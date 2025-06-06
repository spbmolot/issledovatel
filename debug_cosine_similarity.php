<?php
require_once 'vendor/autoload.php';

use ResearcherAI\VectorCacheManager;
use ResearcherAI\AIProviderFactory;

echo "🔍 DEBUG: Анализ косинусного сходства и наличия файла ОПУС\n\n";

try {
    $dbBaseDir = __DIR__ . '/db';
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    
    // Создаем DeepSeek провайдер
    $aiProvider = AIProviderFactory::create('deepseek', 'fake-key');
    $vectorCacheManager->initializeEmbeddingManager($aiProvider);
    
    // Проверяем наличие файла ОПУС в базе
    $pdo = new PDO('sqlite:' . $dbBaseDir . '/cache.sqlite');
    
    echo "📁 ПОИСК ФАЙЛА ОПУС В БАЗЕ:\n";
    $stmt = $pdo->prepare("SELECT file_path, COUNT(*) as chunk_count FROM vector_embeddings WHERE file_path LIKE ? GROUP BY file_path");
    $stmt->execute(['%ОПУС%']);
    $opus_files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($opus_files)) {
        echo "❌ Файл ОПУС НЕ НАЙДЕН в базе vector_embeddings!\n";
        echo "Возможные причины:\n";
        echo "1. Файл не был векторизован\n";
        echo "2. Ошибка при векторизации\n";
        echo "3. Неправильное имя файла\n\n";
    } else {
        echo "✅ Найдены файлы ОПУС:\n";
        foreach ($opus_files as $file) {
            echo "  - {$file['file_path']} ({$file['chunk_count']} чанков)\n";
        }
        echo "\n";
    }
    
    // Тестируем косинусное сходство
    echo "🧮 ТЕСТ КОСИНУСНОГО СХОДСТВА:\n";
    
    // Получаем два разных embedding
    $text1 = "линолеум profi premium";
    $text2 = "паркет дуб натур";
    $text3 = "линолеум profi premium flamenco"; // Очень похожий текст
    
    $embedding1 = $vectorCacheManager->getQueryEmbedding($text1);
    $embedding2 = $vectorCacheManager->getQueryEmbedding($text2);
    $embedding3 = $vectorCacheManager->getQueryEmbedding($text3);
    
    echo "Embedding 1 (первые 10): " . implode(', ', array_slice($embedding1, 0, 10)) . "\n";
    echo "Embedding 2 (первые 10): " . implode(', ', array_slice($embedding2, 0, 10)) . "\n";
    echo "Embedding 3 (первые 10): " . implode(', ', array_slice($embedding3, 0, 10)) . "\n\n";
    
    // Проверяем косинусное сходство вручную
    function calculateCosineSimilarity($vector1, $vector2) {
        if (count($vector1) !== count($vector2)) {
            return 0;
        }
        
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        if ($magnitude1 === 0.0 || $magnitude2 === 0.0) {
            return 0;
        }
        
        return $dotProduct / ($magnitude1 * $magnitude2);
    }
    
    $similarity_1_2 = calculateCosineSimilarity($embedding1, $embedding2);
    $similarity_1_3 = calculateCosineSimilarity($embedding1, $embedding3);
    $similarity_2_3 = calculateCosineSimilarity($embedding2, $embedding3);
    
    echo "🎯 РЕЗУЛЬТАТЫ СХОДСТВА:\n";
    echo "Сходство '$text1' vs '$text2': " . round($similarity_1_2, 6) . "\n";
    echo "Сходство '$text1' vs '$text3': " . round($similarity_1_3, 6) . "\n";
    echo "Сходство '$text2' vs '$text3': " . round($similarity_2_3, 6) . "\n\n";
    
    if ($similarity_1_2 === 0.0 && $similarity_1_3 === 0.0 && $similarity_2_3 === 0.0) {
        echo "❌ ВСЕ СХОДСТВА = 0! Проблема с hash-based embedding!\n";
        echo "Hash-based векторы DeepSeek слишком похожие или некорректные\n\n";
    }
    
    // Проверяем реальные данные из базы
    echo "📊 АНАЛИЗ РЕАЛЬНЫХ ВЕКТОРОВ ИЗ БАЗЫ:\n";
    $stmt = $pdo->prepare("SELECT file_path, embedding FROM vector_embeddings LIMIT 3");
    $stmt->execute();
    $sample_vectors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sample_vectors as $i => $row) {
        $vector = json_decode($row['embedding'], true);
        if ($vector === null) {
            echo "Вектор #" . ($i + 1) . ": ОШИБКА декодирования JSON\n";
            continue;
        }
        
        echo "Вектор #" . ($i + 1) . " (файл: " . basename($row['file_path']) . "):\n";
        echo "  Размер: " . count($vector) . "\n";
        echo "  Первые 10: " . implode(', ', array_slice($vector, 0, 10)) . "\n";
        echo "  Сумма: " . round(array_sum($vector), 4) . "\n";
        
        // Проверяем уникальность значений
        $unique_values = array_unique($vector);
        echo "  Уникальных значений: " . count($unique_values) . " из " . count($vector) . "\n";
        
        // Проверяем на старые заглушки (все 0.1)
        $all_same = count($unique_values) === 1 && abs($vector[0] - 0.1) < 0.001;
        if ($all_same) {
            echo "  ⚠️  СТАРЫЙ ВЕКТОР-ЗАГЛУШКА (все значения 0.1)!\n";
        }
        
        // Вычисляем стандартное отклонение правильно
        $mean = array_sum($vector) / count($vector);
        $variance = array_sum(array_map(function($x) use ($mean) { 
            return pow($x - $mean, 2); 
        }, $vector)) / count($vector);
        $std_dev = sqrt($variance);
        echo "  Стандартное отклонение: " . round($std_dev, 4) . "\n\n";
    }
    
    // Проверяем сколько старых векторов в базе
    echo "🔍 ПРОВЕРКА СТАРЫХ ВЕКТОРОВ:\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vector_embeddings WHERE embedding LIKE '%0.1,0.1,0.1%'");
    $stmt->execute();
    $old_vectors_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Старых векторов (заглушек): $old_vectors_count\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM vector_embeddings");
    $stmt->execute();
    $total_vectors = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Всего векторов: $total_vectors\n\n";
    
    if ($old_vectors_count > 0) {
        echo "⚠️  В базе есть старые векторы-заглушки! Нужна ревекторизация.\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
}
