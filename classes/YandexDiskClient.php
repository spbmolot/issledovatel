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

}
