
<?php

require_once 'vendor/autoload.php';

require_once 'config/database.php';



use ResearcherAI\Logger;

use ResearcherAI\AIProviderFactory;

use ResearcherAI\VectorCacheManager;



echo "🔍 DEBUG: Тестируем storeVectorData() пошагово...\n\n";



try {

    // Получаем настройки

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    

    // Создаем AI провайдер

    $aiProvider = AIProviderFactory::create(

        $settings['ai_provider'] ?? 'deepseek',

        $settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']

    );

    

    // Создаем VectorCacheManager

    $dbBaseDir = __DIR__ . '/db';

    $vectorCacheManager = new VectorCacheManager($dbBaseDir);

    

    echo "✅ VectorCacheManager создан\n";

    

    // Инициализируем EmbeddingManager

    $vectorCacheManager->initializeEmbeddingManager($aiProvider);

    echo "✅ EmbeddingManager инициализирован\n";

    

    // Проверяем, что EmbeddingManager работает

    if (!$vectorCacheManager->isEmbeddingManagerInitialized()) {

        echo "❌ EmbeddingManager НЕ инициализирован!\n";

        exit(1);

    }

    echo "✅ EmbeddingManager проверен\n";

    

    // Тестируем получение embedding

    echo "\n🧠 Тестируем получение embedding...\n";

    $testText = "тестовый текст для векторизации";

    $embedding = $vectorCacheManager->getQueryEmbedding($testText);

    

    if ($embedding === null) {

        echo "❌ getQueryEmbedding() вернул null!\n";

        echo "Проверяем AI провайдер...\n";

        

        // Тестируем AI провайдер напрямую

        try {

            $directEmbedding = $aiProvider->getEmbedding($testText);

            echo "Прямой вызов AI провайдера: " . ($directEmbedding ? "SUCCESS" : "FAILED") . "\n";

            if ($directEmbedding) {

                echo "Размер вектора: " . count($directEmbedding) . "\n";

                echo "Первые 5 значений: " . implode(', ', array_slice($directEmbedding, 0, 5)) . "\n";

            }

        } catch (Exception $e) {

            echo "❌ Ошибка AI провайдера: " . $e->getMessage() . "\n";

        }

        exit(1);

    }

    

    echo "✅ Embedding получен, размер: " . count($embedding) . "\n";

    echo "Первые 5 значений: " . implode(', ', array_slice($embedding, 0, 5)) . "\n";

    

    // Тестируем JSON encoding

    echo "\n📦 Тестируем JSON encoding...\n";

    $embeddingJson = json_encode($embedding);

    if ($embeddingJson === false) {

        echo "❌ JSON encoding провалился!\n";

        echo "JSON error: " . json_last_error_msg() . "\n";

        exit(1);

    }

    echo "✅ JSON encoding успешен, размер: " . strlen($embeddingJson) . " символов\n";

    

    // Тестируем SQLite подключение

    echo "\n🗄️ Тестируем SQLite подключение...\n";

    $reflection = new ReflectionClass($vectorCacheManager);

    $pdoProperty = $reflection->getProperty('pdo');

    $pdoProperty->setAccessible(true);

    $sqlitePdo = $pdoProperty->getValue($vectorCacheManager);

    

    if (!$sqlitePdo) {

        echo "❌ SQLite PDO не инициализирован!\n";

        exit(1);

    }

    echo "✅ SQLite PDO подключен\n";

    

    // Проверяем таблицу vector_embeddings

    echo "\n📋 Проверяем таблицу vector_embeddings...\n";

    try {

        $stmt = $sqlitePdo->query("SELECT COUNT(*) as count FROM vector_embeddings");

        $count = $stmt->fetch()['count'];

        echo "✅ Таблица существует, записей: {$count}\n";

    } catch (Exception $e) {

        echo "❌ Ошибка с таблицей: " . $e->getMessage() . "\n";

        exit(1);

    }

    

    // Тестируем SQL INSERT

    echo "\n💾 Тестируем SQL INSERT...\n";

    try {

        $stmt = $sqlitePdo->prepare("INSERT INTO vector_embeddings (file_path, chunk_text, embedding, chunk_index) VALUES (?, ?, ?, ?)");

        echo "✅ SQL statement подготовлен\n";

        

        $testFilePath = "/test/debug.txt";

        $testChunkText = "тестовый чанк для debug";

        $testChunkIndex = 0;

        

        $result = $stmt->execute([$testFilePath, $testChunkText, $embeddingJson, $testChunkIndex]);

        

        if ($result) {

            echo "✅ SQL INSERT выполнен успешно!\n";

            echo "Inserted ID: " . $sqlitePdo->lastInsertId() . "\n";

            

            // Удаляем тестовую запись

            $sqlitePdo->exec("DELETE FROM vector_embeddings WHERE file_path = '/test/debug.txt'");

            echo "✅ Тестовая запись удалена\n";

        } else {

            echo "❌ SQL INSERT провалился!\n";

            $errorInfo = $stmt->errorInfo();

            echo "SQL Error: " . $errorInfo[2] . "\n";

        }

    } catch (Exception $e) {

        echo "❌ Исключение в SQL INSERT: " . $e->getMessage() . "\n";

        exit(1);

    }

    

    // Финальный тест - полный storeVectorData

    echo "\n🔧 Финальный тест storeVectorData()...\n";

    $testChunks = ["Тестовый чанк 1", "Тестовый чанк 2"];

    $result = $vectorCacheManager->storeVectorData("/test/final.txt", $testChunks);

    

    if ($result) {

        echo "✅ storeVectorData() работает!\n";

        // Удаляем тестовые записи

        $sqlitePdo->exec("DELETE FROM vector_embeddings WHERE file_path = '/test/final.txt'");

    } else {

        echo "❌ storeVectorData() провалился даже с тестовыми данными!\n";

    }

    

} catch (Exception $e) {

    echo "❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";

    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";

}



echo "\n🏁 Диагностика завершена!\n";

?>

