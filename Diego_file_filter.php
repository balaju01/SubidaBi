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
foreach ($dir as $fileinfo) {
    if (!$fileinfo->isDot()) {
        ///// APPLE MUSIC
        if ((strpos($fileinfo->getFilename(), '_ZZ') !== false) && (strpos($fileinfo->getFilename(), 'S1_') !== false)) {
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
            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio nuevo
            unlink($old_file); // Eliminar archivo antiguo
            printf(" hecho.<br>");
        }
        ///// ITUNES
        elseif (strpos($fileinfo->getFilename(), '_ZZ') !== false) {
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
            file_put_contents($new_file, rtrim($new_content)); // Guardar archivo limpio nuevo
            unlink($old_file); // Eliminar archivo antiguo
            printf(" hecho.<br>");
        }
        ///// ORCHARD
        elseif (strpos($fileinfo->getFilename(), 'fullreport_fonarte') !== false && $fileinfo->getExtension() == 'xls') {
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
    }
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
