<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorCacheManager;

echo "🔍 Отладка векторизации...\n\n";

try {
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);
    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;
    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);
    
    echo "🤖 AI Provider: " . get_class($aiProvider) . "\n";
    
    // Тестируем embedding напрямую
    echo "\n🧠 Тестируем embedding напрямую:\n";
    $testText = "Тестовый текст для векторизации";
    $embedding = $aiProvider->getEmbedding($testText);
    
    if ($embedding && is_array($embedding)) {
        echo "✅ Embedding получен, размер: " . count($embedding) . " элементов\n";
        echo "📊 Первые 5 значений: " . implode(', ', array_slice($embedding, 0, 5)) . "\n";
    } else {
        echo "❌ Ошибка получения embedding\n";
        var_dump($embedding);
    }
    
    // Тестируем VectorCacheManager
    echo "\n🗃️ Тестируем VectorCacheManager:\n";
    $dbBaseDir = __DIR__ . '/db';
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    $vectorCacheManager->initializeEmbeddingManager($aiProvider);
    
    $testPath = "/test/debug.txt";
    $testChunks = array(
        "Первый тестовый чанк",
        "Второй тестовый чанк"
    );
    
    echo "💾 Сохраняем векторные данные...\n";
    $result = $vectorCacheManager->storeVectorData($testPath, $testChunks);
    
    if ($result) {
        echo "✅ Данные сохранены успешно\n";
    } else {
        echo "❌ Ошибка сохранения данных\n";
    }
    
    // Проверяем содержимое базы данных
    echo "\n🔍 Проверяем содержимое базы vector_embeddings:\n";
    $vectorizedFiles = $vectorCacheManager->getVectorizedFilesPaths();
    echo "📁 Векторизованных файлов: " . count($vectorizedFiles) . "\n";
    
    if (!empty($vectorizedFiles)) {
        foreach ($vectorizedFiles as $file) {
            echo "   - {$file}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
