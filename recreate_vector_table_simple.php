
<?php

require_once 'vendor/autoload.php';



echo "🔄 Пересоздаем таблицу vector_embeddings с простой структурой...\n";



$dbDir = __DIR__ . '/db';

$dbPath = $dbDir . '/cache.sqlite';

$pdo = new PDO('sqlite:' . $dbPath);



// Удаляем старую таблицу

echo "🗑️ Удаляем старую таблицу...\n";

$pdo->exec("DROP TABLE IF EXISTS vector_embeddings");



// Создаем новую таблицу БЕЗ embedding_model

echo "🏗️ Создаем новую таблицу...\n";

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

$pdo->exec("CREATE INDEX idx_vector_file_path ON vector_embeddings(file_path)");



echo "✅ Таблица пересоздана без проблемной колонки!\n";

