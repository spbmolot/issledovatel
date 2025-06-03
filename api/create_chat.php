
<?php

require_once __DIR__ . '/../config/database.php';



header('Content-Type: application/json; charset=utf-8');



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['error' => 'Method not allowed']);

    exit;

}



try {

    $input = json_decode(file_get_contents('php://input'), true);

    $title = $input['title'] ?? 'Новый чат';

    

    $stmt = $pdo->prepare("INSERT INTO researcher_chats (title) VALUES (?)");

    $stmt->execute([$title]);

    

    $chatId = $pdo->lastInsertId();

    

    echo json_encode([

        'id' => $chatId,

        'title' => $title,

        'created_at' => date('Y-m-d H:i:s')

    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()]);

}

?>

