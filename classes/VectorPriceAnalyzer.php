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
        $similarChunks = $this->vectorCacheManager->findSimilarContent($query, 10);
        
        if (empty($similarChunks)) {
            Logger::info("[VectorPriceAnalyzer] No similar vectors found, using traditional search");
            $result = parent::processQuery($query, $folderPath);
            $result['search_method'] = 'traditional_fallback';
            return $result;
        }
        
        Logger::info("[VectorPriceAnalyzer] Found " . count($similarChunks) . " similar chunks");
        
        $relevantFiles = $this->groupChunksByFiles($similarChunks);
        $priceData = array();
        $sources = array();
        
        foreach ($relevantFiles as $filePath => $chunks) {
            $fileName = basename($filePath);
            $combinedText = $this->combineRelevantChunks($chunks);
            
            if (!empty($combinedText)) {
                $priceData[$fileName] = $combinedText;
                $sources[] = array(
                    'name' => $fileName,
                    'path' => $filePath,
                    'size' => 0,
                    'modified' => '',
                    'similarity' => $this->calculateAverageSimilarity($chunks)
                );
            }
        }
        
        if (empty($priceData)) {
            return array(
                'response' => 'Найдены похожие фрагменты, но не удалось извлечь релевантную информацию о ценах.',
                'sources' => array(),
                'processing_time' => microtime(true) - $startTime,
                'search_method' => 'vector_no_data'
            );
        }
        
        $analysis = $this->aiProvider->analyzeQuery($query, $priceData);
        
        Logger::info("[VectorPriceAnalyzer] Vector search completed successfully");
        
        return array(
            'response' => $analysis['text'],
            'sources' => $sources,
            'processing_time' => microtime(true) - $startTime,
            'search_method' => 'vector'
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
        $maxChunks = 3;
        
        for ($i = 0; $i < min($maxChunks, count($chunks)); $i++) {
            $texts[] = $chunks[$i]['content'];
        }
        
        return implode("\n\n", $texts);
    }
    
    private function calculateAverageSimilarity($chunks) {
        if (empty($chunks)) return 0;
        
        $totalSimilarity = array_sum(array_column($chunks, 'similarity'));
        return round($totalSimilarity / count($chunks), 3);
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
