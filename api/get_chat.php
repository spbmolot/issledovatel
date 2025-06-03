
<?php

require_once __DIR__ . '/../config/database.php';



header('Content-Type: application/json; charset=utf-8');



if (!isset($_GET['id'])) {

    http_response_code(400);

    echo json_encode(['error' => 'Chat ID required']);

    exit;

}



try {

    $chatId = (int)$_GET['id'];

    

    $stmt = $pdo->prepare("SELECT * FROM researcher_chats WHERE id = ?");

    $stmt->execute([$chatId]);

    $chat = $stmt->fetch();

    

    if (!$chat) {

        http_response_code(404);

        echo json_encode(['error' => 'Chat not found']);

        exit;

    }

    

    $stmt = $pdo->prepare("

        SELECT type, message as text, sources, created_at 

        FROM researcher_chat_messages 

        WHERE chat_id = ? 

        ORDER BY created_at ASC

    ");

    $stmt->execute([$chatId]);

    $messages = $stmt->fetchAll();

    

    foreach ($messages as &$message) {

        $message['sources'] = json_decode($message['sources'], true) ?: [];

    }

    

    echo json_encode([

        'chat' => $chat,

        'messages' => $messages

    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()]);

}

?>

