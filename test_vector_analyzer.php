<?php
// Тестирование VectorPriceAnalyzer для диагностики ошибки
header('Content-Type: application/json; charset=utf-8');

// Подключение автозагрузчика
require_once __DIR__ . '/vendor/autoload.php';

// Подключение к базе данных
require_once __DIR__ . '/config/database.php';

use ResearcherAI\VectorPriceAnalyzer;

try {
    // Создание экземпляра анализатора с правильными аргументами
    $aiProvider = new \ResearcherAI\DeepSeekProvider('test-key');
    $yandexDisk = new \ResearcherAI\YandexDiskClient('test-token');
    $cacheManager = new \ResearcherAI\CacheManager(__DIR__ . '/db');
    
    $analyzer = new VectorPriceAnalyzer($aiProvider, $yandexDisk, $cacheManager);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'VectorPriceAnalyzer создан успешно',
        'timestamp' => date('Y-m-d H:i:s'),
        'test_query' => 'CronaFloor NANO'
    ], JSON_UNESCAPED_UNICODE);
    
    // Попробуем выполнить поиск
    $result = $analyzer->processQuery('CronaFloor NANO');
    
    echo json_encode([
        'status' => 'search_success',
        'result' => $result,
        'timestamp' => date('Y-m-d H:i:s')
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
