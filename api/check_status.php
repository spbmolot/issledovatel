
<?php

require_once '../config/database.php';



mb_internal_encoding('UTF-8');

mb_http_output('UTF-8');



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



try {

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch();

    

    $status = [

        'openai' => 'red',      // red, yellow, green

        'deepseek' => 'red',    // red, yellow, green  

        'yandex' => 'red',      // red, green (только 2 состояния)

        'ai_provider' => 'openai',

        'error_messages' => []

    ];

    

    if (!$settings) {

        $status['error_messages']['general'] = 'Настройки не найдены';

        echo json_encode($status, JSON_UNESCAPED_UNICODE);

        exit;

    }

    

    $status['ai_provider'] = $settings['ai_provider'] ?? 'openai';

    

    // ПРОВЕРКА OPENAI API с трехцветной логикой

    if (!empty($settings['openai_key'])) {

        try {

            // Делаем простой тестовый запрос

            $testData = [

                'model' => 'gpt-3.5-turbo',

                'messages' => [

                    ['role' => 'user', 'content' => 'Hi']

                ],

                'max_tokens' => 5

            ];

            

            $ch = curl_init();

            curl_setopt_array($ch, [

                CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_POST => true,

                CURLOPT_POSTFIELDS => json_encode($testData),

                CURLOPT_HTTPHEADER => [

                    'Authorization: Bearer ' . $settings['openai_key'],

                    'Content-Type: application/json'

                ],

                CURLOPT_TIMEOUT => 15,

                CURLOPT_SSL_VERIFYPEER => false

            ]);

            

            // Добавляем прокси если настроен

            if (!empty($settings['proxy_url']) && $settings['proxy_enabled']) {

                curl_setopt($ch, CURLOPT_PROXY, $settings['proxy_url']);

                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

            }

            

            $response = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $error = curl_error($ch);

            curl_close($ch);

            

            if ($error) {

                $status['openai'] = 'red';

                $status['error_messages']['openai'] = '🔴 Ошибка подключения: ' . $error;

            } elseif ($httpCode === 200) {

                $data = json_decode($response, true);

                if (isset($data['choices'][0]['message']['content'])) {

                    $status['openai'] = 'green';

                    $status['error_messages']['openai'] = '🟢 API работает, запросы выполняются';

                } else {

                    $status['openai'] = 'red';

                    $status['error_messages']['openai'] = '🔴 Неверный формат ответа API';

                }

            } elseif ($httpCode === 401) {

                $status['openai'] = 'red';

                $status['error_messages']['openai'] = '🔴 Неверный API ключ';

            } elseif ($httpCode === 429) {

                // Rate limit - может означать что ключ работает, но лимиты

                $status['openai'] = 'yellow';

                $status['error_messages']['openai'] = '🟡 Превышен лимит запросов, попробуйте позже';

            } elseif ($httpCode === 402 || $httpCode === 403) {

                // Payment required или недостаточно средств

                $status['openai'] = 'yellow';

                $status['error_messages']['openai'] = '🟡 Недостаточно средств на балансе OpenAI';

            } else {

                $errorData = json_decode($response, true);

                $errorMessage = $errorData['error']['message'] ?? "HTTP $httpCode";

                

                // Проверяем известные ошибки баланса

                if (strpos(strtolower($errorMessage), 'insufficient') !== false || 

                    strpos(strtolower($errorMessage), 'quota') !== false ||

                    strpos(strtolower($errorMessage), 'billing') !== false) {

                    $status['openai'] = 'yellow';

                    $status['error_messages']['openai'] = '🟡 Проблема с балансом: ' . $errorMessage;

                } else {

                    $status['openai'] = 'red';

                    $status['error_messages']['openai'] = '🔴 Ошибка API: ' . $errorMessage;

                }

            }

        } catch (Exception $e) {

            $status['openai'] = 'red';

            $status['error_messages']['openai'] = '🔴 Ошибка: ' . $e->getMessage();

        }

    } else {

        $status['openai'] = 'red';

        $status['error_messages']['openai'] = '🔴 API ключ не настроен';

    }

    

    // ПРОВЕРКА DEEPSEEK API с трехцветной логикой

    if (!empty($settings['deepseek_key'])) {

        try {

            $testData = [

                'model' => 'deepseek-reasoner',

                'messages' => [

                    ['role' => 'user', 'content' => 'Привет']

                ],

                'max_tokens' => 1000

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

                CURLOPT_TIMEOUT => 15,

                CURLOPT_SSL_VERIFYPEER => false

            ]);

            

            $response = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $error = curl_error($ch);

            curl_close($ch);

            

            if ($error) {

                $status['deepseek'] = 'red';

                $status['error_messages']['deepseek'] = '🔴 Ошибка подключения: ' . $error;

            } elseif ($httpCode === 200) {

                $data = json_decode($response, true);

                if (isset($data['error'])) {

                    $status['deepseek'] = 'red';

                    $status['error_messages']['deepseek'] = '🔴 Ошибка API: ' . ($data['error']['message'] ?? 'unknown');

                } else {

                    // Для модели deepseek-reasoner достаточно успешного HTTP 200

                    $status['deepseek'] = 'green';

                    $status['error_messages']['deepseek'] = '🟢 API работает';

                }

            } elseif ($httpCode === 401) {

                $status['deepseek'] = 'red';

                $status['error_messages']['deepseek'] = '🔴 Неверный API ключ';

            } elseif ($httpCode === 400) {

                $errorData = json_decode($response, true);

                $errorMessage = $errorData['error']['message'] ?? 'HTTP 400';

                

                // СПЕЦИАЛЬНАЯ ОБРАБОТКА "Insufficient Balance"

                if (strpos($errorMessage, 'Insufficient Balance') !== false) {

                    $status['deepseek'] = 'yellow';

                    $status['error_messages']['deepseek'] = '🟡 Недостаточно средств на балансе DeepSeek';

                } else {

                    $status['deepseek'] = 'red';

                    $status['error_messages']['deepseek'] = '🔴 Ошибка запроса: ' . $errorMessage;

                }

            } elseif ($httpCode === 429) {

                $status['deepseek'] = 'yellow';

                $status['error_messages']['deepseek'] = '🟡 Превышен лимит запросов DeepSeek';

            } else {

                $status['deepseek'] = 'red';

                $status['error_messages']['deepseek'] = '🔴 HTTP ошибка: ' . $httpCode;

            }

        } catch (Exception $e) {

            $status['deepseek'] = 'red';

            $status['error_messages']['deepseek'] = '🔴 Ошибка: ' . $e->getMessage();

        }

    } else {

        $status['deepseek'] = 'red';

        $status['error_messages']['deepseek'] = '🔴 API ключ не настроен';

    }

    

    // ПРОВЕРКА YANDEX DISK API (только зеленый/красный)

    if (!empty($settings['yandex_token'])) {

        try {

            $ch = curl_init();

            curl_setopt_array($ch, [

                CURLOPT_URL => 'https://cloud-api.yandex.net/v1/disk',

                CURLOPT_RETURNTRANSFER => true,

                CURLOPT_HTTPHEADER => [

                    'Authorization: OAuth ' . $settings['yandex_token']

                ],

                CURLOPT_TIMEOUT => 10,

                CURLOPT_SSL_VERIFYPEER => false

            ]);

            

            $response = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $error = curl_error($ch);

            curl_close($ch);

            

            if ($error) {

                $status['yandex'] = 'red';

                $status['error_messages']['yandex'] = '🔴 Ошибка подключения: ' . $error;

            } elseif ($httpCode === 200) {

                $data = json_decode($response, true);

                if (isset($data['total_space'])) {

                    $status['yandex'] = 'green';

                    $status['error_messages']['yandex'] = '🟢 Подключен к Яндекс.Диску';

                } else {

                    $status['yandex'] = 'red';

                    $status['error_messages']['yandex'] = '🔴 Неверный формат ответа API';

                }

            } elseif ($httpCode === 401) {

                $status['yandex'] = 'red';

                $status['error_messages']['yandex'] = '🔴 Неверный OAuth токен';

            } else {

                $status['yandex'] = 'red';

                $status['error_messages']['yandex'] = '🔴 HTTP ошибка: ' . $httpCode;

            }

        } catch (Exception $e) {

            $status['yandex'] = 'red';

            $status['error_messages']['yandex'] = '🔴 Ошибка: ' . $e->getMessage();

        }

    } else {

        $status['yandex'] = 'red';

        $status['error_messages']['yandex'] = '🔴 OAuth токен не настроен';

    }

    

    echo json_encode($status, JSON_UNESCAPED_UNICODE);

    

} catch (Exception $e) {

    error_log('Check status error: ' . $e->getMessage());

    echo json_encode([

        'openai' => 'red',

        'deepseek' => 'red', 

        'yandex' => 'red',

        'error_messages' => ['general' => '🔴 Ошибка сервера: ' . $e->getMessage()]

    ], JSON_UNESCAPED_UNICODE);

}

?>
