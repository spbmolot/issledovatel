
<?php

// Принудительно устанавливаем UTF-8

mb_internal_encoding('UTF-8');

mb_http_output('UTF-8');



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../classes/AIProvider.php';

require_once __DIR__ . '/../classes/YandexDiskClient.php';



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

    $chatId = $input['chat_id'] ?? null;

    

    if (empty($query)) {

        throw new Exception('Запрос не может быть пустым');

    }

    

    // Получаем настройки

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch();

    

    if (!$settings) {

        throw new Exception('Настройки не найдены');

    }

    

    $aiProvider = $settings['ai_provider'] ?? 'openai';

    

    // Проверяем ключи

    if ($aiProvider === 'deepseek' && empty($settings['deepseek_key'])) {

        throw new Exception('DeepSeek API ключ не настроен');

    }

    

    if (empty($settings['yandex_token'])) {

        throw new Exception('Yandex Disk токен не настроен');

    }

    

    // Создаем AI провайдер

    try {

        if ($aiProvider === 'deepseek') {

            $ai = AIProviderFactory::create('deepseek', $settings['deepseek_key']);

        } else {

            $proxyUrl = $settings['proxy_enabled'] ? $settings['proxy_url'] : null;

            $ai = AIProviderFactory::create('openai', $settings['openai_key'], $proxyUrl);

        }

    } catch (Exception $e) {

        throw new Exception('Ошибка создания AI провайдера: ' . $e->getMessage());

    }

    

    // Создаем Yandex Disk клиент

    $yandexDisk = new YandexDiskClient($settings['yandex_token']);

    

    // Извлекаем ключевые слова

    $keywords = $ai->extractKeywords($query);

    

    // Ищем файлы на Яндекс.Диске

    $folder = $settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';

    $files = [];

    

    try {

        $allFiles = $yandexDisk->listFiles($folder);

        error_log("Found " . count($allFiles) . " files in folder: $folder");

        

        // Берем первые 3 файла для демонстрации

        $files = array_slice($allFiles, 0, 3);

    } catch (Exception $e) {

        error_log('Yandex Disk error: ' . $e->getMessage());

    }

    

    // Создаем демо-данные на основе реальных файлов

    $priceData = [];

    if (!empty($files)) {

        foreach ($files as $file) {

            $priceData[$file['name']] = "Образец данных из файла {$file['name']}\nРазмер: " . round($file['size']/1024, 2) . " KB\nФормат: PDF прайс-лист\n\nПример товаров:\n- Ламинат различных коллекций\n- Напольные покрытия\n- Цены от 500 до 3000 руб/м²";

        }

    } else {

        $priceData['demo.txt'] = "Демо-данные для запроса: $query\n\nПример товаров:\n- Ламинат кроно - от 800 руб/м²\n- Ламинат Quick Step - от 1200 руб/м²\n- Виниловая плитка - от 600 руб/м²";

    }

    

    // Анализируем запрос

    $result = $ai->analyzeQuery($query, $priceData);

    

    // Логируем запрос

    error_log("Query processed: $query by $aiProvider, files: " . count($files));

    

    $response = [

        'text' => $result['text'],

        'sources' => array_map(function($file) {

            return ['name' => $file['name'], 'path' => $file['path']];

        }, $files),

        'provider' => $aiProvider,

        'files_found' => count($files),

        'keywords' => $keywords

    ];

    

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    

} catch (Exception $e) {

    error_log('Process query error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

}

?>

