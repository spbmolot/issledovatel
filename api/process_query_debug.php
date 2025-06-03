
<?php

// Включаем все логи ошибок

error_reporting(E_ALL);

ini_set('display_errors', 1);

ini_set('log_errors', 1);



require_once __DIR__ . '/../config/database.php';



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



$debug = [

    'timestamp' => date('Y-m-d H:i:s'),

    'request_method' => $_SERVER['REQUEST_METHOD'],

    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none'

];



try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        throw new Exception('Only POST method allowed');

    }



    $input = file_get_contents('php://input');

    $debug['raw_input'] = $input;

    

    $decoded = json_decode($input, true);

    $debug['decoded_input'] = $decoded;

    

    if (!$decoded) {

        throw new Exception('Invalid JSON input');

    }



    $query = $decoded['query'] ?? '';

    if (empty($query)) {

        throw new Exception('Query is empty');

    }

    

    $debug['query'] = $query;



    // Получаем настройки

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch();

    

    if (!$settings) {

        throw new Exception('No settings found');

    }

    

    $debug['settings_found'] = true;

    $debug['ai_provider'] = $settings['ai_provider'] ?? 'none';

    $debug['has_openai_key'] = !empty($settings['openai_key']);

    $debug['has_deepseek_key'] = !empty($settings['deepseek_key']);

    $debug['has_yandex_token'] = !empty($settings['yandex_token']);

    

    // Проверяем какой провайдер выбран

    $aiProvider = $settings['ai_provider'] ?? 'openai';

    

    if ($aiProvider === 'deepseek') {

        if (empty($settings['deepseek_key'])) {

            throw new Exception('DeepSeek key not configured');

        }

        

        $debug['deepseek_key_length'] = strlen($settings['deepseek_key']);

        

        // Проверяем подключение к DeepSeek

        $debug['testing_deepseek'] = true;

        

        $testData = [

            'model' => 'deepseek-chat',

            'messages' => [

                ['role' => 'user', 'content' => 'Test']

            ],

            'max_tokens' => 10

        ];

        

        $ch = curl_init();

        curl_setopt_array($ch, [

            CURLOPT_URL => 'https://api.deepseek.com/v1/chat/completions',

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_POST => true,

            CURLOPT_POSTFIELDS => json_encode($testData),

            CURLOPT_HTTPHEADER => [

                'Authorization: Bearer ' . $settings['deepseek_key'],

                'Content-Type: application/json'

            ],

            CURLOPT_TIMEOUT => 30

        ]);

        

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = curl_error($ch);

        curl_close($ch);

        

        $debug['deepseek_test'] = [

            'http_code' => $httpCode,

            'curl_error' => $error,

            'response' => $response ? json_decode($response, true) : null

        ];

        

        if ($httpCode !== 200) {

            $errorData = json_decode($response, true);

            throw new Exception('DeepSeek API Error: ' . ($errorData['error']['message'] ?? "HTTP $httpCode"));

        }

        

        $debug['deepseek_working'] = true;

    }

    

    $debug['success'] = true;

    

} catch (Exception $e) {

    $debug['error'] = $e->getMessage();

    $debug['success'] = false;

}



echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

?>

