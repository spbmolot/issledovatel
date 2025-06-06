<?php
/**
 * Скрипт векторизации с детальным debug-логированием
 * Только для диагностики, не использовать в production
 */

require_once 'vendor/autoload.php';

use ResearcherAI\Logger;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorPriceAnalyzer;
use ResearcherAI\VectorCacheManager;
use ResearcherAI\FileParser;
use ResearcherAI\CacheManager;

// Временно включаем debug-режим для классов
class DebugVectorCacheManager extends VectorCacheManager {
    public function storeVectorData($filePath, $chunks) {
        if (!$this->isEmbeddingManagerInitialized()) {
            echo "   [DEBUG] ❌ EmbeddingManager не инициализирован\n";
            return false;
        }

        echo "   [DEBUG] storeVectorData() вызван для: {$filePath}\n";
        echo "   [DEBUG] Количество чанков: " . count($chunks) . "\n";
        echo "   [DEBUG] ✅ EmbeddingManager проверен\n";
        echo "   [DEBUG] Подготавливаем SQL statement...\n";
        
        try {
            $stmt = $this->pdo->prepare("INSERT INTO vector_embeddings (file_path, chunk_text, embedding, chunk_index) VALUES (?, ?, ?, ?)");
            echo "   [DEBUG] ✅ SQL statement подготовлен\n";
        } catch (\Exception $e) {
            echo "   [DEBUG] ❌ Ошибка подготовки SQL: " . $e->getMessage() . "\n";
            return false;
        }

        $stored = 0;
        foreach ($chunks as $index => $chunk) {
            try {
                echo "   [DEBUG] Обрабатываем чанк #" . ($index + 1) . "\n";
                echo "   [DEBUG] Вызываем getEmbedding()...\n";
                
                $embedding = $this->embeddingManager->getEmbedding($chunk);
                
                if ($embedding === null || !is_array($embedding)) {
                    echo "   [DEBUG] ❌ Embedding = null или не массив для чанка #" . ($index + 1) . "\n";
                    continue;
                }

                echo "   [DEBUG] ✅ Embedding получен, размер: " . count($embedding) . "\n";
                
                $embeddingJson = json_encode($embedding);
                if ($embeddingJson === false) {
                    echo "   [DEBUG] ❌ Ошибка JSON кодирования для чанка #" . ($index + 1) . "\n";
                    continue;
                }
                
                echo "   [DEBUG] JSON размер: " . strlen($embeddingJson) . " символов\n";
                echo "   [DEBUG] Выполняем SQL INSERT...\n";
                
                $stmt->execute([$filePath, $chunk, $embeddingJson, $index]);
                $stored++;
                
                echo "   [DEBUG] ✅ Чанк #" . ($index + 1) . " сохранен в БД\n";
                
            } catch (\Exception $e) {
                echo "   [DEBUG] ❌ Исключение в чанке #" . ($index + 1) . ": " . $e->getMessage() . "\n";
                continue;
            }
        }

        echo "   [DEBUG] Сохранено векторов: {$stored} из " . count($chunks) . "\n";
        if ($stored > 0) {
            echo "   [DEBUG] ✅ Векторизация успешна: {$stored} чанков\n";
        } else {
            echo "   [DEBUG] ❌ Векторизация провалилась\n";
        }
        
        return $stored > 0;
    }
}

echo "\n🚀 Запускаем векторизацию с debug-логированием...\n\n";

try {
    // Инициализация компонентов  
    $cacheManager = new CacheManager();
    $fileParser = new FileParser();
    $yandexClient = new YandexDiskClient();
    
    // Создаем debug версию VectorCacheManager
    $vectorCacheManager = new DebugVectorCacheManager();
    
    // Создаем VectorPriceAnalyzer с debug менеджером
    $vectorAnalyzer = new VectorPriceAnalyzer($vectorCacheManager);
    
    echo "✅ Компоненты инициализированы\n";
    echo "✅ VectorPriceAnalyzer готов\n\n";

    // Получаем список файлов
    $folderPath = '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';
    echo "📁 Получаем список файлов из папки: {$folderPath}\n\n";
    
    $files = $yandexClient->listFiles($folderPath);
    if (empty($files)) {
        echo "❌ Файлы не найдены в папке {$folderPath}\n";
        exit(1);
    }

    // Обрабатываем первые 3 файла для теста
    $processedFiles = 0;
    $successfulVectorizations = 0;
    $failedVectorizations = 0;
    $totalChunks = 0;

    foreach (array_slice($files, 0, 3) as $file) {
        if (strpos($file['name'], '.xlsx') === false) continue;
        
        echo "🔄 Обрабатываем: " . $file['name'] . "\n";
        
        try {
            // Загружаем файл
            echo "   📥 Загружаем файл с Яндекс.Диска...\n";
            $downloadUrl = $yandexClient->getDownloadUrl($file['path']);
            $tempFile = sys_get_temp_dir() . '/' . $file['name'];
            
            if (!$yandexClient->downloadFile($downloadUrl, $tempFile)) {
                echo "   ❌ Ошибка загрузки файла\n";
                $failedVectorizations++;
                continue;
            }

            // Извлекаем текст
            echo "   📊 Извлекаем текст из Excel файла...\n";
            $text = $fileParser->extractTextFromFile($tempFile);
            
            if (empty($text)) {
                echo "   ❌ Не удалось извлечь текст\n";
                unlink($tempFile);
                $failedVectorizations++;
                continue;
            }

            // Сохраняем в кэш  
            echo "   💾 Сохраняем текст в кэш...\n";
            $cacheManager->storeFileText($file['path'], $text);

            // Разбиваем на чанки
            $chunks = [$text]; // Упрощенное разбиение на чанки
            echo "   [DEBUG] ✅ Приступаем к векторизации " . count($chunks) . " чанков...\n";
            
            // Проверяем EmbeddingManager
            if ($vectorCacheManager->isEmbeddingManagerInitialized()) {
                echo "   [DEBUG] ✅ EmbeddingManager инициализирован\n";
            } else {
                echo "   [DEBUG] ❌ EmbeddingManager НЕ инициализирован\n";
                $failedVectorizations++;
                continue;
            }
            
            // Показываем содержимое чанка
            foreach ($chunks as $i => $chunk) {
                echo "   [DEBUG] Чанк #" . ($i + 1) . ": " . substr($chunk, 0, 50) . "...\n";
            }

            // Векторизируем
            if ($vectorCacheManager->storeVectorData($file['path'], $chunks)) {
                $successfulVectorizations++;
                $totalChunks += count($chunks);
                echo "   ✅ Файл векторизирован\n";
            } else {
                $failedVectorizations++;
                echo "   ❌ Ошибка векторизации\n";
            }

            // Удаляем временный файл
            unlink($tempFile);
            $processedFiles++;
            
        } catch (Exception $e) {
            echo "   ❌ Ошибка обработки файла: " . $e->getMessage() . "\n";
            $failedVectorizations++;
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
        echo "\n";
    }

    // Статистика
    echo "\n🎯 Результаты debug векторизации:\n";
    echo "   - Обработано файлов: {$processedFiles}\n";
    echo "   - Векторизировано файлов: {$successfulVectorizations}\n";
    echo "   - Неудачно векторизировано файлов: {$failedVectorizations}\n";
    echo "   - Всего векторизированных чанков: {$totalChunks}\n\n";

    // Финальная статистика
    $stats = $vectorCacheManager->getVectorizationStats();
    echo "📊 Статистика в БД:\n";
    echo "   - Векторизованных файлов: " . $stats['vectorized_files_count'] . "\n\n";

} catch (Exception $e) {
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>
