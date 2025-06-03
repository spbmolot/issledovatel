
<?php

require_once __DIR__ . '/../classes/FineProxyManager.php';



header('Content-Type: application/json; charset=utf-8');



try {

    $proxyManager = new FineProxyManager();

    

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $input = json_decode(file_get_contents('php://input'), true);

        $ip = $input['ip'] ?? '176.57.216.68'; // IP вашего сайта

        

        $result = $proxyManager->setIP($ip);

        echo json_encode(array(

            'success' => $result,

            'message' => $result ? "IP $ip добавлен в белый список" : 'Ошибка добавления IP',

            'ip' => $ip

        ));

    } else {

        $bestProxy = $proxyManager->getBestProxy();

        

        echo json_encode(array(

            'recommended' => $bestProxy,

            'server_ip' => '176.57.216.68',

            'detected_ip' => $proxyManager->getServerIP()

        ));

    }

} catch (Exception $e) {

    echo json_encode(array('error' => $e->getMessage()));

}

?>

