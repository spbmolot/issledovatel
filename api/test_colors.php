
<?php

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



// Тестовые данные для проверки трехцветной системы

$testResults = [

    'openai' => [

        'green' => '🟢 API работает, запросы выполняются успешно',

        'yellow' => '🟡 API работает, но недостаточно средств на балансе',

        'red' => '🔴 API не работает или неверный ключ'

    ],

    'deepseek' => [

        'green' => '🟢 DeepSeek API работает нормально',

        'yellow' => '🟡 Insufficient Balance - нужно пополнить баланс',

        'red' => '🔴 DeepSeek API недоступен'

    ],

    'yandex' => [

        'green' => '🟢 Подключен к Яндекс.Диску',

        'red' => '🔴 Ошибка подключения к Яндекс.Диску'

    ]

];



echo json_encode($testResults, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>

