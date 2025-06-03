
<?php



abstract class AIProvider {

    protected $apiKey;

    protected $baseUrl;

    

    public function __construct($apiKey) {

        $this->apiKey = $apiKey;

    }

    

    abstract public function testConnection();

    abstract public function analyzeQuery($query, $priceData);

    abstract public function extractKeywords($query);

    protected abstract function sendRequest($endpoint, $data);

}



class OpenAIProvider extends AIProvider {

    protected $proxyUrl;

    

    public function __construct($apiKey, $proxyUrl = null) {

        parent::__construct($apiKey);

        $this->baseUrl = 'https://api.openai.com/v1';

        $this->proxyUrl = $proxyUrl;

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

        require_once 'FineProxyManager.php';

        

        $url = $this->baseUrl . '/' . $endpoint;

        $headers = array(

            'Authorization: Bearer ' . $this->apiKey,

            'Content-Type: application/json'

        );

        

        $ch = curl_init();

        curl_setopt_array($ch, array(

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => json_encode($data),

            CURLOPT_HTTPHEADER => $headers,

            CURLOPT_TIMEOUT => 60,

            CURLOPT_SSL_VERIFYPEER => false

        ));

        

        // Прокси для OpenAI

        if ($this->proxyUrl) {

            $proxyManager = new FineProxyManager();

            $proxy = $proxyManager->getBestProxy();

            curl_setopt($ch, CURLOPT_PROXY, $proxy);

            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

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

        

        return json_decode($response, true);

    }

}



class DeepSeekProvider extends AIProvider {

    public function __construct($apiKey) {

        parent::__construct($apiKey);

        $this->baseUrl = 'https://api.deepseek.com/v1';

    }

    

    public function testConnection() {

        try {

            $response = $this->sendRequest('models', array());

            return isset($response['data']) && is_array($response['data']);

        } catch (Exception $e) {

            error_log('DeepSeek Test Connection Error: ' . $e->getMessage());

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

            'model' => 'deepseek-chat',

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

        $url = $this->baseUrl . '/' . $endpoint;

        $headers = array(

            'Authorization: Bearer ' . $this->apiKey,

            'Content-Type: application/json'

        );

        

        $ch = curl_init();

        curl_setopt_array($ch, array(

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => json_encode($data),

            CURLOPT_HTTPHEADER => $headers,

            CURLOPT_TIMEOUT => 60,

            CURLOPT_SSL_VERIFYPEER => false

        ));

        

        // DeepSeek не требует прокси из России

        

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

            throw new Exception("DeepSeek API Error: " . $errorMessage);

        }

        

        return json_decode($response, true);

    }

}



class AIProviderFactory {

    public static function create($provider, $apiKey, $proxyUrl = null) {

        switch ($provider) {

            case 'openai':

                return new OpenAIProvider($apiKey, $proxyUrl);

            case 'deepseek':

                return new DeepSeekProvider($apiKey);

            default:

                throw new Exception("Неподдерживаемый провайдер: $provider");

        }

    }

}

?>

