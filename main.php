<?php
// Configuración
define("FOLDER_PATH", 'C:/xampp/htdocs/SubidaBi/REPORTES_FONARTE');
define("LOG_PATH", 'C:/xampp/htdocs/SubidaBi/log');
define("BAT_FILE", 'C:/xampp/htdocs/SubidaBi/loadfiles.bat');
define("MODE", isset($_GET['web']) ? 'WEB' : 'CLI'); // Ejecución web o local

// Función para procesar archivos (Diego_file_filter.php)
function processFiles() {
    printf("INFO: Procesando archivos con Diego_file_filter.php...\n");
    include 'Diego_file_filter.php'; // Ejecuta la lógica de limpieza de archivos
}

// Función para cargar datos a Azure (loadfiles.bat)
function uploadToAzure() {
    printf("INFO: Cargando datos a Azure con loadfiles.bat...\n");
    $output = shell_exec('cmd /C ' . BAT_FILE . ' 2>&1'); // Ejecuta el archivo .bat
    printf("INFO: Resultado de loadfiles.bat:\n%s\n", $output);
}

// Función para registrar logs
function logExecution($start_time, $output_str) {
    $end_time = microtime(true);
    $execution_time = round(($end_time - $start_time) / 60, 2); // Tiempo en minutos

    // Crear carpeta de logs si no existe
    if (!is_dir(LOG_PATH)) mkdir(LOG_PATH, 0777, true);

    // Crear archivo de log
    $log_file_name = date("Y_m_d_His") . '.txt';
    $output_str .= "Tiempo total de ejecución: $execution_time minutos\n";
    file_put_contents(LOG_PATH . '/' . $log_file_name, $output_str);

    printf("INFO: Log guardado en %s\n", LOG_PATH . '/' . $log_file_name);
}

// Ejecución principal
$time_start = microtime(true); // Inicia el temporizador
$output_str = ""; // Inicializa el contenido del log

if (MODE === 'CLI') {
    printf("Iniciando procesamiento en modo CLI...\n");
    $output_str .= "Iniciando procesamiento en modo CLI...\n";

    // Procesar archivos
    processFiles();

    // Cargar datos a Azure
    uploadToAzure();

    // Registrar logs
    logExecution($time_start, $output_str);

    printf("Proceso completado. Ver logs en: %s\n", LOG_PATH);
} else {
    echo "<pre>";
    printf("<h3>Procesando archivos en modo WEB...</h3>");
    $output_str .= "Procesando archivos en modo WEB...\n";

    // Procesar archivos
    processFiles();

    // Cargar datos a Azure
    uploadToAzure();

    // Registrar logs
    logExecution($time_start, $output_str);

    printf("<h3>Proceso completado. Ver logs en: %s</h3>", LOG_PATH);
    echo "</pre>";
}
?>