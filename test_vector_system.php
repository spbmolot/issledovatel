
<?php

require_once 'vendor/autoload.php';

require_once 'config/database.php';



use ResearcherAI\Logger;

use ResearcherAI\AIProviderFactory;

use ResearcherAI\YandexDiskClient;

use ResearcherAI\VectorPriceAnalyzer;

use ResearcherAI\CacheManager;



echo "🧪 Тестируем векторную систему...\n\n";



try {

    // Получаем настройки

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    

    if (!$settings) {

        throw new Exception('Настройки не найдены');

    }

    

    echo "✅ Настройки загружены\n";

    

    // Создаем компоненты

    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);

    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;

    

    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);

    echo "✅ AI Provider ({$settings['ai_provider']}) создан\n";

    

    $yandexDiskClient = new YandexDiskClient($settings['yandex_token']);

    echo "✅ YandexDiskClient создан\n";

    

    $dbBaseDir = __DIR__ . '/db';

    $cacheManager = new CacheManager($dbBaseDir);

    echo "✅ CacheManager создан\n";

    

    // Создаем VectorPriceAnalyzer

    $vectorAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexDiskClient, $cacheManager);

    echo "✅ VectorPriceAnalyzer создан\n\n";

    

    // Получаем статистику векторизации

    $stats = $vectorAnalyzer->getVectorSearchStats();

    echo "📊 Статистика векторной системы:\n";

    echo "   Векторизованных файлов: {$stats['vectorized_files_count']}\n";

    echo "   Векторный поиск включен: " . ($stats['vector_search_enabled'] ? 'Да' : 'Нет') . "\n";

    

    if ($stats['vectorized_files_count'] > 0) {

        echo "   Файлы: " . implode(', ', array_slice($stats['vectorized_files'], 0, 3));

        if (count($stats['vectorized_files']) > 3) {

            echo " и еще " . (count($stats['vectorized_files']) - 3) . "...";

        }

        echo "\n";

    }

    

    echo "\n🎯 Тестируем поиск с запросом 'ламинат'...\n";

    

    // Тестовый запрос

    $testQuery = 'ламинат';

    $result = $vectorAnalyzer->processQuery($testQuery, $settings['yandex_folder']);

    

    echo "✅ Запрос обработан!\n";

    echo "   Метод поиска: " . ($result['search_method'] ?? 'traditional') . "\n";

    echo "   Время обработки: " . round($result['processing_time'], 2) . " сек\n";

    echo "   Найдено источников: " . count($result['sources']) . "\n";

    

    if (!empty($result['sources'])) {

        echo "   Источники:\n";

        foreach (array_slice($result['sources'], 0, 3) as $source) {

            echo "     - {$source['name']}\n";

            if (isset($source['similarity'])) {

                echo "       Релевантность: " . $source['similarity'] . "\n";

            }

        }

    }

    

    echo "\n🎉 Тест завершен успешно!\n";

    

} catch (Exception $e) {

    echo "❌ Ошибка тестирования: " . $e->getMessage() . "\n";

    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";

}

