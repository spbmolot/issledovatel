<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>🧪 Прямое тестирование удаления чата</h2>\n";

try {
    // Получаем список чатов
    $stmt = $pdo->prepare("SELECT id, title FROM researcher_chats ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $chats = $stmt->fetchAll();
    
    echo "<h3>📋 Доступные чаты:</h3>\n";
    foreach ($chats as $chat) {
        echo "<p>ID: {$chat['id']}, Название: {$chat['title']}</p>\n";
    }
    
    if (empty($chats)) {
        echo "<p>❌ Нет чатов для тестирования</p>\n";
        exit;
    }
    
    // Берем последний чат
    $testChatId = $chats[0]['id'];
    echo "<h3>🗑️ Тестируем удаление чата ID: $testChatId</h3>\n";
    
    // Имитируем POST запрос как в API
    $chatId = $testChatId;
    
    echo "<h4>🔍 Шаг 1: Проверяем существование чата</h4>\n";
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM researcher_chats WHERE id = ?");
    $checkStmt->execute([$chatId]);
    $chatExists = $checkStmt->fetch()['count'];
    echo "<p>Чат существует: $chatExists</p>\n";
    
    if ($chatExists == 0) {
        echo "<p style='color: red'>❌ Чат ID:$chatId не найден в базе</p>\n";
        exit;
    }
    
    echo "<h4>📝 Шаг 2: Удаляем сообщения чата</h4>\n";
    $stmt = $pdo->prepare("DELETE FROM researcher_chat_messages WHERE chat_id = ?");
    $messagesResult = $stmt->execute([$chatId]);
    $deletedMessages = $stmt->rowCount();
    echo "<p>Результат выполнения: " . ($messagesResult ? 'TRUE' : 'FALSE') . "</p>\n";
    echo "<p>Удалено сообщений: $deletedMessages</p>\n";
    
    echo "<h4>💬 Шаг 3: Удаляем сам чат</h4>\n";
    $stmt = $pdo->prepare("DELETE FROM researcher_chats WHERE id = ?");
    $chatResult = $stmt->execute([$chatId]);
    $deletedChats = $stmt->rowCount();
    echo "<p>Результат выполнения: " . ($chatResult ? 'TRUE' : 'FALSE') . "</p>\n";
    echo "<p>Удалено чатов: $deletedChats</p>\n";
    
    echo "<h4>✅ Шаг 4: Проверяем результат</h4>\n";
    if ($messagesResult && $chatResult && $deletedChats > 0) {
        echo "<p style='color: green'>✅ Удаление успешно завершено</p>\n";
    } else {
        echo "<p style='color: red'>❌ Ошибка удаления:</p>\n";
        echo "<p>messages_result = " . ($messagesResult ? 'TRUE' : 'FALSE') . "</p>\n";
        echo "<p>chat_result = " . ($chatResult ? 'TRUE' : 'FALSE') . "</p>\n";
        echo "<p>deleted_chats = $deletedChats</p>\n";
    }
    
    echo "<h4>🔍 Шаг 5: Проверяем что чат действительно удален</h4>\n";
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM researcher_chats WHERE id = ?");
    $checkStmt->execute([$chatId]);
    $stillExists = $checkStmt->fetch()['count'];
    echo "<p>Чат все еще существует: $stillExists</p>\n";
    
    if ($stillExists == 0) {
        echo "<p style='color: green'>✅ Чат действительно удален из базы</p>\n";
    } else {
        echo "<p style='color: red'>❌ ПРОБЛЕМА: Чат все еще в базе данных!</p>\n";
    }
    
    echo "<h3>📋 Список чатов после удаления:</h3>\n";
    $stmt = $pdo->prepare("SELECT id, title FROM researcher_chats ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $chatsAfter = $stmt->fetchAll();
    
    foreach ($chatsAfter as $chat) {
        $highlight = ($chat['id'] == $testChatId) ? "style='color: red; font-weight: bold'" : "";
        echo "<p $highlight>ID: {$chat['id']}, Название: {$chat['title']}</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red'>❌ Ошибка: " . $e->getMessage() . "</p>\n";
    echo "<p>Трассировка: " . $e->getTraceAsString() . "</p>\n";
}
?>
