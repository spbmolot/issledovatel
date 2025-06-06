<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\AIProviderFactory;
use ResearcherAI\VectorCacheManager;

echo "🧪 Простой тест векторизации...\n\n";

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
    echo "📂 Создаем VectorCacheManager...\n";
    $dbBaseDir = __DIR__ . '/db';
    
    try {
        $vectorCacheManager = new VectorCacheManager($dbBaseDir);
        echo "✅ VectorCacheManager создан\n";
    } catch (Exception $e) {
        echo "❌ Ошибка создания VectorCacheManager: " . $e->getMessage() . "\n";
        echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
        exit(1);
    }
    
    // Инициализируем EmbeddingManager
    echo "🧠 Инициализируем EmbeddingManager...\n";
    try {
        $vectorCacheManager->initializeEmbeddingManager($aiProvider);
        echo "✅ EmbeddingManager инициализирован\n";
    } catch (Exception $e) {
        echo "❌ Ошибка инициализации EmbeddingManager: " . $e->getMessage() . "\n";
        echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
        exit(1);
    }
    
    // Тестируем сохранение данных
    echo "💾 Тестируем сохранение...\n";
    $testPath = "/test/simple.txt";
    $testChunks = array("Тестовый чанк");
    
    try {
        $result = $vectorCacheManager->storeVectorData($testPath, $testChunks);
        if ($result) {
            echo "✅ Данные сохранены успешно!\n";
        } else {
            echo "❌ Метод вернул false\n";
        }
    } catch (Exception $e) {
        echo "❌ Исключение при сохранении: " . $e->getMessage() . "\n";
        echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
        echo "🔍 Стек вызовов:\n" . $e->getTraceAsString() . "\n";
    }
    
    // Проверяем таблицу напрямую
    echo "\n🔍 Проверяем таблицу vector_embeddings напрямую...\n";
    try {
        // Сначала проверим, существует ли таблица
        $checkTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='vector_embeddings'");
        $tableExists = $checkTable->fetch();
        
        if ($tableExists) {
            echo "✅ Таблица vector_embeddings существует\n";
            
            // Считаем записи
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM vector_embeddings");
            $count = $countStmt->fetch();
            echo "📊 Количество записей: " . $count['count'] . "\n";
            
            // Показываем последние записи
            if ($count['count'] > 0) {
                $selectStmt = $pdo->query("SELECT file_path, substr(chunk_text, 1, 50) as chunk_preview FROM vector_embeddings ORDER BY id DESC LIMIT 3");
                while ($row = $selectStmt->fetch()) {
                    echo "   📄 " . $row['file_path'] . ": " . $row['chunk_preview'] . "...\n";
                }
            }
        } else {
            echo "❌ Таблица vector_embeddings НЕ существует!\n";
        }
    } catch (Exception $e) {
        echo "❌ Ошибка проверки таблицы: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Общая ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
