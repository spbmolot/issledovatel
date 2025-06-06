<?php
namespace ResearcherAI;
// Если Exception используется, его нужно либо полностью указать (\Exception),
// либо добавить use Exception; если он в глобальном пространстве имен.

class OpenAIProvider extends AIProvider {
    protected $proxyUrl;
    private $proxyManager; // Добавлено на основе OpenAIClient.php, если нужно

    public function __construct($apiKey, $proxyUrl = null) {
        parent::__construct($apiKey);
        $this->baseUrl = 'https://api.openai.com/v1';
        $this->proxyUrl = $proxyUrl;
        // Предполагаем, что FineProxyManager нужен здесь, как в OpenAIClient
        // Если FineProxyManager.php в том же каталоге, require_once может сработать,
        // но лучше полагаться на автозагрузку, если он тоже класс ResearcherAI.
        // Для PSR-4, FineProxyManager должен быть в своем файле.
        // require_once __DIR__ . '/FineProxyManager.php'; // Оставим пока так, если он не автозагружается
        $this->proxyManager = new FineProxyManager(); 
    }

    private function getWorkingProxy() { // Метод добавлен из OpenAIClient, если он нужен здесь
        if ($this->proxyUrl && !empty(trim($this->proxyUrl))) {
            return trim($this->proxyUrl);
        }
        return $this->proxyManager->getBestProxy();
    }

    public function testConnection() {
        try {
            $response = $this->sendRequest('models', array());
            return isset($response['data']) && is_array($response['data']);
        } catch (\Exception $e) { // Уточнено \Exception
            Logger::error('OpenAI Test Connection Error', $e);
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
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 2000,
            'temperature' => 0.3
        ));
        
        $text = isset($response['choices'][0]['message']['content']) ? $response['choices'][0]['message']['content'] : 'Ошибка получения ответа';
        
        $sources = array();
        foreach ($priceData as $fileName => $data) {
            if (mb_strpos(mb_strtolower($text), mb_strtolower($fileName)) !== false) {
                $sources[] = array('name' => $fileName, 'path' => $fileName);
            }
        }
        
        return array('text' => $text, 'sources' => $sources);
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

    protected function sendRequest($endpoint, $data) {
        // require_once 'FineProxyManager.php'; // Уже инициализирован в конструкторе
        
        $url = $this->baseUrl . '/' . $endpoint;
        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Увеличен таймаут до 120 секунд
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        $currentProxy = $this->getWorkingProxy(); // Используем getWorkingProxy()

        if ($currentProxy) {
            curl_setopt($ch, CURLOPT_PROXY, $currentProxy);
            Logger::info("[OpenAIProvider] Using proxy for OpenAI: " . $currentProxy);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            Logger::error("[OpenAIProvider] OpenAI cURL Error: " . $error . " for URL: " . $url . " with proxy: " . $currentProxy);
            throw new \Exception("Ошибка cURL (OpenAI): " . $error);
        }

        curl_close($ch);

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = 'Ошибка API OpenAI';
            if (isset($decodedResponse['error']['message'])) {
                $errorMessage .= ': ' . $decodedResponse['error']['message'];
            }
            Logger::error("[OpenAIProvider] OpenAI API Error (HTTP " . $httpCode . "): " . $errorMessage . " for URL: " . $url . " with proxy: " . $currentProxy);
            throw new \Exception($errorMessage . " (HTTP Код: " . $httpCode . ")");
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("[OpenAIProvider] OpenAI JSON Decode Error: " . json_last_error_msg() . " for URL: " . $url . " with proxy: " . $currentProxy . " Response: " . $response);
            throw new \Exception("Ошибка декодирования JSON ответа от OpenAI: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
}



    public function getEmbedding($text) {

        try {

            $response = $this->sendRequest('embeddings', array(

                'model' => 'text-embedding-ada-002',

                'input' => $text

            ));

            

            if (isset($response['data'][0]['embedding'])) {

                return $response['data'][0]['embedding'];

            }

            

            throw new \Exception('Invalid embedding response format');

        } catch (\Exception $e) {

            Logger::error("[OpenAIProvider] Embedding error: " . $e->getMessage());

            throw $e;

        }

    }

