<?php
namespace ResearcherAI;

class YandexDiskClient {

    private $oauthToken;

    private $baseUrl = 'https://cloud-api.yandex.net/v1/disk';

    public function __construct($oauthToken) {

        $this->oauthToken = $oauthToken;

    }

    public function testConnection() {

        try {

            $response = $this->sendRequest($this->baseUrl);

            return isset($response['total_space']);

        } catch (\Exception $e) {

            return false;

        }

    }

    public function listFiles($path = '/', $recursive = true) {

        $files = array();

        $this->listFilesRecursive($path, $files, $recursive);

        return $files;

    }

    private function listFilesRecursive($path, &$files, $recursive = true) {

        try {

            $url = $this->baseUrl . '/resources?path=' . urlencode($path) . '&limit=1000';
            // Запрашиваем дополнительные поля md5, modified, size рекурсивно
            $url .= '&fields=_embedded.items.path,_embedded.items.name,_embedded.items.type,_embedded.items.size,_embedded.items.modified,_embedded.items.md5';

            $response = $this->sendRequest($url);

            if (!isset($response['_embedded']['items'])) {

                return;

            }

            foreach ($response['_embedded']['items'] as $item) {

                if ($item['type'] === 'file') {

                    if ($this->isPriceFile($item['name'])) {

                        $files[] = array(

                            'name' => $item['name'],

                            'path' => $item['path'],

                            'download_url' => isset($item['file']) ? $item['file'] : null,

                            'size' => isset($item['size']) ? $item['size'] : 0,

                            'modified' => isset($item['modified']) ? $item['modified'] : null,

                            'mime_type' => isset($item['mime_type']) ? $item['mime_type'] : null,

                            'md5' => isset($item['md5']) ? $item['md5'] : null

                        );

                    }

                } elseif ($item['type'] === 'dir' && $recursive) {

                    $this->listFilesRecursive($item['path'], $files, $recursive);

                }

            }

        } catch (\Exception $e) {

            Logger::error("[YandexDiskClient] Error listing files in " . $path, $e);

        }

    }

    public function downloadFileContent($path) {

        try {

            $url = $this->baseUrl . '/resources/download?path=' . urlencode($path);

            $response = $this->sendRequest($url);

            if (!isset($response['href'])) {

                throw new \Exception('No download URL provided');

            }

            $ch = curl_init();

            curl_setopt_array($ch, array(

                CURLOPT_URL => $response['href'],

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_TIMEOUT => 300,

                CURLOPT_FOLLOWLOCATION => true

            ));

            $content = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {

                throw new \Exception("Download error: " . $error);

            }

            if ($httpCode !== 200) {

                throw new \Exception("Download failed with HTTP code: " . $httpCode);

            }

            return $content;

        } catch (\Exception $e) {

            Logger::error("[YandexDiskClient] Error downloading file " . $path, $e);

            return null;

        }

    }

    public function searchFilesByExtension($folderPath, $extension) {
        $allFiles = $this->listFiles($folderPath);
        $filteredFiles = array();
        
        $extension = strtolower(ltrim($extension, '.'));
        
        foreach ($allFiles as $file) {
            if (isset($file['name'])) {
                $fileName = strtolower($file['name']);
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                
                if ($fileExtension === $extension) {
                    $filteredFiles[] = $file;
                }
            }
        }
        
        return $filteredFiles;
    }

    public function searchFiles($keywords, $folderPath = '/') {

        $allFiles = $this->listFiles($folderPath);

        $relevantFiles = array();

        foreach ($allFiles as $file) {

            $relevanceScore = $this->calculateRelevance($file, $keywords);

            if ($relevanceScore > 0) {

                $file['relevance_score'] = $relevanceScore;

                $relevantFiles[] = $file;

            }

        }

        usort($relevantFiles, function($a, $b) {

            return $b['relevance_score'] - $a['relevance_score'];

        });

        return $relevantFiles;

    }

    private function calculateRelevance($file, $keywords) {

        $score = 0;

        $fileName = strtolower($file['name']);

        foreach ($keywords as $keyword) {

            $keyword = strtolower(trim($keyword));

            if (empty($keyword)) continue;

            if (strpos($fileName, $keyword) !== false) {

                $score += 10;

            }

            similar_text($fileName, $keyword, $similarity);

            if ($similarity > 60) {

                $score += $similarity / 10;

            }

        }

        return $score;

    }

    private function isPriceFile($filename) {

        $filename = strtolower($filename);

        $patterns = array(

            'прайс', 'price', 'цена', 'cost', 'rate',

            'каталог', 'catalog', 'товар', 'product',

            'ассортимент', 'assortment', 'номенклатура'

        );

        foreach ($patterns as $pattern) {

            if (strpos($filename, $pattern) !== false) {

                return true;

            }

        }

        $allowedExtensions = array('xlsx', 'xls', 'csv', 'txt', 'pdf', 'docx', 'doc');

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return in_array(strtolower($extension), $allowedExtensions);

    }

    private function sendRequest($url, $method = 'GET') {

        $headers = array(

            'Authorization: OAuth ' . $this->oauthToken,

            'Content-Type: application/json'

        );

        $ch = curl_init();

        curl_setopt_array($ch, array(

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => $headers,

            CURLOPT_TIMEOUT => 30,

            CURLOPT_CONNECTTIMEOUT => 10

        ));

        if ($method !== 'GET') {

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        }

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            Logger::error("[YandexDiskClient] cURL Error for URL '" . (isset($url) ? $url : 'N/A') . "': " . $error, new \Exception($error));
            throw new \Exception("cURL Error: " . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errorData = json_decode($response, true);
            $jsonLastError = json_last_error(); // Проверяем, был ли JSON в ответе об ошибке
            
            $apiMessageDetail = "HTTP Error " . $httpCode;
            if ($jsonLastError === JSON_ERROR_NONE && $errorData) {
                $apiMessageDetail = isset($errorData['message']) ? $errorData['message'] : $apiMessageDetail;
                if (isset($errorData['description'])) $apiMessageDetail .= " - " . $errorData['description'];
                if (isset($errorData['error'])) $apiMessageDetail .= " (Yandex Code: " . $errorData['error'] . ")";
            }

            $logMessage = "[YandexDiskClient] Yandex Disk API Error for URL '" . (isset($url) ? $url : 'N/A') . "'. HTTP Code: {$httpCode}. Message: {$apiMessageDetail}. Raw Response: {$response}";
            Logger::error($logMessage);
            throw new \Exception("Yandex Disk API Error: " . $apiMessageDetail);
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("[YandexDiskClient] Invalid JSON response from Yandex Disk (after successful HTTP code) for URL '" . (isset($url) ? $url : 'N/A') . "'. Error: " . json_last_error_msg() . ". Raw Response: " . $response);
            throw new \Exception('Invalid JSON response from Yandex Disk: ' . json_last_error_msg());
        }

        return $decodedResponse;

    }

    /**
     * Получение информации о файле
     */
    public function getFileInfo($path) {
        try {
            $url = $this->baseUrl . '/resources?path=' . urlencode($path);
            $response = $this->sendRequest($url);
            return $response;
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[YandexDiskClient] Error getting file info for " . $path, $e);
            }
            return null;
        }
    }

    /**
     * Публикация файла и получение публичной ссылки
     */
    public function publishFile($path) {
        try {
            // Сначала публикуем файл
            $publishUrl = $this->baseUrl . '/resources/publish?path=' . urlencode($path);
            $response = $this->sendRequest($publishUrl, 'PUT');
            
            // Получаем информацию о файле с публичной ссылкой
            $fileInfo = $this->getFileInfo($path);
            
            if (isset($fileInfo['public_url'])) {
                return $fileInfo['public_url'];
            }
            
            return null;
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[YandexDiskClient] Error publishing file " . $path, $e);
            }
            return null;
        }
    }

    /**
     * Получение ссылки для скачивания файла
     */
    public function getDownloadUrl($path) {
        try {
            $url = $this->baseUrl . '/resources/download?path=' . urlencode($path);
            $response = $this->sendRequest($url);
            
            if (isset($response['href'])) {
                return $response['href'];
            }
            
            return null;
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[YandexDiskClient] Error getting download URL for " . $path, $e);
            }
            return null;
        }
    }

    public function getDownloadUrlOld($filePath) {
        try {
            $url = $this->baseUrl . '/resources/download?path=' . urlencode($filePath);
            $response = $this->sendRequest($url);
            
            if (isset($response['href'])) {
                Logger::info("[YandexDiskClient] Download URL получен для: " . basename($filePath));
                return $response['href'];
            }
            
            Logger::error("[YandexDiskClient] Download URL не найден в ответе API");
            return false;
            
        } catch (Exception $e) {
            Logger::error("[YandexDiskClient] Ошибка получения Download URL: " . $e->getMessage());
            return false;
        }
    }

    public function downloadFile($downloadUrl, $localFilePath) {
        try {
            if (class_exists('Logger')) {
                Logger::debug("[YandexDiskClient] Начинаем загрузку файла");
                Logger::debug("[YandexDiskClient] URL: " . substr($downloadUrl, 0, 60) . "...");
                Logger::debug("[YandexDiskClient] Путь: {$localFilePath}");
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 минут таймаут
            
            if (class_exists('Logger')) {
                Logger::debug("[YandexDiskClient] Пытаемся открыть файл для записи");
            }
            $fileHandle = fopen($localFilePath, 'w');
            if (!$fileHandle) {
                if (class_exists('Logger')) {
                    Logger::error("[YandexDiskClient] Cannot create local file: {$localFilePath}");
                }
                curl_close($ch);
                return false;
            }
            if (class_exists('Logger')) {
                Logger::debug("[YandexDiskClient] Файл открыт для записи");
            }
            
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
            
            if (class_exists('Logger')) {
                Logger::debug("[YandexDiskClient] Выполняем CURL запрос");
            }
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            if (class_exists('Logger')) {
                Logger::debug("[YandexDiskClient] CURL результат: " . ($result ? "SUCCESS" : "FAILED"));
                Logger::debug("[YandexDiskClient] HTTP код: {$httpCode}");
                if ($error) {
                    Logger::debug("[YandexDiskClient] CURL ошибка: {$error}");
                }
            }
            
            curl_close($ch);
            fclose($fileHandle);
            
            if ($error) {
                if (class_exists('Logger')) {
                    Logger::error("[YandexDiskClient] Download cURL Error: " . $error);
                }
                if (file_exists($localFilePath)) {
                    unlink($localFilePath);
                }
                return false;
            }
            
            if ($httpCode < 200 || $httpCode >= 300) {
                if (class_exists('Logger')) {
                    Logger::error("[YandexDiskClient] Download HTTP Error: " . $httpCode);
                }
                if (file_exists($localFilePath)) {
                    unlink($localFilePath);
                }
                return false;
            }
            
            if (class_exists('Logger')) {
                Logger::debug("[YandexDiskClient] Проверяем загруженный файл");
            }
            if (!file_exists($localFilePath)) {
                if (class_exists('Logger')) {
                    Logger::error("[YandexDiskClient] Downloaded file not created");
                }
                return false;
            }
            
            $fileSize = filesize($localFilePath);
            if (class_exists('Logger')) {
                Logger::debug("[YandexDiskClient] Размер загруженного файла: {$fileSize} байт");
            }
            
            if ($fileSize == 0) {
                if (class_exists('Logger')) {
                    Logger::error("[YandexDiskClient] Downloaded file is empty");
                }
                unlink($localFilePath);
                return false;
            }
            
            if (class_exists('Logger')) {
                Logger::info("[YandexDiskClient] File downloaded successfully: " . basename($localFilePath) . " ({$fileSize} bytes)");
            }
            return true;
            
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[YandexDiskClient] Download exception: " . $e->getMessage());
            }
            if (file_exists($localFilePath)) {
                unlink($localFilePath);
            }
            return false;
        }
    }

    /**
     * Упрощённая обёртка для получения основных метаданных файла (modified, md5, name, size)
     * Совместима с вызовами VectorCacheManager::checkVectorStatus()
     */
    public function stat($path) {
        try {
            $info = $this->getFileInfo($path);
            if (!$info) {
                return null;
            }
            return [
                'name'     => $info['name']     ?? basename($path),
                'path'     => $info['path']     ?? $path,
                'modified' => $info['modified'] ?? null,
                'md5'      => $info['md5']      ?? null,
                'size'     => $info['size']     ?? null,
            ];
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[YandexDiskClient] stat error for " . $path, $e);
            }
            return null;
        }
    }

}
