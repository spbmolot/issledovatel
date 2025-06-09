<?php
/**
 * Тест API чатов
 */

echo "🧪 Тестирование API чатов...\n\n";

// Тест 1: get_chats.php
echo "1️⃣ Тестируем get_chats.php:\n";
$url = 'https://kp-opt.ru/issledovatel/api/get_chats.php';
$response = file_get_contents($url);

if ($response === false) {
    echo "❌ Ошибка получения чатов\n";
} else {
    $chats = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Получено чатов: " . count($chats) . "\n";
        if (!empty($chats)) {
            echo "📋 Первый чат:\n";
            $firstChat = $chats[0];
            echo "  ID: " . ($firstChat['id'] ?? 'нет') . "\n";
            echo "  Название: " . ($firstChat['title'] ?? 'нет') . "\n";
            echo "  Сообщений: " . ($firstChat['message_count'] ?? 'нет') . "\n";
        }
    } else {
        echo "❌ Ошибка JSON: " . json_last_error_msg() . "\n";
        echo "Ответ: " . substr($response, 0, 500) . "\n";
    }
}

echo "\n";

// Тест 2: get_chat.php с ID
if (!empty($chats) && isset($chats[0]['id'])) {
    $chatId = $chats[0]['id'];
    echo "2️⃣ Тестируем get_chat.php?id=$chatId:\n";
    
    $url = "https://kp-opt.ru/issledovatel/api/get_chat.php?id=$chatId";
    $response = file_get_contents($url);
    
    if ($response === false) {
        echo "❌ Ошибка получения чата\n";
    } else {
        $chat = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ Чат загружен\n";
            echo "  Название: " . ($chat['title'] ?? 'нет') . "\n";
            echo "  Сообщений в чате: " . (isset($chat['messages']) ? count($chat['messages']) : 'нет') . "\n";
        } else {
            echo "❌ Ошибка JSON: " . json_last_error_msg() . "\n";
            echo "Ответ: " . substr($response, 0, 500) . "\n";
        }
    }
}

echo "\n🎯 Тест завершен!\n";
?>
