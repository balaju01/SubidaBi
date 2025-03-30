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
        self.log("Iniciando procesamiento de archivos...")
        
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
                self.log(f"ERROR procesando {filename}: {str(e)}")
                
    def process_zip(self, filepath):
        """Extrae archivos ZIP"""
        self.log(f"Extrayendo ZIP: {os.path.basename(filepath)}")
        with zipfile.ZipFile(filepath, 'r') as zip_ref:
            zip_ref.extractall(self.input_folder)
        os.remove(filepath)
        
    def process_orchard(self, filepath):
        """Procesa archivo Orchard (XLS)"""
        self.log(f"Procesando Orchard: {os.path.basename(filepath)}")
        
        # Leer XLS y convertir a DataFrame
        df = pd.read_excel(filepath, encoding='utf-16')
        
        # Obtener año y mes del primer registro
        first_row = df.iloc[0]
        year_month = first_row[0].split('M')
        year = year_month[0]
        month = year_month[1]
        
        # Agregar columnas de año y mes
        df['Year'] = year
        df['Month'] = month
        
        # Guardar como TXT
        output_path = os.path.join(self.input_folder, "TMP_ORCHARD.txt")
        df.to_csv(output_path, sep='\t', index=False, encoding='utf-8')
        os.remove(filepath)
        
    def process_apple_music(self, filepath):
        """Procesa archivo Apple Music"""
        self.log(f"Procesando Apple Music: {os.path.basename(filepath)}")
        
        # Leer archivo y filtrar filas
        with open(filepath, 'r', encoding='utf-8') as f:
            lines = f.readlines()[4:]  # Saltar primeras 4 líneas
            
        # Guardar archivo procesado
        output_path = os.path.join(self.input_folder, "TMP_APPLEMUSIC.txt")
        with open(output_path, 'w', encoding='utf-8') as f:
            f.writelines(lines)
            
        os.remove(filepath)
        
    # ... (similar para process_itunes y process_financial)

    def upload_to_azure(self):
        """Sube los datos a Azure SQL"""
        self.log("Conectando a Azure SQL...")
        
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
                self.log("Limpiando tablas temporales...")
                cursor.execute("TRUNCATE TABLE TMP_APPLEMUSIC;")
                cursor.execute("TRUNCATE TABLE TMP_ITUNES;")
                cursor.execute("TRUNCATE TABLE TMP_FINANCIAL_REPORT;")
                cursor.execute("TRUNCATE TABLE TMP_ORCHARD;")
                
                # Cargar datos usando BULK INSERT
                self.log("Cargando datos a tablas temporales...")
                self.bulk_insert(cursor, "TMP_APPLEMUSIC", "TMP_APPLEMUSIC.txt")
                self.bulk_insert(cursor, "TMP_ITUNES", "TMP_ITUNES.txt")
                self.bulk_insert(cursor, "TMP_FINANCIAL_REPORT", "TMP_FINANCIAL_REPORT.txt")
                self.bulk_insert(cursor, "TMP_ORCHARD", "TMP_ORCHARD.txt")
                
                # Actualizar tablas finales
                self.log("Actualizando tablas finales...")
                cursor.execute("INSERT INTO APPLEMUSIC SELECT * FROM TMP_APPLEMUSIC;")
                cursor.execute("INSERT INTO ITUNES SELECT * FROM TMP_ITUNES;")
                cursor.execute("INSERT INTO ORCHARD SELECT * FROM TMP_ORCHARD;")
                cursor.execute("""
                    INSERT INTO APPLE_CURRENCY 
                    SELECT LEFT(RIGHT(DIVISA,4),3), TIPO_DE_CAMBIO, ANIO, MES 
                    FROM TMP_FINANCIAL_REPORT;
                """)
                
                conn.commit()
                self.log("Datos actualizados correctamente en Azure SQL")
                
        except Exception as e:
            self.log(f"ERROR en Azure SQL: {str(e)}")
            raise
            
    def bulk_insert(self, cursor, table_name, file_name):
        """Ejecuta BULK INSERT para un archivo"""
        file_path = os.path.join(self.input_folder, file_name)
        query = f"""
        BULK INSERT {table_name}
        FROM '{file_path}'
        WITH (
            FIELDTERMINATOR = '\t',
            ROWTERMINATOR = '\n',
            CODEPAGE = '65001'
        );
        """
        cursor.execute(query)
        
    def move_processed_files(self):
        """Mueve los archivos procesados a la carpeta de salida"""
        self.log("Moviendo archivos procesados...")
        for filename in os.listdir(self.input_folder):
            src = os.path.join(self.input_folder, filename)
            dst = os.path.join(self.output_folder, filename)
            shutil.move(src, dst)
            
    def save_log(self):
        """Guarda el log en un archivo"""
        log_file = os.path.join(self.log_folder, f"log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt")
        
        # Agregar tiempo total de ejecución
        exec_time = (time.time() - self.time_start) / 60
        self.log(f"Tiempo total de ejecución: {exec_time:.2f} minutos")
        
        with open(log_file, 'w', encoding='utf-8') as f:
            f.write(self.log_content)
            
        self.log(f"Log guardado en: {log_file}")

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