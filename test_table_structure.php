<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\AIProviderFactory;
use ResearcherAI\VectorCacheManager;

echo "🔍 Проверяем структуру таблицы vector_embeddings...\n\n";

try {
    // Получаем настройки
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);
    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;
    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);
    
    // Создаем VectorCacheManager
    $dbBaseDir = __DIR__ . '/db';
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    
    // Получаем SQLite PDO
    $reflection = new ReflectionClass($vectorCacheManager);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $sqlitePdo = $pdoProperty->getValue($vectorCacheManager);
    
    echo "📊 Текущая структура таблицы vector_embeddings:\n";
    try {
        $pragma = $sqlitePdo->query("PRAGMA table_info(vector_embeddings)");
        $columns = $pragma->fetchAll();
        
        if (!empty($columns)) {
            foreach ($columns as $column) {
                echo "   - {$column['name']}: {$column['type']} " . 
                     ($column['notnull'] ? "NOT NULL" : "NULL") . 
                     ($column['pk'] ? " PRIMARY KEY" : "") . "\n";
            }
        } else {
            echo "❌ Таблица не существует или пуста\n";
        }
    } catch (Exception $e) {
        echo "❌ Ошибка получения структуры: " . $e->getMessage() . "\n";
    }
    
    // Удаляем старую таблицу и создаем новую
    echo "\n🔄 Пересоздаем таблицу с правильной структурой...\n";
    try {
        $sqlitePdo->exec("DROP TABLE IF EXISTS vector_embeddings");
        echo "✅ Старая таблица удалена\n";
        
        $sql = "CREATE TABLE vector_embeddings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_path TEXT NOT NULL,
            chunk_text TEXT NOT NULL,
            embedding TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $sqlitePdo->exec($sql);
        echo "✅ Новая таблица создана\n";
        
        // Создаем индекс
        $indexSql = "CREATE INDEX idx_vector_file_path ON vector_embeddings(file_path)";
        $sqlitePdo->exec($indexSql);
        echo "✅ Индекс создан\n";
        
    } catch (Exception $e) {
        echo "❌ Ошибка пересоздания таблицы: " . $e->getMessage() . "\n";
    }
    
    // Проверяем новую структуру
    echo "\n📊 Новая структура таблицы:\n";
    try {
        $pragma = $sqlitePdo->query("PRAGMA table_info(vector_embeddings)");
        $columns = $pragma->fetchAll();
        
        foreach ($columns as $column) {
            echo "   - {$column['name']}: {$column['type']} " . 
                 ($column['notnull'] ? "NOT NULL" : "NULL") . 
                 ($column['pk'] ? " PRIMARY KEY" : "") . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Ошибка получения новой структуры: " . $e->getMessage() . "\n";
    }
    
    // Тестируем вставку
    echo "\n💾 Тестируем вставку в новую таблицу...\n";
    try {
        $testEmbedding = array_fill(0, 100, 0.1);
        $embeddingJson = json_encode($testEmbedding);
        
        $stmt = $sqlitePdo->prepare("INSERT INTO vector_embeddings (file_path, chunk_text, embedding) VALUES (?, ?, ?)");
        $result = $stmt->execute(["/test/structure.txt", "Тестовый чанк", $embeddingJson]);
        
        if ($result) {
            echo "✅ Вставка успешна!\n";
            
            $countStmt = $sqlitePdo->query("SELECT COUNT(*) as count FROM vector_embeddings");
            $count = $countStmt->fetch();
            echo "📊 Записей в таблице: " . $count['count'] . "\n";
        } else {
            echo "❌ Вставка не удалась\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Ошибка вставки: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Общая ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
