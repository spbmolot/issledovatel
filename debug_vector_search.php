<?php
require_once 'vendor/autoload.php';

use ResearcherAI\VectorCacheManager;
use ResearcherAI\EmbeddingManager;
use ResearcherAI\AIProviderFactory;
use ResearcherAI\Logger;

// Тестовый запрос
$query = "Линолеум Profi Premium Flamenco 4";

echo "🔍 DEBUG: Анализ векторного поиска для запроса: '$query'\n\n";

try {
    // Инициализация
    $dbBaseDir = __DIR__ . '/db';
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    
    // Создаем AI провайдер (используем DeepSeek)
    $aiProvider = AIProviderFactory::create('deepseek', 'fake-key-for-debug');
    $vectorCacheManager->initializeEmbeddingManager($aiProvider);
    
    echo "✅ VectorCacheManager инициализирован\n\n";
    
    // Получаем embedding запроса
    $queryEmbedding = $vectorCacheManager->getQueryEmbedding($query);
    echo "📊 Embedding запроса:\n";
    echo "- Размер: " . count($queryEmbedding) . " измерений\n";
    echo "- Первые 10 значений: " . implode(', ', array_slice($queryEmbedding, 0, 10)) . "\n\n";
    
    // Выполняем поиск
    $results = $vectorCacheManager->findSimilarContent($query, 10);
    
    echo "🎯 РЕЗУЛЬТАТЫ ПОИСКА:\n";
    echo "Найдено чанков: " . count($results) . "\n\n";
    
    foreach ($results as $i => $result) {
        echo "--- РЕЗУЛЬТАТ #" . ($i + 1) . " ---\n";
        echo "📁 Файл: " . $result['file_path'] . "\n";
        echo "🎯 Сходство: " . round($result['similarity'], 4) . "\n";
        echo "📝 Содержимое (первые 300 символов):\n";
        echo substr($result['content'], 0, 300) . "...\n";
        
        // Проверяем наличие ключевых слов
        $content_lower = mb_strtolower($result['content']);
        $keywords = ['profi', 'premium', 'flamenco', 'линолеум'];
        $found_keywords = array();
        foreach ($keywords as $keyword) {
            if (mb_strpos($content_lower, $keyword) !== false) {
                $found_keywords[] = $keyword;
            }
        }
        echo "🔑 Найденные ключевые слова: " . implode(', ', $found_keywords) . "\n\n";
    }
    
    // Статистика БД
    $stats = $vectorCacheManager->getVectorizationStats();
    echo "📈 СТАТИСТИКА БАЗЫ ДАННЫХ:\n";
    echo "Векторизировано файлов: " . $stats['vectorized_files_count'] . "\n\n";
    
    // Проверим прямой поиск по тексту в БД
    echo "🔍 ПРЯМОЙ ПОИСК ПО ТЕКСТУ В БАЗЕ:\n";
    $pdo = new PDO('sqlite:' . $dbBaseDir . '/cache.sqlite');
    
    $direct_search_terms = ['profi', 'flamenco', 'премиум', 'premium'];
    foreach ($direct_search_terms as $term) {
        $stmt = $pdo->prepare("SELECT file_path, COUNT(*) as count FROM vector_embeddings WHERE LOWER(chunk_text) LIKE LOWER(?) GROUP BY file_path ORDER BY count DESC");
        $stmt->execute(['%' . $term . '%']);
        $direct_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Поиск '$term': найдено в " . count($direct_results) . " файлах\n";
        foreach ($direct_results as $dr) {
            echo "  - {$dr['file_path']}: {$dr['count']} упоминаний\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
