<?php
// Тестирование API напрямую для диагностики ошибки JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Подключение автозагрузчика
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Подключение к базе данных
    require_once __DIR__ . '/config/database.php';
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Автозагрузчик и база данных подключены успешно',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'extensions' => [
            'mbstring' => extension_loaded('mbstring'),
            'curl' => extension_loaded('curl'),
            'sqlite3' => extension_loaded('sqlite3'),
            'pdo' => extension_loaded('pdo')
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    echo json_encode([
        'status' => 'fatal_error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE);
}
?>
