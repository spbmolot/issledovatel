<?php
ini_set('display_errors', 1); 
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL); 
ini_set('log_errors', 1); 
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); 

require_once __DIR__ . '/../vendor/autoload.php';

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once __DIR__ . '/../config/database.php'; // Для $pdo

use ResearcherAI\Logger;
use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\PriceAnalyzer; 
use ResearcherAI\CacheManager; 

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Logger::configure(__DIR__ . '/../logs/application.log'); // Пример конфигурации
// Экземпляр логгера не нужен, т.к. все методы статические

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    // $chatId = $input['chat_id'] ?? null; 
    
    if (empty($query)) {
        throw new Exception('Запрос не может быть пустым');
    }
    
    Logger::info("[process_query] Received query: {$query}");

    // Получаем настройки из БД
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        throw new Exception('Настройки не найдены в базе данных');
    }
    
    $selectedAiProviderName = $settings['ai_provider'] ?? 'openai';
    $yandexToken = $settings['yandex_token'] ?? '';
    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;
    $yandexFolderPath = $settings['yandex_folder_path'] ?? '/Прайсы'; 

    if ($selectedAiProviderName === 'deepseek' && empty($settings['deepseek_key'])) {
        throw new Exception('DeepSeek API ключ не настроен');
    }
    if ($selectedAiProviderName === 'openai' && empty($settings['openai_key'])) {
        throw new Exception('OpenAI API ключ не настроен');
    }
    if (empty($yandexToken)) {
        throw new Exception('Yandex Disk токен не настроен');
    }

    Logger::info("[process_query] Settings loaded. AI Provider: {$selectedAiProviderName}, Yandex Folder: {$yandexFolderPath}");

    // 1. Создаем AI провайдер
    $aiApiKey = ($selectedAiProviderName === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);
    // Передаем null вместо логгера, если AIProviderFactory или провайдеры будут изменены для статического использования Logger
    $aiProvider = AIProviderFactory::create($selectedAiProviderName, $aiApiKey, $proxyUrl /*, null */ ); 
    Logger::info("[process_query] AIProvider created: {$selectedAiProviderName}");

    // 2. Создаем YandexDiskClient
    // Передаем null вместо логгера, если YandexDiskClient будет изменен для статического использования Logger
    $yandexDiskClient = new YandexDiskClient($yandexToken, $proxyUrl /*, null */);
    Logger::info("[process_query] YandexDiskClient created.");

    // 3. Создаем CacheManager
    $dbBaseDir = __DIR__ . '/../db'; 
    // Передаем null вместо логгера, CacheManager будет изменен для статического использования Logger
    $cacheManager = new CacheManager($dbBaseDir /*, null */);
    Logger::info("[process_query] CacheManager created. DB directory: {$dbBaseDir}");

    // 4. Создаем PriceAnalyzer
    // PriceAnalyzer конструктор уже не принимает логгер
    $priceAnalyzer = new PriceAnalyzer($aiProvider, $yandexDiskClient, $cacheManager);
    Logger::info("[process_query] PriceAnalyzer created.");
    
    // 5. Обрабатываем запрос через PriceAnalyzer
    Logger::info("[process_query] Calling PriceAnalyzer->processQuery for folder: {$yandexFolderPath}");
    $result = $priceAnalyzer->processQuery($query, $yandexFolderPath);
    
    Logger::info("[process_query] Query processed by PriceAnalyzer. Result: " . json_encode($result, JSON_UNESCAPED_UNICODE));
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    Logger::error('[process_query] Database error: ' . $e->getMessage(), $e);
    error_log('[process_query.php] Database Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка базы данных: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    Logger::error('[process_query] General error: ' . $e->getMessage(), $e);
    error_log('[process_query.php] General Error: ' . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Произошла ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
