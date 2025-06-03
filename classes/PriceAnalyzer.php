<?php
namespace ResearcherAI;

use Exception;
use ResearcherAI\CacheManager;

// Классы будут загружены через autoload

class PriceAnalyzer {
    private $openAI;
    private $yandexDisk;
    private $fileParser;
    private $cacheManager;
    private $maxFilesToAnalyze = 10;
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    public function __construct($openAI, $yandexDisk, CacheManager $cacheManager) {
        $this->openAI = $openAI;
        $this->yandexDisk = $yandexDisk;
        $this->fileParser = new FileParser();
        $this->cacheManager = $cacheManager;
    }
    
    public function processQuery($query, $folderPath = '/Прайсы') {
        $startTime = microtime(true);
        
        try {
            // Step 1: Extract keywords from query
            $keywords = $this->openAI->extractKeywords($query);
            if (empty($keywords)) {
                $keywords = $this->extractKeywordsLocally($query);
            }
            
            // Step 2: Search for relevant files
            $relevantFiles = $this->yandexDisk->searchFiles($keywords, $folderPath);
            
            if (empty($relevantFiles)) {
                return [
                    'response' => 'К сожалению, не удалось найти релевантные прайс-листы для вашего запроса. Проверьте, что файлы загружены в папку "' . $folderPath . '" на Яндекс.Диске.',
                    'sources' => [],
                    'processing_time' => microtime(true) - $startTime
                ];
            }
            
            // Step 3: Download and parse relevant files, using cache
            $priceData = [];
            $sources = [];
            $processedFiles = 0;
            
            foreach ($relevantFiles as $file) {
                if ($processedFiles >= $this->maxFilesToAnalyze) {
                    break;
                }
                
                if ($file['size'] > $this->maxFileSize) {
                    continue; // Skip large files
                }

                $yandexDiskPath = $file['path'];
                $yandexDiskModified = $file['modified'];
                $yandexDiskMd5 = $file['md5'] ?? null; // Если YandexDiskClient будет возвращать md5

                $parsedData = null;
                $cachedContent = $this->cacheManager->getCachedContent($yandexDiskPath, $yandexDiskModified, $yandexDiskMd5);

                if ($cachedContent !== false) {
                    $decodedData = json_decode($cachedContent, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $parsedData = $decodedData;
                    } else {
                        $this->cacheManager->deleteCacheEntry($yandexDiskPath); // Удаляем поврежденный кэш
                    }
                }

                if ($parsedData === null) { // Cache miss or failed to decode
                    $content = $this->yandexDisk->downloadFile($yandexDiskPath);
                    if ($content === null) {
                        continue;
                    }
                    
                    $parsedDataArray = $this->fileParser->parse($content, $file['name'], $file['mime_type']);
                    
                    if (!empty($parsedDataArray)) {
                        if ($this->cacheManager->setCache($yandexDiskPath, $yandexDiskModified, $yandexDiskMd5, json_encode($parsedDataArray))) {
                        }
                        $parsedData = $parsedDataArray;
                    } else {
                        $parsedData = []; // Устанавливаем пустой массив, чтобы не было ошибок дальше
                    }
                }
                
                if (!empty($parsedData)) {
                    $relevantData = $this->filterRelevantData($parsedData, $keywords);
                    if (!empty($relevantData)) {
                        $priceData[$file['name']] = $relevantData;
                        $sources[] = [
                            'name' => $file['name'],
                            'path' => $file['path'],
                            'size' => $file['size'],
                            'modified' => $file['modified']
                        ];
                        $processedFiles++;
                    }
                }
            }
            
            if (empty($priceData)) {
                return [
                    'response' => 'Найдены файлы, но в них не обнаружено данных, релевантных вашему запросу "' . $query . '". Попробуйте уточнить запрос или проверьте содержимое прайс-листов.',
                    'sources' => $sources, // Возвращаем источники, даже если данные не найдены, для информации
                    'processing_time' => microtime(true) - $startTime
                ];
            }
            
            // Step 4: Analyze data with OpenAI
            $analysis = $this->openAI->analyzeQuery($query, $priceData);
            
            return [
                'response' => $analysis['text'],
                'sources' => $sources,
                'processing_time' => microtime(true) - $startTime
            ];
            
        } catch (Exception $e) {
            Logger::error('Price Analyzer Error', $e); // Используем статический Logger
            return [
                'response' => 'Произошла ошибка при анализе прайс-листов: ' . $e->getMessage(),
                'sources' => [],
                'processing_time' => microtime(true) - $startTime
            ];
        }
    }
    
    private function extractKeywordsLocally($query) {
        // Simple local keyword extraction as fallback
        $keywords = [];
        
        // Remove common words
        $stopWords = [
            'найди', 'найти', 'покажи', 'показать', 'сколько', 'стоит', 'цена', 'на',
            'в', 'и', 'с', 'по', 'для', 'от', 'до', 'есть', 'ли', 'какой', 'какая',
            'какие', 'где', 'что', 'как', 'все', 'самый', 'лучший', 'дешевый',
            'дорогой', 'хороший', 'плохой', 'новый', 'старый'
        ];
        
        $words = preg_split('/[\s\.,;:!?\-()]+/u', mb_strtolower($query));
        
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    private function filterRelevantData($data, $keywords) {
        $relevantRows = [];
        $maxRows = 50; // Limit data sent to OpenAI
        $rowCount = 0;
        
        foreach ($data as $row) {
            if ($rowCount >= $maxRows) {
                break;
            }
            
            $rowText = mb_strtolower(implode(' ', $row));
            $relevanceScore = 0;
            
            foreach ($keywords as $keyword) {
                $keyword = mb_strtolower(trim($keyword));
                if (mb_strpos($rowText, $keyword) !== false) {
                    $relevanceScore++;
                }
            }
            
            if ($relevanceScore > 0) {
                $relevantRows[] = $row;
                $rowCount++;
            }
        }
        
        // If no relevant rows found, return first few rows as sample
        if (empty($relevantRows) && !empty($data)) {
            $relevantRows = array_slice($data, 0, min(10, count($data)));
        }
        
        return $this->formatDataForAI($relevantRows);
    }
    
    private function formatDataForAI($data) {
        if (empty($data)) {
            return '';
        }
        
        $formatted = '';
        $headers = array_shift($data); // First row as headers
        
        if ($headers) {
            $formatted .= "Заголовки: " . implode(' | ', $headers) . "\n\n";
        }
        
        foreach ($data as $index => $row) {
            if ($index >= 20) { // Limit to 20 rows
                $formatted .= "... и еще " . (count($data) - $index) . " строк\n";
                break;
            }
            
            $formatted .= ($index + 1) . ". " . implode(' | ', $row) . "\n";
        }
        
        return $formatted;
    }
    
    public function getFileStatistics($folderPath = '/Прайсы') {
        try {
            $files = $this->yandexDisk->listFiles($folderPath);
            
            $stats = [
                'total_files' => count($files),
                'total_size' => 0,
                'file_types' => [],
                'last_modified' => null
            ];
            
            foreach ($files as $file) {
                $stats['total_size'] += $file['size'];
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $stats['file_types'][$extension] = ($stats['file_types'][$extension] ?? 0) + 1;
                
                if ($stats['last_modified'] === null || $file['modified'] > $stats['last_modified']) {
                    $stats['last_modified'] = $file['modified'];
                }
            }
            
            return $stats;
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function testConnections() {
        return [
            'openai' => $this->openAI->testConnection(),
            'yandex' => $this->yandexDisk->testConnection()
        ];
    }
    
    public function updateFileCache($folderPath = '/Прайсы') {
        try {
            $files = $this->yandexDisk->listFiles($folderPath);
            
            // Store file list in cache for faster access
            $cacheData = [
                'files' => $files,
                'updated_at' => date('Y-m-d H:i:s'),
                'folder_path' => $folderPath
            ];
            
            file_put_contents('../cache/file_list.json', json_encode($cacheData, JSON_UNESCAPED_UNICODE));
            
            return count($files);
        } catch (Exception $e) {
            error_log('Cache update error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function getCachedFileList() {
        $cacheFile = '../cache/file_list.json';
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            
            // Check if cache is not older than 1 hour
            $cacheTime = strtotime($cacheData['updated_at']);
            if (time() - $cacheTime < 3600) {
                return $cacheData['files'];
            }
        }
        
        return null;
    }
}
?>