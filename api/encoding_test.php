
<?php

// Тест различных методов кодировки



// Метод 1: Принудительная установка UTF-8

header('Content-Type: application/json; charset=utf-8');

mb_internal_encoding('UTF-8');

mb_http_output('UTF-8');



$testStrings = [

    'Простой текст',

    'Текст с символами: №, ™, ©, ®',

    'Кавычки: "обычные" и «русские»',

    'Дефисы: - и —',

    'Ламинат кроно 8мм'

];



$results = [];



foreach ($testStrings as $string) {

    $results[] = [

        'original' => $string,

        'utf8_encode' => utf8_encode($string),

        'mb_convert' => mb_convert_encoding($string, 'UTF-8', 'auto'),

        'htmlspecialchars' => htmlspecialchars($string, ENT_QUOTES, 'UTF-8'),

        'json_encode' => json_encode($string, JSON_UNESCAPED_UNICODE)

    ];

}



echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>

