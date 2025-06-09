<?php
// Тест точности поиска CronaFloor NANO
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use ResearcherAI\VectorCacheManager;
use ResearcherAI\Logger;

try {
    $vectorCache = new VectorCacheManager();
    
    // 1. Проверим общее количество чанков
    $pdo = $vectorCache->getPDO();
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM vector_embeddings");
    $totalChunks = $stmt->fetch()['total'];
    
    // 2. Поищем точные совпадения по "CronaFloor" или "NANO"
    $stmt = $pdo->prepare("SELECT file_name, chunk_text FROM vector_embeddings WHERE chunk_text LIKE ? OR chunk_text LIKE ?");
    $stmt->execute(['%CronaFloor%', '%NANO%']);
    $exactMatches = $stmt->fetchAll();
    
    // 3. Поищем по "Crona" (может быть сокращение)
    $stmt = $pdo->prepare("SELECT file_name, chunk_text FROM vector_embeddings WHERE chunk_text LIKE ?");
    $stmt->execute(['%Crona%']);
    $cronaMatches = $stmt->fetchAll();
    
    // 4. Поищем все уникальные файлы
    $stmt = $pdo->query("SELECT DISTINCT file_name FROM vector_embeddings ORDER BY file_name");
    $allFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 5. Поищем по ключевым словам "floor", "покрытие"
    $stmt = $pdo->prepare("SELECT file_name, chunk_text FROM vector_embeddings WHERE chunk_text LIKE ? OR chunk_text LIKE ?");
    $stmt->execute(['%floor%', '%покрытие%']);
    $floorMatches = $stmt->fetchAll();
    
    $result = [
        'total_chunks' => $totalChunks,
        'total_files' => count($allFiles),
        'exact_matches' => count($exactMatches),
        'crona_matches' => count($cronaMatches),
        'floor_matches' => count($floorMatches),
        'sample_exact_matches' => array_slice($exactMatches, 0, 3),
        'sample_crona_matches' => array_slice($cronaMatches, 0, 3),
        'sample_floor_matches' => array_slice($floorMatches, 0, 5),
        'all_files' => $allFiles
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
