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
	print($fileinfo->isDot() ? "Es un punto<br>" : "No es un punto<br>");
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
        ///// APPLE MUSIC
        if ((strpos($fileinfo->getFilename(), '_ZZ') !== false) && (strpos($fileinfo->getFilename(), 'S1_') !== false)) {
            printf("<h4>APPLE MUSIC.</h4>");
            echo "INFO (Línea 88): Procesando archivo de Apple Music: " . $fileinfo->getFilename() . "<br>";
            // Establecer rutas de archivo nuevas y antiguas
            $old_file = $fileinfo->getPathname();
            $new_file = strstr($fileinfo->getPathname(), ".txt", true) . "-clean.txt";
            // Verificar si el archivo ya existe
            if (file_exists($new_file)) {
                printf(" archivo ya existe.<br>");
                continue;
            }
            // Inicializar variables de bucle de archivo
            $lines = file($old_file, FILE_IGNORE_NEW_LINES); // Leer archivo en array
            $new_content = '';
            // Recorrer cada fila y columna
            foreach ($lines as $index => $row) {
                if ($index == 0 || $index == 1 || $index == 2 || $index == 3) continue;
                if (strpos($row, "Row Count\t") !== false) break;
                $new_content .= $row . "\t" . $col_year . "\t" . $col_month . "\n";
            }
            //var_dump($new_content);
            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio nuevo
            unlink($old_file); // Eliminar archivo antiguo
            printf(" hecho.<br>");
        }
        ///// ITUNES
        elseif (strpos($fileinfo->getFilename(), '_ZZ') !== false) {
            printf("<h4>ITUNES.</h4>");
            echo "INFO (Línea 112): Procesando archivo de iTunes: " . $fileinfo->getFilename() . "<br>";
            // Establecer rutas de archivo nuevas y antiguas
            $old_file = $fileinfo->getPathname();
            $new_file = strstr($fileinfo->getPathname(), ".txt", true) . "-clean.txt";
            // Verificar si el archivo ya existe
            if (file_exists($new_file)) {
                printf(" archivo ya existe.<br>");
                continue;
            }
            // Inicializar variables de bucle de archivo
            $lines = file($old_file, FILE_IGNORE_NEW_LINES); // Leer archivo en array
            $new_content = '';
            // Recorrer cada fila y columna
            foreach ($lines as $index => $row) {
                if ($index == 0) continue;
                if (strpos($row, "Total_Rows\t") !== false) break;
                $new_content .= $row . "\t" . $col_year . "\t" . $col_month . "\n";
                
            }
            var_dump($new_content);
            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio nuevo
            unlink($old_file); // Eliminar archivo antiguo
            printf(" hecho.<br>");
        }
        ///// ORCHARD
        elseif (strpos($fileinfo->getFilename(), 'fullreport_fonarte') !== false && $fileinfo->getExtension() == 'xls') {
            printf("<h4>ORCHARD.</h4>");
            echo "INFO (Línea 136): Procesando archivo de Orchard: " . $fileinfo->getFilename() . "<br>";
            // Establecer rutas de archivo nuevas y antiguas
            $old_file = $fileinfo->getPathname();
            $new_file = strstr($fileinfo->getPathname(), ".xls", true) . "-clean.txt";
            // Verificar si el archivo ya existe
            if (file_exists($new_file)) {
                printf(" archivo ya existe.<br>");
                continue;
            }
            // Inicializar variables de bucle de archivo
            $fp = fopen($old_file, 'r'); // Abrir archivo en modo lectura
            $new_content = '';

            while (($row = fgets($fp)) !== false) {
                $row = str_replace('"', '', $row); // Eliminar comillas ["]
                $temp_arr = explode(',', $row);

                if (empty($temp_arr[0])) break; // Detener si la fila está vacía
                array_pop($temp_arr); // Eliminar última columna si es necesario
                $temp_arr[] = $col_year; // Agregar año
                $temp_arr[] = $col_month; // Agregar mes
                $new_content .= implode("\t", $temp_arr) . "\n";
            }

            fclose($fp); // Cerrar archivo
            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio nuevo
            unlink($old_file); // Eliminar archivo antiguo
            printf(" hecho.<br>");
        }
        ///// TIPO DE CAMBIO
        elseif (strpos($fileinfo->getFilename(), 'financial_') !== false && $fileinfo->getExtension() == 'csv') {
            printf("<h4>TIPO DE CAMBIO.</h4>");
            echo "INFO (Línea 176): Procesando archivo de tipo de cambio: " . $fileinfo->getFilename() . "<br>";
            // Establecer rutas de archivo nuevas y antiguas
            $old_file = $fileinfo->getPathname();
            $new_file = strstr($fileinfo->getPathname(), ".csv", true) . "-clean.txt";
            // Verificar si el archivo ya existe
            if (file_exists($new_file)) {
                printf(" archivo ya existe.<br>");
                continue;
            }
            // Inicializar variables de bucle de archivo
            $lines = file($old_file, FILE_IGNORE_NEW_LINES); // Leer archivo en array
            $new_content = '';
            $temp_arr = array();
            // Recorrer cada fila y columna
            foreach ($lines as $index => $row) {
                if ($index == 0 || $index == 1 || $index == 2) continue;
                $str = str_replace('"', '', $row); // Eliminar comillas ["]
                $temp_arr = explode(',', $str);
                if ($temp_arr[0] == '') break;
                array_pop($temp_arr);
                $temp_arr[] = $col_year;
                $temp_arr[] = $col_month;
                $new_content .= implode("\t", $temp_arr) . "\n";
            }
            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio nuevo
            unlink($old_file); // Eliminar archivo antiguo
            printf(" hecho.<br>");
        }
        ///// TMP_APPLEMUSIC
        if (strpos($fileinfo->getFilename(), 'TMP_APPLEMUSIC') !== false) {
            printf("<h4>Procesando TMP_APPLEMUSIC...</h4>");
            $old_file = $fileinfo->getPathname();
            $new_file = strstr($fileinfo->getPathname(), ".txt", true) . "-clean.txt";

            if (file_exists($new_file)) {
                printf("Archivo limpio ya existe.<br>");
                continue;
            }

            $lines = file($old_file, FILE_IGNORE_NEW_LINES); // Leer archivo en array
            $new_content = '';

            foreach ($lines as $index => $row) {
                if ($index == 0) continue; // Saltar encabezados

                $columns = str_getcsv($row, "\t"); // Dividir columnas por tabulaciones

                // Calcular NET_ROTALTY
                $net_royalty_total = floatval($columns[5]); // Net Royalty Total
                $total_royalty_bearing_plays = floatval($columns[3]); // Total Royalty Bearing Plays
                $net_rotalty = ($total_royalty_bearing_plays > 0) ? $net_royalty_total / $total_royalty_bearing_plays : 0;

                // Agregar ITEM_TYPE
                $item_type = 1;

                // Agregar columnas calculadas y ANIO/MES
                $columns[] = $net_rotalty; // NET_ROTALTY
                $columns[] = $item_type;  // ITEM_TYPE
                $columns[] = $col_year;  // ANIO
                $columns[] = $col_month; // MES

                $new_content .= implode("\t", $columns) . "\n";
            }

            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio
            unlink($old_file); // Eliminar archivo original
            printf("TMP_APPLEMUSIC procesado y guardado en '%s'.<br>", $new_file);
        }

        ///// TMP_FINANCIAL_REPORT
        elseif (strpos($fileinfo->getFilename(), 'TMP_FINANCIAL_REPORT') !== false) {
            printf("<h4>Procesando TMP_FINANCIAL_REPORT...</h4>");
            $old_file = $fileinfo->getPathname();
            $new_file = strstr($fileinfo->getPathname(), ".csv", true) . "-clean.txt";

            if (file_exists($new_file)) {
                printf("Archivo limpio ya existe.<br>");
                continue;
            }

            $lines = file($old_file, FILE_IGNORE_NEW_LINES); // Leer archivo en array
            $new_content = '';

            foreach ($lines as $index => $row) {
                if ($index == 0) continue; // Saltar encabezados

                $columns = str_getcsv($row, ","); // Dividir columnas por comas

                // Tratar País o región (Divisa)
                $pais_region_divisa = $columns[0]; // País o región (Divisa)
                $divisa = substr($pais_region_divisa, -4, 3); // Extraer abreviatura de la divisa

                // Reemplazar el valor tratado en la columna
                $columns[0] = $divisa;

                // Agregar ANIO/MES
                $columns[] = $col_year;  // ANIO
                $columns[] = $col_month; // MES

                $new_content .= implode("\t", $columns) . "\n";
            }

            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio
            unlink($old_file); // Eliminar archivo original
            printf("TMP_FINANCIAL_REPORT procesado y guardado en '%s'.<br>", $new_file);
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
