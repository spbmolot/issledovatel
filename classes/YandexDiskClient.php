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

                            'mime_type' => isset($item['mime_type']) ? $item['mime_type'] : null

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

    public function downloadFile($path) {

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

    public function getDownloadUrl($filePath) {
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
            echo "   [DEBUG] Начинаем загрузку файла...\n";
            echo "   [DEBUG] URL: " . substr($downloadUrl, 0, 60) . "...\n";
            echo "   [DEBUG] Путь: {$localFilePath}\n";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 минут таймаут
            
            echo "   [DEBUG] Пытаемся открыть файл для записи...\n";
            $fileHandle = fopen($localFilePath, 'w');
            if (!$fileHandle) {
                echo "   [DEBUG] ❌ Не удалось открыть файл для записи\n";
                Logger::error("[YandexDiskClient] Cannot create local file: {$localFilePath}");
                curl_close($ch);
                return false;
            }
            echo "   [DEBUG] ✅ Файл открыт для записи\n";
            
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
            
            echo "   [DEBUG] Выполняем CURL запрос...\n";
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            echo "   [DEBUG] CURL результат: " . ($result ? "SUCCESS" : "FAILED") . "\n";
            echo "   [DEBUG] HTTP код: {$httpCode}\n";
            if ($error) {
                echo "   [DEBUG] CURL ошибка: {$error}\n";
            }
            
            curl_close($ch);
            fclose($fileHandle);
            
            if ($error) {
                echo "   [DEBUG] ❌ Обнаружена CURL ошибка\n";
                Logger::error("[YandexDiskClient] Download cURL Error: " . $error);
                if (file_exists($localFilePath)) {
                    unlink($localFilePath);
                }
                return false;
            }
            
            if ($httpCode < 200 || $httpCode >= 300) {
                echo "   [DEBUG] ❌ Неверный HTTP код: {$httpCode}\n";
                Logger::error("[YandexDiskClient] Download HTTP Error: " . $httpCode);
                if (file_exists($localFilePath)) {
                    unlink($localFilePath);
                }
                return false;
            }
            
            echo "   [DEBUG] Проверяем загруженный файл...\n";
            if (!file_exists($localFilePath)) {
                echo "   [DEBUG] ❌ Файл не создан\n";
                Logger::error("[YandexDiskClient] Downloaded file not created");
                return false;
            }
            
            $fileSize = filesize($localFilePath);
            echo "   [DEBUG] Размер загруженного файла: {$fileSize} байт\n";
            
            if ($fileSize == 0) {
                echo "   [DEBUG] ❌ Файл пустой\n";
                Logger::error("[YandexDiskClient] Downloaded file is empty");
                unlink($localFilePath);
                return false;
            }
            
            echo "   [DEBUG] ✅ Файл успешно загружен\n";
            Logger::info("[YandexDiskClient] File downloaded successfully: " . basename($localFilePath) . " ({$fileSize} bytes)");
            return true;
            
        } catch (Exception $e) {
            echo "   [DEBUG] ❌ Исключение: " . $e->getMessage() . "\n";
            Logger::error("[YandexDiskClient] Download exception: " . $e->getMessage());
            if (file_exists($localFilePath)) {
                unlink($localFilePath);
            }
            return false;
        }
    }

}
