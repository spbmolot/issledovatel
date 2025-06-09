<?php
// Проверка структуры таблицы vector_embeddings
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use ResearcherAI\VectorCacheManager;

try {
    $vectorCache = new VectorCacheManager(__DIR__);
    $pdo = $vectorCache->getPDO();
    
    // Проверим структуру таблицы
    $stmt = $pdo->query("PRAGMA table_info(vector_embeddings)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Попробуем получить одну запись для примера
    $stmt = $pdo->query("SELECT * FROM vector_embeddings LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'table_structure' => $columns,
        'sample_record' => $sample,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
