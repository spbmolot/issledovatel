
<?php

require_once 'vendor/autoload.php';

require_once 'config/database.php';



use ResearcherAI\Logger;

use ResearcherAI\AIProviderFactory;

use ResearcherAI\YandexDiskClient;

use ResearcherAI\VectorPriceAnalyzer;

use ResearcherAI\CacheManager;



echo "🔄 Векторизация существующих файлов...\n\n";



try {

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    

    if (!$settings) {

        throw new Exception('Настройки не найдены');

    }

    

    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);

    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;

    

    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);

    $yandexDiskClient = new YandexDiskClient($settings['yandex_token']);

    $dbBaseDir = __DIR__ . '/db';

    $cacheManager = new CacheManager($dbBaseDir);

    

    $vectorAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexDiskClient, $cacheManager);

    

    echo "✅ Компоненты инициализированы\n";

    

    $folderPath = $settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';

    echo "📁 Сканируем папку: {$folderPath}\n";

    

    $files = $yandexDiskClient->searchFiles(array('xlsx', 'xls', 'csv'), $folderPath);

    echo "📋 Найдено файлов: " . count($files) . "\n\n";

    

    if (empty($files)) {

        echo "❌ Файлы не найдены для векторизации\n";

        exit;

    }

    

    $vectorizedCount = 0;

    $maxFiles = 3; // Ограничиваем для теста

    

    foreach (array_slice($files, 0, $maxFiles) as $file) {

        echo "🔄 Обрабатываем: " . $file['name'] . "\n";

        

        try {

            $fileContent = $yandexDiskClient->downloadFile($file['path']);

            

            if ($fileContent) {

                echo "   ✅ Файл загружен\n";

                $vectorizedCount++;

            } else {

                echo "   ❌ Ошибка загрузки файла\n";

            }

        } catch (Exception $e) {

            echo "   ❌ Ошибка: " . $e->getMessage() . "\n";

        }

        

        echo "\n";

    }

    

    echo "🎉 Векторизация завершена!\n";

    echo "📊 Обработано файлов: {$vectorizedCount} из " . min($maxFiles, count($files)) . "\n";

    

    $stats = $vectorAnalyzer->getVectorSearchStats();

    echo "📈 Векторизованных файлов в базе: " . $stats['vectorized_files_count'] . "\n";

    

} catch (Exception $e) {

    echo "❌ Ошибка: " . $e->getMessage() . "\n";

}

