
<?php

// Принудительно устанавливаем UTF-8

mb_internal_encoding('UTF-8');

mb_http_output('UTF-8');



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../classes/AIProvider.php';

require_once __DIR__ . '/../classes/YandexDiskClient.php';

require_once __DIR__ . '/../classes/FileParser.php'; 



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

    $fileParser = new \ResearcherAI\FileParser(); 
    
    // Извлекаем ключевые слова

    $keywords = $ai->extractKeywords($query);

    

    // Ищем файлы на Яндекс.Диске

    $folder = $settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';

    $allFoundFiles = []; 
    
    try {

        $allFoundFiles = $yandexDisk->listFiles($folder);

        error_log("Found " . count($allFoundFiles) . " files in folder: $folder");
        
        // $files = array_slice($allFiles, 0, 3); // Убираем ограничение на 3 файла

    } catch (Exception $e) {

        error_log('Yandex Disk error listing files: ' . $e->getMessage());

    }
    
    $realPriceData = [];

    $processedFilesForResponse = []; // Для хранения информации о реально обработанных файлах

    $maxTotalCharsForAI = 50000; // Примерный лимит символов для контекста AI (можно настроить)

    $currentCharCount = 0;

    if (!empty($allFoundFiles)) {

        error_log("Starting to process " . count($allFoundFiles) . " files.");

        foreach ($allFoundFiles as $fileMeta) {

            if ($currentCharCount >= $maxTotalCharsForAI) {

                error_log("Reached max character limit for AI context ($maxTotalCharsForAI chars). Skipping remaining files.");

                break;

            }
            
            $fileName = $fileMeta['name'];

            $filePath = $fileMeta['path'];

            error_log("Processing file: $fileName (Path: $filePath)");

            try {

                $fileContent = $yandexDisk->downloadFile($filePath);

                if ($fileContent === null) {

                    error_log("Failed to download file: $fileName. Skipping.");

                    continue;

                }

                // Опционально: $fileParser->validateFile($fileContent, $fileName);

                $parsedRows = $fileParser->parse($fileContent, $fileName);
                
                if (empty($parsedRows)) {

                    error_log("File $fileName was parsed into empty data. Skipping.");

                    continue;

                }

                $fileTextContent = "";

                foreach ($parsedRows as $row) {

                    // Очищаем каждую ячейку перед объединением

                    $cleanedRow = array_map(function($cell) {

                        return trim(str_replace(["\r", "\n"], ' ', (string)$cell));

                    }, $row);

                    $fileTextContent .= implode("\t", array_filter($cleanedRow)) . "\n";

                }

                $fileTextContent = trim($fileTextContent);

                if (!empty($fileTextContent)) {

                    $contentLength = mb_strlen($fileTextContent, 'UTF-8');

                    if (($currentCharCount + $contentLength) > $maxTotalCharsForAI) {

                        $charsToTake = $maxTotalCharsForAI - $currentCharCount;

                        $fileTextContent = mb_substr($fileTextContent, 0, $charsToTake, 'UTF-8') . "... (содержимое файла было усечено)";

                        $currentCharCount = $maxTotalCharsForAI; // Помечаем, что лимит достигнут

                    } else {

                        $currentCharCount += $contentLength;

                    }

                    $realPriceData[$fileName] = $fileTextContent;

                    $processedFilesForResponse[] = $fileMeta; 

                    error_log("Successfully processed and added data from: $fileName. Total AI context chars: $currentCharCount");

                } else {

                    error_log("No text content extracted from $fileName after parsing. Skipping.");

                }

            } catch (Exception $e) {

                error_log("Error processing file $fileName: " . $e->getMessage() . " Skipping.");

            }

        }

    } else {

        error_log("No files found in Yandex Disk folder '$folder' or an error occurred while listing files.");

    }
    
    // Если после обработки всех файлов нет данных, используем демо-данные

    if (empty($realPriceData)) {

        error_log("No data could be extracted from any files. Using fallback demo data for AI query.");

        $realPriceData['demo_fallback.txt'] = "Не удалось извлечь данные из прайс-листов. Информация для запроса '$query':\n- Товар А: 100 руб.\n- Товар Б: 200 руб.";

        // В этом случае в sources можно ничего не добавлять или добавить информацию о fallback

        $processedFilesForResponse = [['name' => 'Файлы не найдены или не обработаны', 'path' => '']];

    }
    
    // Анализируем запрос

    $result = $ai->analyzeQuery($query, $realPriceData); // Передаем реальные или демо-данные
    
    // Логируем запрос

    error_log("Query processed: $query by $aiProvider, files used for AI context: " . count($realPriceData) . ", files listed in response: " . count($processedFilesForResponse));
    
    $response = [

        'text' => $result['text'],

        'sources' => array_map(function($file) {

            return ['name' => $file['name'], 'path' => $file['path']];

        }, $processedFilesForResponse), // Используем список успешно обработанных файлов

        'provider' => $aiProvider,

        'files_found' => count($realPriceData),

        'keywords' => $keywords

    ];

    

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    

} catch (Exception $e) {

    error_log('Process query error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

}

?>
