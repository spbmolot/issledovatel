
<?php

function testProxy($proxy) {

    echo "Тестируем прокси: $proxy\n";

    

    $ch = curl_init();

    curl_setopt_array($ch, array(

        CURLOPT_URL => 'https://api.openai.com/v1/models',

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_PROXY => $proxy,

        CURLOPT_PROXYTYPE => CURLPROXY_HTTP,

        CURLOPT_TIMEOUT => 10,

        CURLOPT_HTTPHEADER => array(

            'Authorization: Bearer sk-test-key',

            'Content-Type: application/json'

        )

    ));

    

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $error = curl_error($ch);

    curl_close($ch);

    

    if ($error) {

        echo "  ❌ Ошибка: $error\n";

        return false;

    }

    

    echo "  HTTP код: $httpCode\n";

    if ($httpCode == 401) { // Unauthorized - значит прокси работает, но ключ неверный

        echo "  ✅ Прокси работает (получили 401 - ожидаемо для тестового ключа)\n";

        return true;

    } elseif ($httpCode == 200) {

        echo "  ✅ Прокси работает отлично!\n";

        return true;

    } else {

        echo "  ❌ Прокси не работает\n";

        return false;

    }

}



// Тестируем разные форматы

$proxies = array(

    'http://185.77.222.232:8085',

    'https://185.77.222.232:8085',

    '185.77.222.232:8085'

);



foreach ($proxies as $proxy) {

    testProxy($proxy);

    echo "\n";

}

?>

