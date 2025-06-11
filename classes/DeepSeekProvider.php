<?php
namespace ResearcherAI;

class DeepSeekProvider extends AIProvider {
    public function __construct($apiKey) {
        parent::__construct($apiKey);
        $this->baseUrl = "https://api.deepseek.com";
    }

    public function testConnection() {
        try {
            $response = $this->sendRequest('/v1/models', array(), 'GET');
            return isset($response['data']) && is_array($response['data']);
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[DeepSeekProvider] Connection test failed: " . $e->getMessage());
            }
            return false;
        }
    }

    public function analyzeQuery($query, $priceData) {
        $systemPrompt = "Ты - эксперт по анализу прайс-листов в России. Анализируй данные о товарах и отвечай на вопросы пользователей на русском языке.\n\nПравила ответов:\n- Всегда указывай источники данных (названия файлов)\n- Показывай цены в рублях\n- Сравнивай предложения разных поставщиков\n- Выделяй лучшие предложения\n- Отвечай структурированно и понятно\n\nФормат ответа:\n1. Краткий ответ на вопрос\n2. Детальная информация с ценами\n3. Рекомендации по выбору\n4. Источники данных";
        
        $userPrompt = "Запрос пользователя: " . $query . "\n\nДанные из прайс-листов:\n";
        $totalLength = 0;
        $maxLength = 50000; // Ограничиваем общий размер данных
        
        foreach ($priceData as $fileName => $data) {
            // Простая очистка данных от некорректных символов
            $cleanData = $data;
            if (function_exists('mb_convert_encoding')) {
                $cleanData = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            }
            $cleanData = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleanData);
            
            $fileSection = "\n=== Файл: " . $fileName . " ===\n" . $cleanData . "\n";
            
            if ($totalLength + strlen($fileSection) > $maxLength) {
                if (class_exists('Logger')) {
                    Logger::warning("[DeepSeekProvider] Data truncated due to size limit");
                }
                break;
            }
            
            $userPrompt .= $fileSection;
            $totalLength += strlen($fileSection);
        }
        
        if (class_exists('Logger')) {
            Logger::debug("[DeepSeekProvider] Total prompt length: " . strlen($userPrompt) . " chars");
        }
        
        $messages = array(
            array('role' => 'system', 'content' => $systemPrompt),
            array('role' => 'user', 'content' => $userPrompt)
        );
        
        try {
            $response = $this->sendRequest('/v1/chat/completions', array(
                'model' => 'deepseek-reasoner',
                'messages' => $messages,
                'max_tokens' => 32000
            ));
            
            $text = isset($response['choices'][0]['message']['content']) ? $response['choices'][0]['message']['content'] : 'Ошибка получения ответа';
            $reasoning = isset($response['choices'][0]['message']['reasoning_content']) ? $response['choices'][0]['message']['reasoning_content'] : '';
            
            if (!empty($reasoning) && class_exists('Logger')) {
                Logger::debug("[DeepSeekProvider] Reasoning length: " . strlen($reasoning) . " chars");
            }
            
            $sources = array();
            foreach ($priceData as $fileName => $data) {
                if (mb_strpos(mb_strtolower($text), mb_strtolower($fileName)) !== false) {
                    $sources[] = array('name' => $fileName, 'path' => $fileName);
                }
            }
            
            return array('text' => $text, 'sources' => $sources);
            
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[DeepSeekProvider] analyzeQuery error: " . $e->getMessage());
            }
            return array('text' => 'Ошибка при анализе запроса: ' . $e->getMessage(), 'sources' => array());
        }
    }

    public function extractKeywords($query) {
        $words = explode(' ', mb_strtolower($query));
        $stopWords = array('найди', 'покажи', 'что', 'как', 'где', 'на', 'в', 'и', 'с', 'по', 'для', 'от', 'до', 'есть', 'ли', 'цены', 'цена', 'стоимость');
        $keywords = array();
        
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:()');
            if (mb_strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }

    public function getEmbedding($text) {
        try {
            // DeepSeek не имеет встроенного API для embeddings, 
            // используем заглушку совместимую с векторным поиском
            // В будущем можно интегрировать с другим embedding сервисом
            
            // Генерируем простой хеш-based embedding для совместимости
            $hash = hash('sha256', $text);
            $embedding = array();
            
            // Создаем 1536-мерный вектор (как у OpenAI) из хеша
            for ($i = 0; $i < 1536; $i++) {
                $byte_index = $i % 32; // 32 байта в SHA256
                $byte_value = hexdec(substr($hash, $byte_index * 2, 2));
                $embedding[] = ($byte_value - 127.5) / 127.5; // Нормализуем в [-1, 1]
            }
            
            if (class_exists('Logger')) {
                Logger::info("[DeepSeekProvider] Generated hash-based embedding with " . count($embedding) . " dimensions");
            }
            return $embedding;
            
        } catch (\Exception $e) {
            if (class_exists('Logger')) {
                Logger::error("[DeepSeekProvider] getEmbedding error: " . $e->getMessage());
            }
            // Возвращаем случайный вектор как fallback
            return array_fill(0, 1536, 0.0);
        }
    }

    protected function sendRequest($endpoint, $data, $method = "POST") {
        $url = $this->baseUrl . $endpoint;
        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
            
            // Отладка JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (class_exists('Logger')) {
                    Logger::error("[DeepSeekProvider] JSON encode error: " . json_last_error_msg());
                    Logger::error("[DeepSeekProvider] Data causing error: " . print_r($data, true));
                }
                throw new \Exception("JSON encode error: " . json_last_error_msg());
            }
            
            if (class_exists('Logger')) {
                Logger::debug("[DeepSeekProvider] Sending JSON length: " . strlen($jsonData) . " bytes");
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        } elseif ($method === "GET") {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("CURL Error: " . $error);
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            if (class_exists('Logger')) {
                Logger::error("[DeepSeekProvider] HTTP Error {$httpCode}: " . $response);
            }
            throw new \Exception("HTTP Error: {$httpCode}");
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON decode error: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
}
