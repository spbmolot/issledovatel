<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorPriceAnalyzer;
use ResearcherAI\CacheManager;

echo "🧪 Тестируем векторизацию одного файла (исправленная версия)...\n\n";

try {
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);
    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;
    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);
    $yandexDiskClient = new YandexDiskClient($settings['yandex_token']);
    $dbBaseDir = __DIR__ . '/db';
    $cacheManager = new CacheManager($dbBaseDir);
    $vectorAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexDiskClient, $cacheManager);
    
    echo "✅ Компоненты готовы\n";
    
    // Получаем VectorCacheManager через публичный доступ или метод
    if (method_exists($vectorAnalyzer, 'getVectorCacheManager')) {
        $vectorCacheManager = $vectorAnalyzer->getVectorCacheManager();
    } else {
        $vectorCacheManager = $vectorAnalyzer->vectorCacheManager;
    }
    
    // Тестовые данные для векторизации
    $testPath = "/2 АКТУАЛЬНЫЕ ПРАЙСЫ/test.xlsx";
    $testChunks = array(
        "Напольные покрытия - ламинат, линолеум, паркет",
        "Цена за м2: 1500 рублей, размер 200x20 см",
        "Производитель: EGGER, коллекция NEW 2023"
    );
    
    echo "🔄 Тестируем векторизацию тестовых данных...\n";
    
    $result = $vectorCacheManager->storeVectorData($testPath, $testChunks);
    
    if ($result) {
        echo "✅ Тестовые данные векторизированы!\n";
    } else {
        echo "❌ Ошибка векторизации\n";
    }
    
    // Показываем статистику
    $stats = $vectorAnalyzer->getVectorSearchStats();
    echo "\n📊 Статистика векторного поиска:\n";
    echo "   - Векторизованных файлов: " . $stats['vectorized_files_count'] . "\n";
    
    // Тестируем поиск
    echo "\n🔍 Тестируем поиск по запросу 'ламинат EGGER':\n";
    $searchResult = $vectorCacheManager->findSimilarContent("ламинат EGGER", 2);
    
    if (!empty($searchResult)) {
        foreach ($searchResult as $i => $result) {
            echo "   📄 Результат " . ($i + 1) . ": " . substr($result['content'], 0, 100) . "...\n";
        }
    } else {
        echo "   ❌ Результаты поиска не найдены\n";
    }
    
    echo "\n🎉 Тест завершен!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
