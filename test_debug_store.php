<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\AIProviderFactory;
use ResearcherAI\VectorCacheManager;

echo "🔧 Детальная отладка storeVectorData...\n\n";

try {
    // Получаем настройки
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);
    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;
    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);
    
    echo "🤖 AI Provider: " . get_class($aiProvider) . "\n";
    
    // Создаем VectorCacheManager
    $dbBaseDir = __DIR__ . '/db';
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    $vectorCacheManager->initializeEmbeddingManager($aiProvider);
    
    echo "✅ Компоненты готовы\n\n";
    
    // Получаем доступ к приватным свойствам через рефлексию
    $reflection = new ReflectionClass($vectorCacheManager);
    
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $sqlitePdo = $pdoProperty->getValue($vectorCacheManager);
    
    $embeddingManagerProperty = $reflection->getProperty('embeddingManager');
    $embeddingManagerProperty->setAccessible(true);
    $embeddingManager = $embeddingManagerProperty->getValue($vectorCacheManager);
    
    echo "🔍 Отладка embeddingManager:\n";
    if ($embeddingManager === null) {
        echo "❌ embeddingManager равен null\n";
        exit(1);
    } else {
        echo "✅ embeddingManager инициализирован: " . get_class($embeddingManager) . "\n";
    }
    
    // Тестируем embedding напрямую
    $testText = "Тестовый чанк";
    echo "\n🧠 Тестируем embedding для: '{$testText}'\n";
    
    try {
        $embedding = $embeddingManager->getEmbedding($testText);
        if ($embedding && is_array($embedding)) {
            echo "✅ Embedding получен, размер: " . count($embedding) . "\n";
        } else {
            echo "❌ Embedding не получен или пустой\n";
            var_dump($embedding);
            exit(1);
        }
    } catch (Exception $e) {
        echo "❌ Ошибка получения embedding: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Тестируем сохранение вручную
    echo "\n💾 Тестируем ручное сохранение в базу...\n";
    try {
        $testPath = "/test/debug.txt";
        $embeddingJson = json_encode($embedding);
        
        echo "📝 Подготавливаем SQL запрос...\n";
        $stmt = $sqlitePdo->prepare("INSERT INTO vector_embeddings (file_path, chunk_text, embedding) VALUES (?, ?, ?)");
        
        echo "🎯 Выполняем запрос с данными:\n";
        echo "   - file_path: {$testPath}\n";
        echo "   - chunk_text: {$testText}\n";
        echo "   - embedding: [массив " . count($embedding) . " элементов]\n";
        
        $result = $stmt->execute([$testPath, $testText, $embeddingJson]);
        
        if ($result) {
            echo "✅ Прямое сохранение успешно!\n";
            
            // Проверяем результат
            $countStmt = $sqlitePdo->query("SELECT COUNT(*) as count FROM vector_embeddings");
            $count = $countStmt->fetch();
            echo "📊 Записей в таблице: " . $count['count'] . "\n";
        } else {
            echo "❌ Прямое сохранение не удалось\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Ошибка прямого сохранения: " . $e->getMessage() . "\n";
        echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
    }
    
    // Теперь тестируем через storeVectorData
    echo "\n🔄 Тестируем через storeVectorData...\n";
    $testChunks = array("Другой тестовый чанк");
    $testPath2 = "/test/method.txt";
    
    $result = $vectorCacheManager->storeVectorData($testPath2, $testChunks);
    
    if ($result) {
        echo "✅ storeVectorData успешно!\n";
    } else {
        echo "❌ storeVectorData вернул false\n";
    }
    
    // Финальная проверка таблицы
    echo "\n📊 Финальная проверка таблицы:\n";
    $countStmt = $sqlitePdo->query("SELECT COUNT(*) as count FROM vector_embeddings");
    $count = $countStmt->fetch();
    echo "Всего записей: " . $count['count'] . "\n";
    
    if ($count['count'] > 0) {
        $selectStmt = $sqlitePdo->query("SELECT file_path, chunk_text FROM vector_embeddings");
        while ($row = $selectStmt->fetch()) {
            echo "   📄 " . $row['file_path'] . ": " . $row['chunk_text'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Общая ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
