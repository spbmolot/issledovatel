
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

    // Загружаем настройки

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    

    if (!$settings) {

        throw new Exception('Настройки не найдены');

    }

    echo "✅ Настройки загружены\n";

    

    // Создаем AI Provider

    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);

    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;

    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);

    echo "✅ AI Provider ({$settings['ai_provider']}) создан\n";

    

    // Создаем YandexDiskClient

    $yandexDiskClient = new YandexDiskClient($settings['yandex_token']);

    echo "✅ YandexDiskClient создан\n";

    

    // Создаем CacheManager

    $dbBaseDir = __DIR__ . '/db';

    $cacheManager = new CacheManager($dbBaseDir);

    echo "✅ CacheManager создан\n";

    

    // Создаем VectorPriceAnalyzer

    $vectorAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexDiskClient, $cacheManager);

    echo "✅ VectorPriceAnalyzer создан успешно!\n";

    

    // Получаем статистику векторного поиска

    $stats = $vectorAnalyzer->getVectorSearchStats();

    echo "📊 Статистика векторного поиска:\n";

    echo "   - Векторизованных файлов: " . $stats['vectorized_files_count'] . "\n";

    echo "   - Векторный поиск включен: " . ($stats['vector_search_enabled'] ? 'Да' : 'Нет') . "\n";

    

    if (isset($stats['vectorized_files']) && !empty($stats['vectorized_files'])) {

        echo "   - Файлы:\n";

        foreach (array_slice($stats['vectorized_files'], 0, 5) as $file) {

            echo "     * {$file}\n";

        }

    }

    

    echo "\n🎉 Тест завершен успешно! Класс VectorPriceAnalyzer работает.\n";

    

} catch (Exception $e) {

    echo "❌ Ошибка: " . $e->getMessage() . "\n";

    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";

}

