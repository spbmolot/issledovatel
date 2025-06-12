<?php
/**
 * Тест улучшенной векторизации на одном файле
 */

require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\Logger;
use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorCacheManager;

echo "🧪 ТЕСТ УЛУЧШЕННОЙ ВЕКТОРИЗАЦИИ\n";
echo "==============================\n\n";

try {
    // Получаем настройки (MySQL)
    $settingsRow = $pdo->query("SELECT * FROM researcher_settings LIMIT 1")->fetch();
    if (!$settingsRow) {
        throw new \Exception("Настройки не найдены в таблице researcher_settings");
    }
    $settings = $settingsRow;

    // Создаем AI провайдер
    $aiProvider = AIProviderFactory::create(
        $settings['ai_provider'] ?? 'deepseek',
        (isset($settings['ai_provider']) && $settings['ai_provider'] === 'openai') ? $settings['openai_key'] : ($settings['deepseek_key'] ?? $settings['openai_key'])
    );

    echo "✅ AI Provider: " . get_class($aiProvider) . "\n";
    
    // Создаем VectorCacheManager
    $dbBaseDir = __DIR__ . '/db';
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    $vectorCacheManager->initializeEmbeddingManager($aiProvider);
    
    echo "✅ VectorCacheManager инициализирован\n\n";

    // Тестовый сырой текст
    $testRawText = "Товар1 | CronaFloor | CF001 | 1200 руб/м2 | Ламинат 8мм
Товар2 | Tarkett | TK002 | 950 руб/м2 | LVT покрытие
Товар3 | Quick Step | QS003 | 1500 руб/м2 | Паркетная доска";
    
    $testFilePath = "/test/enhanced_vectorization_test.xlsx";
    
    echo "📄 Тестируем на сыром тексте:\n";
    echo substr($testRawText, 0, 100) . "...\n\n";
    
    // Тестируем новый метод
    echo "🔄 Запускаем storeVectorDataEnhanced...\n";
    $result = $vectorCacheManager->storeVectorDataEnhanced($testFilePath, $testRawText, $aiProvider);
    
    if ($result) {
        echo "✅ Тест успешен!\n";
    } else {
        echo "❌ Тест не прошел\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
