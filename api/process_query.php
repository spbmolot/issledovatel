
<?php

ini_set('display_errors', 1); 

ini_set('display_startup_errors', 1); 

error_reporting(E_ALL); 

ini_set('log_errors', 1); 

ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); 



require_once __DIR__ . '/../vendor/autoload.php';



mb_internal_encoding('UTF-8');

mb_http_output('UTF-8');



require_once __DIR__ . '/../config/database.php';



use ResearcherAI\Logger;

use ResearcherAI\AIProviderFactory;

use ResearcherAI\YandexDiskClient;

use ResearcherAI\VectorPriceAnalyzer;

use ResearcherAI\CacheManager; 



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);

    exit;

}



try {

    $input = json_decode(file_get_contents('php://input'), true);

    $query = $input['query'] ?? '';

    

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

    $yandexFolderPath = $settings['yandex_folder'] ?? '/Прайсы';



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

    $aiProvider = AIProviderFactory::create($selectedAiProviderName, $aiApiKey, $proxyUrl);

    Logger::info("[process_query] AIProvider created: {$selectedAiProviderName}");



    // 2. Создаем YandexDiskClient

    $yandexDiskClient = new YandexDiskClient($yandexToken, $proxyUrl);

    Logger::info("[process_query] YandexDiskClient created.");



    // 3. Создаем CacheManager

    $dbBaseDir = __DIR__ . '/../db'; 

    $cacheManager = new CacheManager($dbBaseDir);

    Logger::info("[process_query] CacheManager created. DB directory: {$dbBaseDir}");



    // 4. Создаем VectorPriceAnalyzer (новый!)

    $priceAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexDiskClient, $cacheManager);

    Logger::info("[process_query] VectorPriceAnalyzer created.");

    

    // 5. Обрабатываем запрос через VectorPriceAnalyzer

    Logger::info("[process_query] Calling VectorPriceAnalyzer->processQuery for folder: {$yandexFolderPath}");

    $result = $priceAnalyzer->processQuery($query, $yandexFolderPath);

    

    // Приводим к ожидаемому формату frontend

    if (isset($result['response'])) {

        $result['text'] = $result['response'];

    }

    

    Logger::info("[process_query] Query processed by VectorPriceAnalyzer. Method: " . ($result['search_method'] ?? 'traditional'));

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

