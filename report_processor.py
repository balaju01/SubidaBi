import os
import sys
import time
import pandas as pd
import pyodbc
from datetime import datetime
import shutil
import zipfile
import tkinter as tk
from tkinter import filedialog
from sqlalchemy import create_engine
from tenacity import retry, stop_after_attempt, wait_exponential

class FonarteProcessor:
    def __init__(self):
        self.time_start = time.time()
        self.log_content = ""
        self.input_folder = ""
        self.output_folder = ""
        self.processed_folder = ""
        self.log_folder = ""
        
    def setup_folders(self):
        """Configura las rutas de carpetas usando interfaz gráfica"""
        root = tk.Tk()
        root.withdraw()
        
        print("Selecciona la carpeta con los archivos de entrada (REPORTES_FONARTE):")
        self.input_folder = filedialog.askdirectory(title="Seleccionar carpeta de entrada")
        
        if not self.input_folder:
            print("No se seleccionó carpeta. Saliendo...")
            sys.exit()
            
        base_path = os.path.dirname(self.input_folder)
        self.output_folder = os.path.join(base_path, "REPORTES_FONARTE_PROCESADOS")
        self.log_folder = os.path.join(base_path, "Logs_Procesamiento")
        
        os.makedirs(self.output_folder, exist_ok=True)
        os.makedirs(self.log_folder, exist_ok=True)
        
    def log(self, message):
        """Agrega mensajes al log"""
        timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        log_line = f"[{timestamp}] {message}\n"
        self.log_content += log_line
        print(log_line.strip())
        
    def process_files(self):
        """Procesa todos los archivos según su tipo"""
        print("Iniciando procesamiento de archivos...")
        
        # Procesar cada archivo en la carpeta
        for filename in os.listdir(self.input_folder):
            filepath = os.path.join(self.input_folder, filename)
            
            try:
                if filename.endswith('.zip'):
                    self.process_zip(filepath)
                elif 'fullreport_fonarte' in filename and filename.endswith('.xls'):
                    self.process_orchard(filepath)
                elif '_ZZ' in filename and 'S1_' in filename:
                    self.process_apple_music(filepath)
                elif '_ZZ' in filename:
                    self.process_itunes(filepath)
                elif 'financial_' in filename and filename.endswith('.csv'):
                    self.process_financial(filepath)
                    
            except Exception as e:
                print(f"ERROR procesando {filename}: {str(e)}")
                
    def process_zip(self, filepath):
        """Extrae archivos ZIP"""
        print(f"Extrayendo ZIP: {os.path.basename(filepath)}")
        with zipfile.ZipFile(filepath, 'r') as zip_ref:
            zip_ref.extractall(self.input_folder)
        #os.remove(filepath)
        
    def process_orchard(self, filepath):
        """Procesa archivo Orchard (XLS) con encoding UCS-2"""
        print(f"Procesando Orchard: {os.path.basename(filepath)}")
        
        try:
            # Leer el archivo como binario y detectar encoding
            with open(filepath, 'rb') as f:
                raw_data = f.read()
            
            # Intentar decodificar como UTF-16-LE (UCS-2) primero
            try:
                decoded_content = raw_data.decode('utf-16-le')
            except UnicodeDecodeError:
                # Si falla, probar con UTF-8
                decoded_content = raw_data.decode('utf-8')
            
            # Procesar línea por línea
            lines = decoded_content.splitlines()
            processed_lines = []
            
            for i, line in enumerate(lines):
                if i == 0:  # Saltar encabezado (si existe)
                    continue
                
                # Limpiar comillas y separar por tabs
                clean_line = line.replace('"', '').strip()
                if not clean_line:
                    continue
                
                parts = clean_line.split('\t')
                
                # Extraer año y mes (ejemplo: "2025M02" -> 2025, 02)
                if 'M' in parts[0]:
                    year, month = parts[0].split('M')[:2]
                else:
                    year, month = str(datetime.now().year), f"{datetime.now().month:02d}"
                
                # Agregar columnas de año y mes
                parts.extend([year, month])
                processed_lines.append('\t'.join(parts) + '\n')
            
            # Guardar en UTF-8
            output_path = os.path.join(self.input_folder, "TMP_ORCHARD.txt")
            with open(output_path, 'w', encoding='utf-8') as f:
                f.writelines(processed_lines)
            
            #os.remove(filepath)
            print("Archivo Orchard procesado correctamente")
        
        except Exception as e:
            print(f"ERROR al procesar {os.path.basename(filepath)}: {str(e)}")
            raise
            
    def process_apple_music(self, filepath):
        """Procesa archivo Apple Music"""
        print(f"Procesando Apple Music: {os.path.basename(filepath)}")
        
        try:
            # Verificar si el archivo limpio ya existe
            base_path = os.path.splitext(filepath)[0]
            output_path = f"{base_path}-clean.txt"
            
            if os.path.exists(output_path):
                print("El archivo limpio ya existe. Saltando...")
                return
            
            # Leer el archivo original
            with open(filepath, 'r', encoding='utf-8') as f:
                lines = f.readlines()
            
            # Procesar cada línea
            new_content = []
            current_year = datetime.now().year
            current_month = datetime.now().month
            
            for i, line in enumerate(lines):
                line = line.strip()
                
                # Saltar las primeras 4 líneas (índices 0-3)
                if i < 4:
                    continue
                    
                # Detener el procesamiento si encontramos "Row Count\t"
                if "Row Count\t" in line:
                    break
                    
                # Agregar año y mes como nuevas columnas
                processed_line = f"{line}\t{current_year}\t{current_month}\n"
                new_content.append(processed_line)
            
            # Guardar archivo procesado
            output_path = os.path.join(self.input_folder, "TMP_APPLEMUSIC.txt")
            with open(output_path, 'w', encoding='utf-8') as f:
                f.writelines(new_content)
            
            #os.remove(filepath)
            print("Archivo Apple Music procesado correctamente")
            
        except Exception as e:
            print(f"ERROR al procesar Apple Music {os.path.basename(filepath)}: {str(e)}")
            raise

    def process_itunes(self, filepath):
        """Procesa archivos iTunes"""
        print(f"Procesando iTunes: {os.path.basename(filepath)}")
        
        try:
            # Verificar si el archivo limpio ya existe
            base_path = os.path.splitext(filepath)[0]
            output_path = f"{base_path}-clean.txt"
            
            if os.path.exists(output_path):
                print("El archivo limpio ya existe. Saltando...")
                return
            
            # Leer el archivo original
            with open(filepath, 'r', encoding='utf-8') as f:
                lines = f.readlines()
            
            # Procesar cada línea
            new_content = []
            current_year = datetime.now().year
            current_month = datetime.now().month
            
            for i, line in enumerate(lines):
                line = line.strip()
                
                # Saltar la primera línea (encabezado)
                if i == 0:
                    continue
                    
                # Detener el procesamiento si encontramos "Total_Rows\t"
                if "Total_Rows\t" in line:
                    break
                    
                # Agregar año y mes como nuevas columnas
                processed_line = f"{line}\t{current_year}\t{current_month}\n"
                new_content.append(processed_line)
            
            # Guardar archivo procesado
            output_path = os.path.join(self.input_folder, "TMP_ITUNES.txt")
            with open(output_path, 'w', encoding='utf-8') as f:
                f.writelines(new_content)
            
            #os.remove(filepath)
            print("Archivo iTunes procesado correctamente")
            
        except Exception as e:
            print(f"ERROR al procesar iTunes {os.path.basename(filepath)}: {str(e)}")
            raise
    
    def process_financial(self, filepath):
        """Procesa archivos financial (tipo de cambio)"""
        print(f"Procesando Financial Report: {os.path.basename(filepath)}")
        
        try:
            # Verificar si el archivo limpio ya existe
            base_path = os.path.splitext(filepath)[0]
            output_path = f"{base_path}-clean.txt"
            
            if os.path.exists(output_path):
                print("El archivo limpio ya existe. Saltando...")
                return
            
            # Leer el archivo original
            with open(filepath, 'r', encoding='utf-8') as f:
                lines = f.readlines()
            
            # Procesar cada línea
            new_content = []
            current_year = datetime.now().year
            current_month = datetime.now().month
            
            for i, line in enumerate(lines):
                line = line.strip()
                
                # Saltar las primeras 3 líneas (encabezados)
                if i < 3:
                    continue
                    
                # Eliminar comillas y separar por comas
                clean_line = line.replace('"', '')
                parts = clean_line.split(',')
                
                # Detener el procesamiento si la primera columna está vacía
                if not parts[0]:
                    break
                    
                # Eliminar el último elemento y agregar año/mes
                parts = parts[:-1]
                parts.extend([str(current_year), str(current_month)])
                
                # Unir con tabs y agregar a contenido nuevo
                new_content.append('\t'.join(parts) + '\n')
            
            # Guardar archivo procesado
            output_path = os.path.join(self.input_folder, "TMP_FINANCIAL_REPORT.txt")
            with open(output_path, 'w', encoding='utf-8') as f:
                f.writelines(new_content)
            
            #os.remove(filepath)
            print("Archivo Financial Report procesado correctamente")
            
        except Exception as e:
            print(f"ERROR al procesar Financial Report {os.path.basename(filepath)}: {str(e)}")
            raise

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=4, max=10))
    def insert_with_pandas(self, table_name, file_name):
        """Versión mejorada que respeta la estructura de la tabla existente"""
        print(f"Iniciando inserción estructurada de {file_name} en {table_name}")
        
        try:
            # 1. Validación del archivo
            file_path = os.path.join(self.input_folder, file_name)
            if not os.path.exists(file_path):
                raise FileNotFoundError(f"Archivo no encontrado: {file_path}")
            
            # 2. Lectura optimizada del archivo
            print(f"Leyendo archivo {file_name}")
            try:
                # Leer sin nombres de columna
                df = pd.read_csv(file_path, sep='\t', header=None, 
                            encoding='utf-8', 
                            on_bad_lines='warn',
                            dtype=str)
                print(f"Archivo leído. Dimensiones: {df.shape}")
            except Exception as e:
                raise ValueError(f"Error leyendo archivo {file_name}: {str(e)}")

            # 3. Obtener estructura de la tabla destino
            conn_str = (
                "Driver={ODBC Driver 17 for SQL Server};"
                "Server=fonarte2.database.windows.net;"
                "Database=Reporteador;"
                "UID=Dataguys2;"
                "PWD=Fonarte2018;"
                "Connection Timeout=30;"
            )
            
            with pyodbc.connect(conn_str) as conn:
                cursor = conn.cursor()
                
                # Obtener columnas de la tabla
                cursor.execute(f"SELECT TOP 0 * FROM {table_name}")
                columns = [column[0] for column in cursor.description]
                
                # Verificar coincidencia de columnas
                if len(columns) != len(df.columns):
                    raise ValueError(f"El archivo tiene {len(df.columns)} columnas pero la tabla {table_name} tiene {len(columns)}")
                
                # 4. Construir consulta SQL parametrizada
                placeholders = ", ".join(["?"] * len(columns))
                query = f"INSERT INTO {table_name} VALUES ({placeholders})"
                print(f"Query preparada: {query[:100]}...")
                
                # 5. Insertar por lotes con los nombres de columna correctos
                chunksize = 300
                total_rows = len(df)
                inserted = 0
                
                for i in range(0, total_rows, chunksize):
                    chunk = df.iloc[i:i + chunksize]
                    chunk_start = time.time()
                    
                    try:
                        # Convertir a lista de tuplas
                        data = [tuple(x) for x in chunk.to_numpy()]
                        
                        # Ejecutar inserción
                        cursor.executemany(query, data)
                        conn.commit()
                        
                        inserted += len(chunk)
                        chunk_time = time.time() - chunk_start
                        print(f"Lote {i//chunksize + 1}: {len(chunk)} filas insertadas "
                            f"({inserted}/{total_rows}) en {chunk_time:.2f}s")
                        
                    except Exception as chunk_error:
                        conn.rollback()
                        print(f"Error en lote {i//chunksize + 1}: {str(chunk_error)}")
                        print(f"Datos problemáticos: {chunk.iloc[0].tolist()}")
                        raise
                    
                print(f"Inserción completada. Total: {inserted} filas")
                    
        except Exception as e:
            print(f"ERROR crítico en inserción: {str(e)}")
            raise

    def upload_to_azure(self):
        """Sube los datos a Azure SQL"""
        print("Conectando a Azure SQL...")
        
        try:
            conn_str = (
                "Driver={ODBC Driver 17 for SQL Server};"
                "Server=fonarte2.database.windows.net;"
                "Database=Reporteador;"
                "UID=Dataguys2;"
                "PWD=Fonarte2018;"
            )
            
            with pyodbc.connect(conn_str) as conn:
                cursor = conn.cursor()
                
                # Truncar tablas temporales
                print("Limpiando tablas temporales...")
                #cursor.execute("TRUNCATE TABLE TMP_APPLEMUSIC;")
                cursor.execute("TRUNCATE TABLE TMP_ITUNES;")
                #cursor.execute("TRUNCATE TABLE TMP_FINANCIAL_REPORT;")
                #cursor.execute("TRUNCATE TABLE TMP_ORCHARD;")
                
                # Cargar datos usando pandas
                print("Cargando datos a tablas temporales...")
                #self.insert_with_pandas("TMP_APPLEMUSIC", "TMP_APPLEMUSIC.txt")
                self.insert_with_pandas("TMP_ITUNES", "TMP_ITUNES.txt")
                #self.insert_with_pandas("TMP_FINANCIAL_REPORT", "TMP_FINANCIAL_REPORT.txt")
                #self.insert_with_pandas("TMP_ORCHARD", "TMP_ORCHARD.txt")
                
                # Actualizar tablas finales
                print("Actualizando tablas finales...")
                #cursor.execute("INSERT INTO APPLEMUSIC SELECT * FROM TMP_APPLEMUSIC;")
                #cursor.execute("INSERT INTO ITUNES SELECT * FROM TMP_ITUNES;")
                #cursor.execute("INSERT INTO ORCHARD SELECT * FROM TMP_ORCHARD;")
                '''cursor.execute("""
                    INSERT INTO APPLE_CURRENCY 
                    SELECT LEFT(RIGHT(DIVISA,4),3), TIPO_DE_CAMBIO, ANIO, MES 
                    FROM TMP_FINANCIAL_REPORT;
                """)'''
                
                conn.commit()
                print("Datos actualizados correctamente en Azure SQL")
                
        except Exception as e:
            print(f"ERROR en Azure SQL: {str(e)}")
            raise
            
    def move_processed_files(self):
        """Mueve los archivos procesados a la carpeta de salida"""
        print("Moviendo archivos procesados...")
        for filename in os.listdir(self.input_folder):
            src = os.path.join(self.input_folder, filename)
            dst = os.path.join(self.output_folder, filename)
            shutil.move(src, dst)
            
    def save_log(self):
        """Guarda el log en un archivo"""
        log_file = os.path.join(self.log_folder, f"log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt")
        
        # Agregar tiempo total de ejecución
        exec_time = (time.time() - self.time_start) / 60
        print(f"Tiempo total de ejecución: {exec_time:.2f} minutos")
        
        with open(log_file, 'w', encoding='utf-8') as f:
            f.write(self.log_content)
            
        print(f"Log guardado en: {log_file}")

if __name__ == "__main__":
    processor = FonarteProcessor()
    processor.setup_folders()
    
    try:
        processor.process_files()
        processor.upload_to_azure()
        processor.move_processed_files()
        processor.save_log()
        
    except Exception as e:
        processor.log(f"ERROR FATAL: {str(e)}")
        processor.save_log()
        sys.exit(1)