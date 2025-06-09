
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
    
    error_log(" [delete_chat] Начинаем удаление чата ID: $chatId");
    
    if (!$chatId) {

        throw new Exception('Chat ID required');

    }
    
    // Проверяем существование чата

    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM researcher_chats WHERE id = ?");

    $checkStmt->execute([$chatId]);

    $chatExists = $checkStmt->fetch()['count'];

    error_log(" [delete_chat] Чат существует: $chatExists");
    
    if ($chatExists == 0) {

        error_log(" [delete_chat] Чат ID:$chatId не найден в базе");

        throw new Exception('Chat not found');

    }
    
    // Удаляем сообщения чата

    $stmt = $pdo->prepare("DELETE FROM researcher_chat_messages WHERE chat_id = ?");

    $messagesResult = $stmt->execute([$chatId]);

    $deletedMessages = $stmt->rowCount();

    error_log(" [delete_chat] Удалено сообщений: $deletedMessages");
    
    // Удаляем сам чат

    $stmt = $pdo->prepare("DELETE FROM researcher_chats WHERE id = ?");

    $chatResult = $stmt->execute([$chatId]);

    $deletedChats = $stmt->rowCount();

    error_log(" [delete_chat] Удалено чатов: $deletedChats");
    
    // Проверяем результат

    if ($messagesResult && $chatResult && $deletedChats > 0) {

        error_log(" [delete_chat] Удаление успешно завершено");

        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

    } else {

        error_log(" [delete_chat] Ошибка: messages_result=$messagesResult, chat_result=$chatResult, deleted_chats=$deletedChats");

        throw new Exception("Failed to delete chat - no rows affected");

    }
    
} catch (Exception $e) {

    error_log(" [delete_chat] Ошибка: " . $e->getMessage());

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

}

?>
