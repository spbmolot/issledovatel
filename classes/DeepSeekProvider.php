<?php

namespace ResearcherAI;

// Если Exception используется, его нужно либо полностью указать (\Exception),
// либо добавить use Exception; если он в глобальном пространстве имен.

class DeepSeekProvider extends AIProvider {
    public function __construct($apiKey) {
        parent::__construct($apiKey);
        $this->baseUrl = 'https://api.deepseek.com/v1';
    }

    public function testConnection() {
        try {
            $response = $this->sendRequest('models', array(), 'GET'); // DeepSeek /models может быть GET
            return isset($response['data']) && is_array($response['data']);
        } catch (\Exception $e) { // Уточнено \Exception
            error_log('DeepSeek Test Connection Error: ' . $e->getMessage());
            return false;
        }
    }

    public function analyzeQuery($query, $priceData) {
        $systemPrompt = "Ты - эксперт по анализу прайс-листов в России. Анализируй данные о товарах и отвечай на вопросы пользователей на русском языке.\n\nПравила ответов:\n- Всегда указывай источники данных (названия файлов)\n- Показывай цены в рублях\n- Сравнивай предложения разных поставщиков\n- Выделяй лучшие предложения\n- Отвечай структурированно и понятно\n\nФормат ответа:\n1. Краткий ответ на вопрос\n2. Детальная информация с ценами  \n3. Рекомендации по выбору\n4. Источники данных";

        $userPrompt = "Запрос пользователя: " . $query . "\n\nДанные из прайс-листов:\n";
        foreach ($priceData as $fileName => $data) {
            $userPrompt .= "\n=== Файл: " . $fileName . " ===\n" . $data . "\n";
        }

        $messages = array(
            array('role' => 'system', 'content' => $systemPrompt),
            array('role' => 'user', 'content' => $userPrompt)
        );

        $response = $this->sendRequest('chat/completions', array(
            'model' => 'deepseek-chat', // или другая актуальная модель DeepSeek
            'messages' => $messages,
            'max_tokens' => 2000,
            'temperature' => 0.3
        ));

        $text = isset($response['choices'][0]['message']['content']) ? $response['choices'][0]['message']['content'] : 'Ошибка получения ответа от DeepSeek';

        $sources = array();
        foreach ($priceData as $fileName => $data) {
            if (mb_strpos(mb_strtolower($text), mb_strtolower($fileName)) !== false) {
                $sources[] = array('name' => $fileName, 'path' => $fileName);
            }
        }

        return array('text' => $text, 'sources' => $sources);
    }

    public function extractKeywords($query) {
        // Можно использовать тот же подход, что и в OpenAIProvider, или специфичный для DeepSeek, если есть
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

    protected function sendRequest($endpoint, $data, $method = 'POST') { // Добавлен параметр $method
        $url = $this->baseUrl . '/' . $endpoint;
        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            // Для GET запросов (например, /models), данные могут передаваться в URL, если API это поддерживает
            // Если $data не пустое для GET, его нужно будет добавить как query string к $url
            // Например: if (!empty($data)) { $url .= '?' . http_build_query($data); curl_setopt($ch, CURLOPT_URL, $url); }
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }

        // DeepSeek обычно не требует прокси, но можно добавить логику, если понадобится

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Ошибка cURL (DeepSeek): " . $error);
        }

        curl_close($ch);
        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = 'Ошибка API DeepSeek';
            if (isset($decodedResponse['error']['message'])) {
                $errorMessage .= ': ' . $decodedResponse['error']['message'];
            }
            throw new \Exception($errorMessage . " (HTTP Код: " . $httpCode . ")");
        }
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ошибка декодирования JSON ответа от DeepSeek: " . json_last_error_msg());
        }

        return $decodedResponse;
    }
}
