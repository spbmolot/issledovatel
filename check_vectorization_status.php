<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\VectorCacheManager;

echo " Проверяем статус векторизации...\n\n";

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
    
    // Инициализируем EmbeddingManager для поиска
    $vectorCacheManager->initializeEmbeddingManager($aiProvider);
    
    // Получаем SQLite PDO через рефлексию
    $reflection = new ReflectionClass($vectorCacheManager);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $sqlitePdo = $pdoProperty->getValue($vectorCacheManager);
    
    // Проверяем таблицу vector_embeddings
    echo " Статистика векторной базы данных:\n";
    
    // Общее количество записей
    $countStmt = $sqlitePdo->query("SELECT COUNT(*) as count FROM vector_embeddings");
    $count = $countStmt->fetch();
    echo "   Всего векторов: " . $count['count'] . "\n";
    
    if ($count['count'] > 0) {
        // Количество уникальных файлов
        $filesStmt = $sqlitePdo->query("SELECT COUNT(DISTINCT file_path) as files FROM vector_embeddings");
        $files = $filesStmt->fetch();
        echo "   Векторизованных файлов: " . $files['files'] . "\n";
        
        // Последние векторизированные файлы
        echo "\n Последние векторизированные файлы:\n";
        $recentStmt = $sqlitePdo->query("
            SELECT file_path, COUNT(*) as chunks, MAX(created_at) as last_update 
            FROM vector_embeddings 
            GROUP BY file_path 
            ORDER BY last_update DESC 
            LIMIT 10
        ");
        
        while ($row = $recentStmt->fetch()) {
            $fileName = basename($row['file_path']);
            echo "   {$fileName} ({$row['chunks']} чанков) - {$row['last_update']}\n";
        }
        
        // Статистика по типам файлов
        echo "\n Статистика по типам файлов:\n";
        $typeStmt = $sqlitePdo->query("
            SELECT 
                CASE 
                    WHEN file_path LIKE '%.xlsx' THEN 'Excel (.xlsx)'
                    WHEN file_path LIKE '%.xls' THEN 'Excel (.xls)'
                    WHEN file_path LIKE '%.pdf' THEN 'PDF'
                    WHEN file_path LIKE '%.doc%' THEN 'Word'
                    ELSE 'Другое'
                END as file_type,
                COUNT(DISTINCT file_path) as files,
                COUNT(*) as chunks
            FROM vector_embeddings 
            GROUP BY file_type
            ORDER BY files DESC
        ");
        
        while ($row = $typeStmt->fetch()) {
            echo "   {$row['file_type']}: {$row['files']} файлов, {$row['chunks']} чанков\n";
        }
        
    } else {
        echo " Векторы не найдены в базе данных\n";
    }
    
    // Проверяем таблицу processed_files
    echo "\n Статус обработки файлов:\n";
    try {
        $processedStmt = $sqlitePdo->query("SELECT COUNT(*) as count FROM processed_files");
        $processed = $processedStmt->fetch();
        echo "   Обработанных файлов: " . $processed['count'] . "\n";
        
        if ($processed['count'] > 0) {
            $statusStmt = $sqlitePdo->query("
                SELECT status, COUNT(*) as count 
                FROM processed_files 
                GROUP BY status
            ");
            while ($row = $statusStmt->fetch()) {
                echo "   {$row['status']}: {$row['count']} файлов\n";
            }
        }
    } catch (Exception $e) {
        echo "   Таблица processed_files не найдена\n";
    }
    
    // Тестируем поиск
    echo "\n Тестируем векторный поиск...\n";
    $searchResults = $vectorCacheManager->searchSimilar("ламинат EGGER", 3);
    
    if (!empty($searchResults)) {
        echo " Поиск работает! Найдено результатов: " . count($searchResults) . "\n";
        foreach ($searchResults as $i => $result) {
            $fileName = basename($result['file_path']);
            $snippet = substr($result['chunk_text'], 0, 50) . '...';
            echo "   " . ($i + 1) . ". {$fileName}: {$snippet}\n";
        }
    } else {
        echo " Поиск не вернул результатов\n";
    }
    
} catch (Exception $e) {
    echo " Ошибка: " . $e->getMessage() . "\n";
    echo " Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}

echo "\n Проверка завершена!\n";
