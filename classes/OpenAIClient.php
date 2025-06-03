
<?php

namespace ResearcherAI;

require_once 'FineProxyManager.php';



class OpenAIClient {

    private $apiKey;

    private $proxyUrl;

    private $baseUrl = 'https://api.openai.com/v1';

    private $proxyManager;

    

    public function __construct($apiKey, $proxyUrl = null) {

        $this->apiKey = $apiKey;

        $this->proxyUrl = $proxyUrl;

        $this->proxyManager = new FineProxyManager();

    }

    

    private function getWorkingProxy() {

        // Если прокси указан вручную - используем его

        if ($this->proxyUrl && !empty(trim($this->proxyUrl))) {

            return trim($this->proxyUrl);

        }

        

        // Иначе получаем лучший прокси от FineProxy

        return $this->proxyManager->getBestProxy();

    }

    

    public function testConnection() {

        try {

            $response = $this->sendRequest('models', array());

            return isset($response['data']) && is_array($response['data']);

        } catch (Exception $e) {

            error_log('OpenAI Test Connection Error: ' . $e->getMessage());

            return false;

        }

    }

    

    public function analyzeQuery($query, $priceData) {

        $systemPrompt = "Ты - эксперт по анализу прайс-листов в России. Анализируй данные о товарах и отвечай на вопросы пользователей на русском языке. 



Правила ответов:

- Всегда указывай источники данных (названия файлов)

- Показывай цены в рублях

- Сравнивай предложения разных поставщиков

- Выделяй лучшие предложения

- Отвечай структурированно и понятно



Формат ответа:

1. Краткий ответ на вопрос

2. Детальная информация с ценами  

3. Рекомендации по выбору

4. Источники данных";

        

        $userPrompt = "Запрос пользователя: " . $query . "\n\nДанные из прайс-листов:\n";

        foreach ($priceData as $fileName => $data) {

            $userPrompt .= "\n=== Файл: " . $fileName . " ===\n" . $data . "\n";

        }

        

        $messages = array(

            array('role' => 'system', 'content' => $systemPrompt),

            array('role' => 'user', 'content' => $userPrompt)

        );

        

        $response = $this->sendRequest('chat/completions', array(

            'model' => 'gpt-4o-mini', // Более доступная модель

            'messages' => $messages,

            'max_tokens' => 2000,

            'temperature' => 0.3

        ));

        

        $text = isset($response['choices'][0]['message']['content']) ? $response['choices'][0]['message']['content'] : 'Ошибка получения ответа';

        

        // Извлекаем источники из ответа

        $sources = array();

        foreach ($priceData as $fileName => $data) {

            if (mb_strpos(mb_strtolower($text), mb_strtolower($fileName)) !== false) {

                $sources[] = array('name' => $fileName, 'path' => $fileName);

            }

        }

        

        return array(

            'text' => $text,

            'sources' => $sources

        );

    }

    

    public function extractKeywords($query) {

        // Локальное извлечение ключевых слов (надежнее и быстрее)

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

    

    private function sendRequest($endpoint, $data) {

        $url = $this->baseUrl . '/' . $endpoint;

        $proxy = $this->getWorkingProxy();

        

        $headers = array(

            'Authorization: Bearer ' . $this->apiKey,

            'Content-Type: application/json',

            'User-Agent: Mozilla/5.0 (compatible; ResearcherAI/1.0)'

        );

        

        $ch = curl_init();

        curl_setopt_array($ch, array(

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => json_encode($data),

            CURLOPT_HTTPHEADER => $headers,

            CURLOPT_TIMEOUT => 60,

            CURLOPT_CONNECTTIMEOUT => 15,

            CURLOPT_SSL_VERIFYPEER => false,

            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_FOLLOWLOCATION => true

        ));

        

        // Настройка прокси только для OpenAI запросов

        if ($proxy) {

            curl_setopt($ch, CURLOPT_PROXY, $proxy);

            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);

            

            error_log("OpenAI запрос через прокси: $proxy");

        }

        

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = curl_error($ch);

        curl_close($ch);

        

        if ($error) {

            throw new Exception("cURL Error: " . $error);

        }

        

        if ($httpCode !== 200) {

            $errorData = json_decode($response, true);

            $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : "HTTP Error " . $httpCode;

            throw new Exception("OpenAI API Error: " . $errorMessage);

        }

        

        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            throw new Exception('Invalid JSON response from OpenAI');

        }

        

        return $decodedResponse;

    }

}

?>

