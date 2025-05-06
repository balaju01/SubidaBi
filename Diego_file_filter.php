<?php
ini_set('memory_limit', '1024M'); // Aumentar el límite de memoria a 1 GB
// Mostrar todos los errores y advertencias
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/*    Filtración de archivos para remover texto no deseado y limpiar las tablas del mes correspondiente.
    Al final se corre un archivo .bat para terminar de actualizar las tablas en la base de datos.
    Andrés P. - 28 de Agosto de 2018
*/
// Establecer la ruta de la carpeta

echo "INFO (Línea 12): Ruta de la carpeta establecida en " . FOLDER_PATH . "<br>";

// Establecer el año y mes predeterminados con signo negativo
$col_year  = "-" . date("Y");
$col_month = "-" . date("n");
echo "INFO (Línea 16): Año y mes predeterminados establecidos en $col_year y $col_month<br>";

// Verificar si hay archivos ZIP y extraerlos
echo "INFO (Línea 20): Verificando archivos ZIP...<br>";
$dir = new DirectoryIterator(FOLDER_PATH);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot() && $fileinfo->getExtension() == 'zip') {
        echo "INFO (Línea 24): Archivo ZIP encontrado: " . $fileinfo->getFilename() . "<br>";
        extractZipFile($fileinfo->getPathname(), FOLDER_PATH);
    }
}

// Verificar si hay carpetas
echo "INFO (Línea 30): Verificando carpetas...<br>";
$dir = new DirectoryIterator(FOLDER_PATH);
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        if ($fileinfo->getType() == 'dir') {
            echo "INFO (Línea 35): Carpeta encontrada: " . $fileinfo->getFilename() . "<br>";
            if (strpos($fileinfo->getFilename(), 'apple') !== false) {
                echo "INFO (Línea 37): Procesando carpeta Apple: " . $fileinfo->getFilename() . "<br>";
                $apple_dir = new DirectoryIterator($fileinfo->getPathname());
                foreach ($apple_dir as $apple_fileinfo) {
                    if (!$apple_fileinfo->isDot()) {
                        copy($apple_fileinfo->getPathname(), (FOLDER_PATH . "\\" . $apple_fileinfo->getFilename()));
                    }
                }
                if (strlen($fileinfo->getPathname()) < 5) {
                    printf("ALERTA (Línea 45): Problema al eliminar la carpeta [%s] porque la ruta parece demasiado corta y podría ser incorrecta.<br>", $fileinfo->getPathname());
                } else {
                    delete_directory($fileinfo->getPathname());
                }
            } else {
                if (strlen($fileinfo->getPathname()) < 5) {
                    printf("ALERTA (Línea 51): Problema al eliminar la carpeta [%s] porque la ruta parece demasiado corta y podría ser incorrecta.<br>", $fileinfo->getPathname());
                } else {
                    delete_directory($fileinfo->getPathname());
                }
            }
        }
    }
}

// Obtener el mes y año reales del archivo original de Orchard
echo "INFO (Línea 61): Verificando archivos Orchard...<br>";
$dir = new DirectoryIterator(FOLDER_PATH);
//var_dump($dir);
foreach ($dir as $fileinfo) {
	echo "DEBUG (Línea 69): Inspeccionando \$fileinfo...<br>";
    //var_dump($fileinfo);
	//print($fileinfo->isDot() ? "Es un punto<br>" : "No es un punto<br>");
    if (!$fileinfo->isDot()) {
        if (strpos($fileinfo->getFilename(), 'fullreport_fonarte') !== false && $fileinfo->getExtension() == 'xls') {
            echo "INFO (Línea 66): Archivo Orchard encontrado: " . $fileinfo->getFilename() . "<br>";
            $fp = fopen($fileinfo->getPathname(), 'r'); // Abrir archivo xls
            $line = fgets($fp); // Saltar encabezados
            $line = fgets($fp); // Obtener la primera fila de datos
            $line = mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'); // Convertir a UTF-8 desde ISO-8859-1
            $line = str_replace('"', '', $line); // Eliminar comillas ["]
            $data = str_getcsv($line, "\t"); // Obtener columnas separadas por tabulaciones
            $col_year  = strstr($data[0], "M", true); // Año
            $col_month = substr(strstr($data[0], "M"), 1); // Mes
            fclose($fp); // Cerrar archivo
            echo "INFO (Línea 76): Año y mes extraídos del archivo Orchard: $col_year, $col_month<br>";
        }
    }
}

// Procesar archivos en la carpeta
echo "INFO (Línea 82): Procesando archivos en la carpeta...<br>";
$dir = new DirectoryIterator(FOLDER_PATH);
$col_month = intval(trim($col_month));
$col_year = trim($col_year);
$col_year = intval(str_replace("$col_year[1]", "", $col_year));
var_dump($col_year);
var_dump($col_month);

foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        
        if ((strpos($fileinfo->getFilename(), '_ZZ') !== false) && (strpos($fileinfo->getFilename(), 'S1_') !== false)) {
            echo  "APPLE MUSIC <br>";
            $old_file = $fileinfo->getPathname();
            $new_file = FOLDER_PATH . "/TMP_APPLEMUSIC.txt"; // Guardar con el nombre de la tabla

            if (file_exists($new_file)) {
                continue;
            }

            $lines = file($old_file, FILE_IGNORE_NEW_LINES);
            $new_content = '';

            foreach ($lines as $index => $row) {
                if ($index == 0 || $index == 1 || $index == 2 ) {
                    //echo $index . " - " . $row . "<br>";
                    continue;
                }
                   
                if ($index == 3) {
                    
                    $row = $row. "\tItem Type\tAnio\tMes\tNet Royalty\n"; 
                    //echo $index . " - " . $row . "<br>";
                    $new_content .= $row;
                }
                
                if (strpos($row, "Row Count\t") !== false) break;

                if ($index > 3 ) $new_content .= $row . "\t1\t" . $col_year . "\t" . $col_month . "\n";
            }

            file_put_contents($new_file, rtrim($new_content));
            unlink($old_file);
        }
        ///// ITUNES
        elseif (strpos($fileinfo->getFilename(), '_ZZ') !== false) {
            $old_file = $fileinfo->getPathname();
            $new_file = FOLDER_PATH . "/TMP_ITUNES.txt"; // Guardar con el nombre de la tabla

            if (file_exists($new_file)) {
                continue;
            }

            $lines = file($old_file, FILE_IGNORE_NEW_LINES);
            $new_content = '';

            foreach ($lines as $index => $row) {
                if ($index == 0){continue;}
                if (strpos($row, "Total_Rows\t") !== false) break;
                $new_content .= $row . "\t" . $col_year . "\t" . $col_month . "\n";
            }

            file_put_contents($new_file, rtrim($new_content));
            unlink($old_file);
        }
        ///// ORCHARD
        elseif(strpos($fileinfo->getFilename(), 'fullreport_fonarte') !== false && $fileinfo->getExtension() == 'xls') {
            printf("Getting [Orchard] ready...");
            // Set new and old file paths
            $old_file = $fileinfo->getPathname();
            $new_file = FOLDER_PATH . "/TMP_ORCHARD.txt"; // Guardar con el nombre de la tabla
            // Check if file already exists
            if(file_exists($new_file)) {
                printf(" file already exists.\n");
                continue;
            }
            // Initialize file looping variables
            $fp = fopen($old_file, 'r'); // Open xls
            $line = fgets($fp); // Skip first row (headers)
            $new_content = '';
            // Loop through rows
            
            while (!feof($fp)){
                $line = fgets($fp); // Get row
                $data = str_getcsv($line, "\t"); // Get columns separated by a tab
                $temp_arr = array();
                // Loop through columns and filter data
                foreach($data as $index => $row) {
                    $str = mb_convert_encoding($row, 'UTF-8', 'UCS-2'); // Convert to UTF8
                    
                    $str = str_replace('"?','',$str); // Remove ["]
                    
                    $str = str_replace('"','',$str); // Remove ["] 
                    $temp_arr[] = mb_convert_encoding($str, 'UTF-8', 'auto'); // Convertir a UTF-8 automáticamente
                    
                    
                }
                if(sizeof($temp_arr) > 2){
                    $temp_arr[] = $col_year; // Add year
                    $temp_arr[] = $col_month; // Add month
                    // Append new row to final string
                    $new_content .= implode("\t",$temp_arr) . "\n";
                }
                
            }
            // Write the new content into the txt file
            $new_content = mb_convert_encoding($new_content, 'UTF-8', 'auto');
            file_put_contents($new_file, $new_content);
            fclose($fp); // Close files
            unlink($old_file); // Delete old file
            printf(" done.\n");
        }
       ///// TIPO DE CAMBIO
        elseif (strpos($fileinfo->getFilename(), 'financial_') !== false && $fileinfo->getExtension() == 'csv') {
            $old_file = $fileinfo->getPathname();
            $new_file = FOLDER_PATH . "/TMP_FINANCIAL_REPORT.txt"; // Guardar con el nombre de la tabla

            if (file_exists($new_file)) {
                continue;
            }

            $lines = file($old_file, FILE_IGNORE_NEW_LINES);
            $new_content = '';
            $temp_arr = array();

            foreach ($lines as $index => $row) {
                if ($index == 0 || $index == 1 || $index == 2) continue;
                $str = str_replace('"', '', $row);
                $temp_arr = explode(',', $str);
                if ($temp_arr[0] == '') break;
                array_pop($temp_arr);
                $temp_arr[] = $col_year;
                $temp_arr[] = $col_month;
                $new_content .= implode("\t", $temp_arr) . "\n";
            }

            file_put_contents($new_file, rtrim($new_content));
            unlink($old_file);
        }
    }
}

// Procesar archivos según el mapeo de columnas
$mapping_file = 'column_mapping.json';

if (!file_exists($mapping_file)) {
    die("ERROR: Archivo de configuración '$mapping_file' no encontrado.");
}

// Leer el archivo JSON
$column_mapping = json_decode(file_get_contents($mapping_file), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("ERROR: Error al parsear el archivo JSON: " . json_last_error_msg());
}
/*
// Procesar cada archivo
foreach ($column_mapping as $table => $mapping) {
    $source_columns = $mapping['source_columns'];
    $db_columns = $mapping['db_columns'];
    $calculations = isset($mapping['calculations']) ? $mapping['calculations'] : [];

    // Leer el archivo de origen
    $source_file = "C:\\xampp\\htdocs\\SubidaBi\\REPORTES_FONARTE\\$table.txt";
    if (!file_exists($source_file)) {
        echo "WARNING: Archivo de origen '$source_file' no encontrado. Saltando...\n";
        continue;
    }

    $lines = file($source_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $new_content = [];

    // Reordenar las columnas según el mapeo y aplicar cálculos
    foreach ($lines as $index => $line) {
        if ($index == 0) continue; // Saltar encabezados

        $row = str_getcsv($line, "\t");
        $reordered_row = [];

        foreach ($db_columns as $db_column) {
            if (isset($calculations[$db_column])) {
                // Evaluar la expresión de cálculo
                $expression = $calculations[$db_column];
                $value = eval("return $expression;");
                $reordered_row[] = $value;
            } else {
                // Buscar el índice de la columna en el archivo de origen
                $index = array_search($db_column, $source_columns);
                $reordered_row[] = $index !== false ? $row[$index] : null;
            }
        }

        $new_content[] = implode("\t", $reordered_row);
    }

    // Guardar el archivo reordenado
    $output_file = str_replace(".txt", "-clean.txt", $source_file);
    file_put_contents($output_file, implode("\n", $new_content));
    echo "INFO: Archivo procesado y guardado en '$output_file'.\n";
}
*/
// Función para extraer archivos ZIP
function extractZipFile($file, $path) {
    echo "INFO (Línea 209): Extrayendo archivo ZIP: $file<br>";
    $zip = new ZipArchive;
    $res = $zip->open($file);
    if ($res === TRUE) {
        $zip->extractTo($path);
        $zip->close();
        unlink($file); // Eliminar el archivo ZIP
        echo "INFO (Línea 216): Archivo ZIP [$file] extraído.<br>";
    } else {
        echo "ERROR (Línea 218): No se pudo extraer el archivo ZIP [$file].<br>";
        exit();
    }
}

// Función para eliminar directorios de forma recursiva
function delete_directory($dirname) {
    echo "INFO (Línea 225): Eliminando directorio: $dirname<br>";
    if (is_dir($dirname)) $dir_handle = opendir($dirname);
    if (!$dir_handle) return false;
    while ($file = readdir($dir_handle)) {
        if ($file != "." && $file != "..") {
            if (!is_dir($dirname . "/" . $file)) unlink($dirname . "/" . $file);
            else delete_directory($dirname . '/' . $file);
        }
    }
    closedir($dir_handle);
    rmdir($dirname);
    echo "INFO (Línea 236): Directorio [$dirname] eliminado.<br>";
    return true;
}
