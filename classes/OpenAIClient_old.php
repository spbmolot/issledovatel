<?php

namespace ResearcherAI;

class OpenAIClient {
    private $apiKey;
    private $proxyUrl;
    private $baseUrl = 'https://api.openai.com/v1';
    
    public function __construct($apiKey, $proxyUrl = null) {
        $this->apiKey = $apiKey;
        $this->proxyUrl = $proxyUrl;
    }
    
    public function analyzeQuery($query, $priceData) {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($query, $priceData);
        
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];
        
        $response = $this->sendRequest('chat/completions', [
            'model' => 'gpt-4-turbo-preview',
            'messages' => $messages,
            'max_tokens' => 2000,
            'temperature' => 0.3
        ]);
        
        return $this->parseResponse($response);
    }
    
    public function extractKeywords($query) {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Ты помощник для извлечения ключевых слов из запросов пользователей о товарах. Извлеки наиболее важные ключевые слова для поиска товаров в прайс-листах. Верни только ключевые слова через запятую.'
            ],
            [
                'role' => 'user',
                'content' => "Запрос: $query\n\nИзвлеки ключевые слова для поиска:"
            ]
        ];
        
        $response = $this->sendRequest('chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'max_tokens' => 100,
            'temperature' => 0.1
        ]);
        
        if (isset($response['choices'][0]['message']['content'])) {
            $keywords = $response['choices'][0]['message']['content'];
            return array_map('trim', explode(',', $keywords));
        }
        
        return [];
    }
    
    private function buildSystemPrompt() {
        return "Ты - эксперт по анализу прайс-листов и помощник менеджера по закупкам. 
        
Твоя задача:
1. Анализировать запросы пользователей о товарах и ценах
2. Находить релевантную информацию в предоставленных прайс-листах
3. Предоставлять точные и полезные ответы с указанием источников
4. Сравнивать цены от разных поставщиков
5. Предлагать лучшие варианты покупки

Правила ответов:
- Всегда указывай источник информации (имя файла прайса)
- При сравнении цен показывай все доступные варианты
- Выделяй лучшие предложения по цене
- Если информации недостаточно, честно об этом говори
- Отвечай на русском языке
- Структурируй ответы для удобного чтения

Формат ответа:
- Краткий ответ на вопрос
- Детальная информация с ценами
- Рекомендации
- Источники данных";
    }
    
    private function buildUserPrompt($query, $priceData) {
        $prompt = "Запрос пользователя: $query\n\n";
        $prompt .= "Доступные данные из прайс-листов:\n";
        
        foreach ($priceData as $file => $data) {
            $prompt .= "\n=== Файл: $file ===\n";
            $prompt .= $data . "\n";
        }
        
        $prompt .= "\nПроанализируй данные и дай подробный ответ на запрос пользователя.";
        
        return $prompt;
    }
    
    private function parseResponse($response) {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Invalid OpenAI response');
        }
        
        $content = $response['choices'][0]['message']['content'];
        
        // Extract sources mentioned in the response
        $sources = [];
        if (preg_match_all('/(?:файл|прайс|источник)[:\s]*([^\n\r\.,;]+)/ui', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $source = trim($match);
                if (!empty($source) && !in_array($source, $sources)) {
                    $sources[] = $source;
                }
            }
        }
        
        return [
            'text' => $content,
            'sources' => $sources
        ];
    }
    
    private function sendRequest($endpoint, $data) {
        $url = $this->baseUrl . '/' . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        // Configure proxy if provided
        if ($this->proxyUrl) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxyUrl);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? "HTTP Error $httpCode";
            throw new Exception("OpenAI API Error: $errorMessage");
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from OpenAI');
        }
        
        return $decodedResponse;
    }
    
    public function testConnection() {
        try {
            $response = $this->sendRequest('models', []);
            return isset($response['data']) && is_array($response['data']);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>