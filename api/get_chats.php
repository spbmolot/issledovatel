
<?php

require_once __DIR__ . '/../config/database.php';



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



try {

    $stmt = $pdo->prepare("

        SELECT c.*, 

               COUNT(cm.id) as message_count,

               MAX(cm.created_at) as last_message_at

        FROM researcher_chats c 

        LEFT JOIN researcher_chat_messages cm ON c.id = cm.chat_id 

        GROUP BY c.id 

        ORDER BY COALESCE(last_message_at, c.created_at) DESC 

        LIMIT 50

    ");

    $stmt->execute();

    

    $chats = $stmt->fetchAll();

    echo json_encode($chats);

    

} catch (Exception $e) {

    error_log('Get chats error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()]);

}

?>

