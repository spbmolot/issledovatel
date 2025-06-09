<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use ResearcherAI\Logger;
use ResearcherAI\AIProviderFactory;
use ResearcherAI\CacheManager;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorPriceAnalyzer;

// Установить заголовки для JSON ответа
header('Content-Type: application/json; charset=utf-8');

try {
    Logger::info("🚀 [TEST] Тестирование VectorPriceAnalyzer с отображением прогресса");
    
    // Инициализация компонентов
    $aiProvider = AIProviderFactory::create($settings['ai_provider'] ?? 'deepseek', [
        'api_key' => $settings['deepseek_api_key'] ?? '',
        'proxy' => null
    ]);
    
    $yandexClient = new YandexDiskClient($settings['yandex_token']);
    $cacheManager = new CacheManager();
    
    // Создаем VectorPriceAnalyzer
    $vectorAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexClient, $cacheManager);
    
    // Тестовый запрос 
    $testQuery = "Найди информацию о смартфонах iPhone или Samsung";
    
    Logger::info("📱 [TEST] Тестовый запрос: {$testQuery}");
    
    // Выполняем поиск с векторизацией
    $result = $vectorAnalyzer->processQueryWithVectorSearch($testQuery, $settings['yandex_folder']);
    
    // Структурируем ответ как в реальном API
    $response = [
        'success' => true,
        'response' => $result['response'] ?? 'Нет ответа от AI',
        'sources' => $result['sources'] ?? [],
        'progress' => $result['progress'] ?? [],
        'debug' => [
            'query' => $testQuery,
            'progress_steps' => count($result['progress'] ?? []),
            'sources_count' => count($result['sources'] ?? []),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Выводим JSON ответ
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    Logger::info("✅ [TEST] Тестирование завершено успешно");
    
} catch (Exception $e) {
    Logger::error("❌ [TEST] Ошибка тестирования: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
