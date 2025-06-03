
<?php

header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');



try {

    echo json_encode([

        'success' => true,

        'message' => 'API работает',

        'timestamp' => date('Y-m-d H:i:s')

    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'error' => $e->getMessage()

    ]);

}

?>

