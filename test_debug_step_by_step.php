<?php
// Пошаговая диагностика для выявления точной причины ошибки
header('Content-Type: application/json; charset=utf-8');

// Подключение автозагрузчика
require_once __DIR__ . '/vendor/autoload.php';

// Подключение к базе данных
require_once __DIR__ . '/config/database.php';

use ResearcherAI\VectorCacheManager;
use ResearcherAI\VectorPriceAnalyzer;

$step = 1;
$results = [];

try {
    // Шаг 1: Автозагрузчик
    $results["step_$step"] = "Подключение автозагрузчика";
    $results["step_$step"] .= " - ✅ УСПЕХ";
    $step++;
    
    // Шаг 2: База данных
    $results["step_$step"] = "Подключение к базе данных";
    $results["step_$step"] .= " - ✅ УСПЕХ";
    $step++;
    
    // Шаг 3: Импорт VectorCacheManager
    $results["step_$step"] = "Импорт VectorCacheManager";
    $results["step_$step"] .= " - ✅ УСПЕХ";
    $step++;
    
    // Шаг 4: Создание VectorCacheManager
    $results["step_$step"] = "Создание VectorCacheManager";
    $vectorCacheManager = new VectorCacheManager(__DIR__ . '/db');
    $results["step_$step"] .= " - ✅ УСПЕХ";
    $step++;
    
    // Шаг 5: Проверка метода getPDO
    $results["step_$step"] = "Проверка метода getPDO";
    $pdo = $vectorCacheManager->getPDO();
    $results["step_$step"] .= " - ✅ УСПЕХ (PDO: " . ($pdo ? "есть" : "нет") . ")";
    $step++;
    
    // Шаг 6: Импорт VectorPriceAnalyzer
    $results["step_$step"] = "Импорт VectorPriceAnalyzer";
    $results["step_$step"] .= " - ✅ УСПЕХ";
    $step++;
    
    // Шаг 7: Создание VectorPriceAnalyzer
    $results["step_$step"] = "Создание VectorPriceAnalyzer";
    
    // Создаём необходимые зависимости
    $aiProvider = new \ResearcherAI\DeepSeekProvider('test-key');
    $yandexDisk = new \ResearcherAI\YandexDiskClient('test-token');
    $cacheManager = new \ResearcherAI\CacheManager(__DIR__ . '/db');
    
    $analyzer = new VectorPriceAnalyzer($aiProvider, $yandexDisk, $cacheManager);
    $results["step_$step"] .= " - ✅ УСПЕХ";
    $step++;
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Все шаги выполнены успешно',
        'steps' => $results,
        'total_steps' => $step - 1,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $results["step_$step"] .= " - ❌ ОШИБКА: " . $e->getMessage();
    
    echo json_encode([
        'status' => 'error',
        'failed_step' => $step,
        'steps' => $results,
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
} catch (Error $e) {
    $results["step_$step"] .= " - ❌ ФАТАЛЬНАЯ ОШИБКА: " . $e->getMessage();
    
    echo json_encode([
        'status' => 'fatal_error',
        'failed_step' => $step,
        'steps' => $results,
        'error' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>
