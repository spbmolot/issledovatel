
<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);



header('Content-Type: application/json; charset=utf-8');



echo json_encode([

    'status' => 'API Working',

    'time' => date('Y-m-d H:i:s'),

    'php_version' => PHP_VERSION,

    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'

], JSON_PRETTY_PRINT);

?>

