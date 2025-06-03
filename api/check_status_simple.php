
<?php

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



try {

    // Подключаем базу данных

    require_once __DIR__ . '/../config/database.php';

    

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch();

    

    $status = [

        'openai' => false,

        'yandex' => false,

        'error_messages' => []

    ];

    

    if ($settings) {

        // Простая проверка OpenAI

        if (!empty($settings['openai_key'])) {

            $status['openai'] = strlen($settings['openai_key']) > 20; // Простая проверка длины ключа

            if (!$status['openai']) {

                $status['error_messages']['openai'] = 'OpenAI ключ слишком короткий';

            }

        } else {

            $status['error_messages']['openai'] = 'OpenAI API ключ не настроен';

        }

        

        // Простая проверка Yandex

        if (!empty($settings['yandex_token'])) {

            $status['yandex'] = strlen($settings['yandex_token']) > 20; // Простая проверка длины токена

            if (!$status['yandex']) {

                $status['error_messages']['yandex'] = 'Yandex токен слишком короткий';

            }

        } else {

            $status['error_messages']['yandex'] = 'Yandex OAuth токен не настроен';

        }

    } else {

        $status['error_messages']['general'] = 'Настройки не найдены. Пожалуйста, настройте API ключи.';

    }

    

    echo json_encode($status, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    echo json_encode([

        'openai' => false,

        'yandex' => false,

        'error_messages' => ['general' => 'Ошибка: ' . $e->getMessage()]

    ], JSON_UNESCAPED_UNICODE);

}

?>

