
<?php

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



$diagnostic = [

    'timestamp' => date('Y-m-d H:i:s'),

    'php_version' => PHP_VERSION,

    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',

    'request_method' => $_SERVER['REQUEST_METHOD'],

    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'

];



// Проверяем расширения PHP

$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];

$diagnostic['php_extensions'] = [];

foreach ($required_extensions as $ext) {

    $diagnostic['php_extensions'][$ext] = extension_loaded($ext);

}



// Проверяем файлы

$required_files = [

    '../config/database.php',

    '../classes/AIProvider.php',

    '../classes/YandexDiskClient.php'

];

$diagnostic['files'] = [];

foreach ($required_files as $file) {

    $diagnostic['files'][$file] = file_exists($file);

}



// Проверяем БД

try {

    require_once '../config/database.php';

    $diagnostic['database'] = 'connected';

    

    $stmt = $pdo->query("SELECT COUNT(*) FROM researcher_settings");

    $diagnostic['settings_count'] = $stmt->fetchColumn();

} catch (Exception $e) {

    $diagnostic['database'] = 'error: ' . $e->getMessage();

}



echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>

