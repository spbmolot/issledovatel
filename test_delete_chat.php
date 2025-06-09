<?php
require_once __DIR__ . '/config/database.php';

echo "<h2>🧪 Тест удаления чата</h2>\n";

try {
    // Сначала получим список чатов
    echo "<h3>📋 Список чатов перед удалением:</h3>\n";
    $stmt = $pdo->prepare("SELECT id, title, created_at FROM researcher_chats ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $chats = $stmt->fetchAll();
    
    if (empty($chats)) {
        echo "<p>❌ Нет чатов для тестирования</p>\n";
        exit;
    }
    
    foreach ($chats as $chat) {
        echo "<p>ID: {$chat['id']}, Название: {$chat['title']}, Создан: {$chat['created_at']}</p>\n";
    }
    
    // Берем последний чат для удаления
    $testChatId = $chats[0]['id'];
    echo "<h3>🗑️ Удаляем чат ID: $testChatId</h3>\n";
    
    // Считаем сообщения перед удалением
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM researcher_chat_messages WHERE chat_id = ?");
    $stmt->execute([$testChatId]);
    $messageCount = $stmt->fetch()['count'];
    echo "<p>📝 Сообщений в чате: $messageCount</p>\n";
    
    // Имитируем работу API
    echo "<h3>🔄 Выполняем удаление...</h3>\n";
    
    // Удаляем сообщения
    $stmt = $pdo->prepare("DELETE FROM researcher_chat_messages WHERE chat_id = ?");
    $result1 = $stmt->execute([$testChatId]);
    $deletedMessages = $stmt->rowCount();
    echo "<p>📝 Удалено сообщений: $deletedMessages</p>\n";
    
    // Удаляем чат
    $stmt = $pdo->prepare("DELETE FROM researcher_chats WHERE id = ?");
    $result2 = $stmt->execute([$testChatId]);
    $deletedChats = $stmt->rowCount();
    echo "<p>💬 Удалено чатов: $deletedChats</p>\n";
    
    if ($result1 && $result2 && $deletedChats > 0) {
        echo "<p style='color: green'>✅ Удаление успешно!</p>\n";
    } else {
        echo "<p style='color: red'>❌ Ошибка удаления. Результаты: сообщения=$result1, чаты=$result2, строк_удалено=$deletedChats</p>\n";
    }
    
    // Проверяем список после удаления
    echo "<h3>📋 Список чатов после удаления:</h3>\n";
    $stmt = $pdo->prepare("SELECT id, title, created_at FROM researcher_chats ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $chatsAfter = $stmt->fetchAll();
    
    foreach ($chatsAfter as $chat) {
        echo "<p>ID: {$chat['id']}, Название: {$chat['title']}, Создан: {$chat['created_at']}</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red'>❌ Ошибка: " . $e->getMessage() . "</p>\n";
    error_log("Test delete chat error: " . $e->getMessage());
}
?>
