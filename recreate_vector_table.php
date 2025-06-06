<?php
require_once 'vendor/autoload.php';

use ResearcherAI\Logger;

echo "🔄 Пересоздаем таблицу vector_embeddings с новой структурой...\n";

try {
    // Подключаемся к базе данных
    $dbDir = __DIR__ . '/db';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    $dbPath = $dbDir . '/cache.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Подключение к базе данных успешно\n";

    // Удаляем старую таблицу
    echo "🗑️ Удаляем старую таблицу vector_embeddings...\n";
    $pdo->exec("DROP TABLE IF EXISTS vector_embeddings");

    // Создаем новую таблицу с правильной структурой
    echo "🏗️ Создаем новую таблицу vector_embeddings...\n";
    $sql = "CREATE TABLE vector_embeddings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_path TEXT NOT NULL,
        chunk_text TEXT NOT NULL,
        embedding TEXT NOT NULL,
        chunk_index INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);

    // Создаем индекс
    echo "📇 Создаем индекс для быстрого поиска...\n";
    $indexSql = "CREATE INDEX idx_vector_file_path ON vector_embeddings(file_path)";
    $pdo->exec($indexSql);

    echo "✅ Таблица vector_embeddings успешно пересоздана!\n";
    echo "🎯 Теперь можно запускать векторизацию\n";

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
