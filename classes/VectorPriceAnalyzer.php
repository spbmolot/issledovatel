<?php
namespace ResearcherAI;

class VectorPriceAnalyzer extends PriceAnalyzer {
    public $vectorCacheManager;
    private $useVectorSearch = true;
    private $aiProvider;
    
    public function __construct($aiProvider, $yandexDisk, $cacheManager) {
        parent::__construct($aiProvider, $yandexDisk, $cacheManager);
        
        $this->aiProvider = $aiProvider;
        $dbBaseDir = dirname(__DIR__) . '/db';
        $this->vectorCacheManager = new VectorCacheManager($dbBaseDir);
        $this->vectorCacheManager->initializeEmbeddingManager($aiProvider);
        
        Logger::info("[VectorPriceAnalyzer] Initialized with vector search capabilities");
    }
    
    public function processQuery($query, $folderPath = '/Прайсы') {
        $startTime = microtime(true);
        
        try {
            Logger::info("[VectorPriceAnalyzer] Processing query with vector search: {$query}");
            
            if ($this->useVectorSearch) {
                return $this->processQueryWithVectorSearch($query, $folderPath, $startTime);
            } else {
                return parent::processQuery($query, $folderPath);
            }
            
        } catch (\Exception $e) {
            Logger::error('[VectorPriceAnalyzer] Error in processQuery: ' . $e->getMessage());
            Logger::info("[VectorPriceAnalyzer] Falling back to traditional search");
            return parent::processQuery($query, $folderPath);
        }
    }
    
    private function processQueryWithVectorSearch($query, $folderPath, $startTime) {
        $progress = array(); // Массив для отслеживания прогресса
        
        $progress[] = "🔍 Начинаю гибридный поиск по запросу: '{$query}'";
        $progress[] = "📊 Этап 1: Векторный поиск в базе из 97 файлов...";
        
        $similarChunks = $this->vectorCacheManager->findSimilarContent($query, 10);
        
        // Добавляем текстовый поиск для повышения точности
        $progress[] = "🔤 Этап 2: Дополнительный текстовый поиск по ключевым словам...";
        $textSearchChunks = $this->performTextSearch($query);
        
        // Объединяем результаты векторного и текстового поиска
        $allChunks = $this->mergeSearchResults($similarChunks, $textSearchChunks);
        
        if (empty($allChunks)) {
            $progress[] = "⚠️ Гибридный поиск не дал результатов, переключаюсь на традиционный поиск";
            Logger::info("[VectorPriceAnalyzer] No results from hybrid search, using traditional search");
            $result = parent::processQuery($query, $folderPath);
            $result['search_method'] = 'traditional_fallback';
            $result['progress'] = $progress;
            return $result;
        }
        
        $progress[] = "✅ Найдено " . count($allChunks) . " релевантных фрагментов (векторный: " . count($similarChunks) . ", текстовый: " . count($textSearchChunks) . ")";
        Logger::info("[VectorPriceAnalyzer] Found " . count($allChunks) . " chunks total");
        
        $relevantFiles = $this->groupChunksByFiles($allChunks);
        $progress[] = "📁 Группировка по файлам: " . count($relevantFiles) . " уникальных прайс-листов";
        
        $priceData = array();
        $sources = array();
        $fileProcessed = 0;
        
        foreach ($relevantFiles as $filePath => $chunks) {
            $fileName = basename($filePath);
            $fileProcessed++;
            $progress[] = "📄 [{$fileProcessed}/" . count($relevantFiles) . "] Обрабатываю: {$fileName}";
            
            $combinedText = $this->combineRelevantChunks($chunks);
            $progress[] = "   └─ Извлечено ключевой информации: " . strlen($combinedText) . " символов";
            
            if (!empty($combinedText)) {
                $priceData[$fileName] = $combinedText;
                $avgSimilarity = $this->calculateAverageSimilarity($chunks);
                $progress[] = "   └─ Релевантность: " . round($avgSimilarity * 100, 1) . "%";
                
                $sources[] = array(
                    'name' => $fileName,
                    'path' => $filePath,
                    'size' => 0,
                    'modified' => '',
                    'similarity' => $avgSimilarity
                );
            }
        }
        
        if (empty($priceData)) {
            $progress[] = "❌ Не удалось извлечь полезную информацию из найденных фрагментов";
            return array(
                'response' => 'Найдены похожие фрагменты, но не удалось извлечь релевантную информацию о ценах.',
                'sources' => array(),
                'processing_time' => microtime(true) - $startTime,
                'search_method' => 'vector_no_data',
                'progress' => $progress
            );
        }
        
        $totalChars = array_sum(array_map('strlen', $priceData));
        $progress[] = "🧠 Подготовка данных для AI анализа: {$totalChars} символов из " . count($priceData) . " файлов";
        $progress[] = "⚡ Отправляю запрос к DeepSeek AI для анализа и формирования ответа...";
        
        $analysis = $this->aiProvider->analyzeQuery($query, $priceData);
        
        if (isset($analysis['error'])) {
            $progress[] = "❌ Ошибка AI анализа: " . $analysis['error'];
        } else {
            $progress[] = "✅ AI анализ завершен успешно";
            $progress[] = "📝 Сформирован структурированный ответ с рекомендациями";
        }
        
        Logger::info("[VectorPriceAnalyzer] Vector search completed successfully");
        
        return array(
            'response' => $analysis['text'],
            'sources' => $sources,
            'processing_time' => microtime(true) - $startTime,
            'search_method' => 'vector',
            'progress' => $progress
        );
    }
    
    private function groupChunksByFiles($similarChunks) {
        $grouped = array();
        
        foreach ($similarChunks as $chunk) {
            $filePath = $chunk['file_path'];
            if (!isset($grouped[$filePath])) {
                $grouped[$filePath] = array();
            }
            $grouped[$filePath][] = $chunk;
        }
        
        foreach ($grouped as $filePath => &$chunks) {
            usort($chunks, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
        }
        
        return $grouped;
    }
    
    private function combineRelevantChunks($chunks) {
        $texts = array();
        $maxChunks = 5;           // Увеличиваем до 5 чанков для лучшего покрытия
        $maxCharsPerChunk = 1500; // Уменьшаем размер чанка для большего разнообразия
        $maxTotalChars = 6000;    // Немного увеличиваем общий лимит
        
        $totalChars = 0;
        for ($i = 0; $i < min($maxChunks, count($chunks)); $i++) {
            $chunkText = $chunks[$i]['content'];
            
            // Извлекаем ключевую информацию из чанка
            $processedChunk = $this->extractKeyInfo($chunkText, $maxCharsPerChunk);
            
            // Проверяем общий лимит
            if ($totalChars + strlen($processedChunk) > $maxTotalChars) {
                $remainingChars = $maxTotalChars - $totalChars;
                if ($remainingChars > 200) { // Минимум 200 символов для полезной информации
                    $processedChunk = substr($processedChunk, 0, $remainingChars) . '...';
                    $texts[] = $processedChunk;
                }
                break;
            }
            
            $texts[] = $processedChunk;
            $totalChars += strlen($processedChunk);
        }
        
        Logger::info("[VectorPriceAnalyzer] Combined text length: {$totalChars} chars from " . count($texts) . " chunks");
        return implode("\n\n=== ФАЙЛ " . ($texts ? count($texts) : 0) . " ===\n", $texts);
    }
    
    private function extractKeyInfo($text, $maxLength) {
        // Приоритизируем строки с ценами, артикулами, названиями товаров
        $lines = explode("\n", $text);
        $keyLines = array();
        $otherLines = array();
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Ключевые индикаторы: цены, артикулы, бренды
            if (preg_match('/\d+[.,]\d+|\d+\s*(руб|₽)|артикул|код|brand|модель/ui', $line) ||
                strlen($line) < 100) { // Короткие строки обычно содержат ключевую инфу
                $keyLines[] = $line;
            } else {
                $otherLines[] = $line;
            }
        }
        
        // Сначала добавляем ключевые строки, потом остальные
        $result = implode("\n", array_slice($keyLines, 0, 15));
        
        // Добавляем остальные строки если есть место
        if (strlen($result) < $maxLength * 0.7) {
            $remaining = $maxLength - strlen($result) - 10;
            $additional = implode("\n", array_slice($otherLines, 0, 5));
            if (strlen($additional) > $remaining) {
                $additional = substr($additional, 0, $remaining) . '...';
            }
            $result .= "\n" . $additional;
        }
        
        return strlen($result) > $maxLength ? substr($result, 0, $maxLength) . '...' : $result;
    }
    
    private function calculateAverageSimilarity($chunks) {
        if (empty($chunks)) return 0;
        
        $totalSimilarity = array_sum(array_column($chunks, 'similarity'));
        return round($totalSimilarity / count($chunks), 3);
    }
    
    private function performTextSearch($query) {
        try {
            $pdo = $this->vectorCacheManager->getPDO();
            
            // Разбиваем запрос на ключевые слова
            $keywords = $this->extractKeywords($query);
            $chunks = array();
            
            foreach ($keywords as $keyword) {
                if (strlen($keyword) < 3) continue; // Игнорируем короткие слова
                
                $stmt = $pdo->prepare("
                    SELECT file_path, chunk_text, chunk_index 
                    FROM vector_embeddings 
                    WHERE chunk_text LIKE ? 
                    LIMIT 20
                ");
                $stmt->execute(['%' . $keyword . '%']);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $result) {
                    $chunkKey = $result['file_path'] . '_' . $result['chunk_index'];
                    if (!isset($chunks[$chunkKey])) {
                        $chunks[$chunkKey] = array(
                            'file_path' => $result['file_path'],
                            'chunk_text' => $result['chunk_text'],
                            'chunk_index' => $result['chunk_index'],
                            'similarity' => 0.9, // Высокий приоритет для точных совпадений
                            'search_type' => 'text'
                        );
                    }
                }
            }
            
            Logger::info("[VectorPriceAnalyzer] Text search found " . count($chunks) . " chunks for keywords: " . implode(', ', $keywords));
            return array_values($chunks);
            
        } catch (\Exception $e) {
            Logger::error("[VectorPriceAnalyzer] Text search error: " . $e->getMessage());
            return array();
        }
    }
    
    private function extractKeywords($query) {
        // Извлекаем ключевые слова из запроса
        $query = mb_strtolower($query, 'UTF-8');
        
        // Удаляем стоп-слова
        $stopWords = array('и', 'в', 'на', 'с', 'по', 'для', 'от', 'до', 'из', 'к', 'о', 'об', 'что', 'как', 'где', 'когда');
        
        // Разбиваем на слова
        $words = preg_split('/[\s\-_\.]+/', $query);
        $keywords = array();
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return $keywords;
    }
    
    private function mergeSearchResults($vectorChunks, $textChunks) {
        $merged = array();
        $seen = array();
        
        // Добавляем результаты текстового поиска с высоким приоритетом
        foreach ($textChunks as $chunk) {
            $key = $chunk['file_path'] . '_' . $chunk['chunk_index'];
            if (!isset($seen[$key])) {
                $merged[] = $chunk;
                $seen[$key] = true;
            }
        }
        
        // Добавляем результаты векторного поиска
        foreach ($vectorChunks as $chunk) {
            $key = $chunk['file_path'] . '_' . $chunk['chunk_index'];
            if (!isset($seen[$key])) {
                $chunk['search_type'] = 'vector';
                $merged[] = $chunk;
                $seen[$key] = true;
            }
        }
        
        // Сортируем по релевантности (текстовые совпадения первыми)
        usort($merged, function($a, $b) {
            if (isset($a['search_type']) && $a['search_type'] === 'text' && 
                isset($b['search_type']) && $b['search_type'] === 'vector') {
                return -1;
            }
            if (isset($a['search_type']) && $a['search_type'] === 'vector' && 
                isset($b['search_type']) && $b['search_type'] === 'text') {
                return 1;
            }
            return $b['similarity'] <=> $a['similarity'];
        });
        
        Logger::info("[VectorPriceAnalyzer] Merged search results: " . count($merged) . " unique chunks");
        return $merged;
    }
    
    public function getVectorSearchStats() {
        try {
            $vectorizedFiles = $this->vectorCacheManager->getVectorizedFilesPaths();
            
            return array(
                'vectorized_files_count' => count($vectorizedFiles),
                'vector_search_enabled' => $this->useVectorSearch,
                'vectorized_files' => array_map('basename', $vectorizedFiles)
            );
        } catch (\Exception $e) {
            Logger::error("[VectorPriceAnalyzer] Error getting vector stats: " . $e->getMessage());
            return array(
                'vectorized_files_count' => 0,
                'vector_search_enabled' => $this->useVectorSearch,
                'error' => $e->getMessage()
            );
        }
    }
    
    public function enableVectorSearch($enable = true) {
        $this->useVectorSearch = $enable;
        Logger::info("[VectorPriceAnalyzer] Vector search " . ($enable ? 'enabled' : 'disabled'));
    }
}
