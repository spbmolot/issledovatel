
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

    

    $chatId = $input['chat_id'] ?? null;

    $type = $input['type'] ?? 'user';

    $text = $input['text'] ?? '';

    $sources = $input['sources'] ?? [];

    

    if (!$chatId || empty($text)) {

        http_response_code(400);

        echo json_encode(['error' => 'Chat ID and text are required']);

        exit;

    }

    

    $stmt = $pdo->prepare("

        INSERT INTO researcher_chat_messages (chat_id, type, message, sources) 

        VALUES (?, ?, ?, ?)

    ");

    $stmt->execute([

        $chatId,

        $type,

        $text,

        json_encode($sources, JSON_UNESCAPED_UNICODE)

    ]);

    

    $pdo->prepare("UPDATE researcher_chats SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$chatId]);

    
    // Автоматическое переименование чата при первом сообщении пользователя
    if ($type === 'user') {
        // Проверяем, это ли первое пользовательское сообщение в чате
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM researcher_chat_messages WHERE chat_id = ? AND type = 'user'");
        $checkStmt->execute([$chatId]);
        $userMessageCount = $checkStmt->fetchColumn();
        
        // Если это первое пользовательское сообщение, обновляем название чата
        if ($userMessageCount == 1) {
            // Обрезаем название до 100 символов для удобства отображения
            $chatTitle = mb_strlen($text) > 100 ? mb_substr($text, 0, 100) . '...' : $text;
            $updateTitleStmt = $pdo->prepare("UPDATE researcher_chats SET title = ? WHERE id = ?");
            $updateTitleStmt->execute([$chatTitle, $chatId]);
        }
    }

    

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()]);

}

?>
