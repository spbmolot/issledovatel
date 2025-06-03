
<?php

require_once __DIR__ . '/../config/database.php';



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);

    exit;

}



try {

    $input = json_decode(file_get_contents('php://input'), true);

    $chatId = $input['chat_id'] ?? null;

    

    if (!$chatId) {

        throw new Exception('Chat ID required');

    }

    

    // Удаляем сообщения чата

    $stmt = $pdo->prepare("DELETE FROM researcher_chat_messages WHERE chat_id = ?");

    $stmt->execute([$chatId]);

    

    // Удаляем сам чат

    $stmt = $pdo->prepare("DELETE FROM researcher_chats WHERE id = ?");

    $result = $stmt->execute([$chatId]);

    

    if ($result) {

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

    } else {

        throw new Exception('Failed to delete chat');

    }

    

} catch (Exception $e) {

    error_log('Delete chat error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

}

?>

