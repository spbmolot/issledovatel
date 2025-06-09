<?php
/**
 * Тест загрузки конкретного чата
 */

// Определяем корневую директорию проекта
define('ROOT_DIR', __DIR__);

// Подключаем конфигурацию базы данных
require_once ROOT_DIR . '/config/database.php';

echo "<h1>Тест загрузки чата</h1>";

// Получаем ID чата из URL параметра
$chatId = $_GET['id'] ?? 19; // По умолчанию чат 19, который работал в прошлых тестах

echo "<h2>Тестируем загрузку чата ID: $chatId</h2>";

try {
    // Используем уже созданное соединение $pdo из config/database.php
    // Больше не нужно создавать новое соединение
    
    // Проверяем существование чата
    $stmt = $pdo->prepare("SELECT * FROM researcher_chats WHERE id = :id");
    $stmt->execute(['id' => $chatId]);
    $chat = $stmt->fetch();

    if (!$chat) {
        echo "<p style='color: red;'>❌ Чат с ID $chatId не найден</p>";
        exit;
    }

    echo "<h3>Информация о чате:</h3>";
    echo "<pre>";
    print_r($chat);
    echo "</pre>";

    // Получаем сообщения чата
    $stmt = $pdo->prepare("SELECT * FROM researcher_chat_messages WHERE chat_id = :chat_id ORDER BY created_at");
    $stmt->execute(['chat_id' => $chatId]);
    $messages = $stmt->fetchAll();

    echo "<h3>Сообщения чата (" . count($messages) . "):</h3>";
    
    foreach ($messages as $message) {
        echo "<hr>";
        echo "<h4>Сообщение #" . $message['id'] . " (" . $message['type'] . ")</h4>";
        echo "<p><strong>Создано:</strong> " . $message['created_at'] . "</p>";
        echo "<p><strong>Текст:</strong></p>";
        echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo htmlspecialchars(substr($message['text'], 0, 200));
        if (strlen($message['text']) > 200) {
            echo "...";
        }
        echo "</div>";
        
        // Проверяем sources
        echo "<p><strong>Sources:</strong> ";
        if (!empty($message['sources'])) {
            try {
                $sources = json_decode($message['sources'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "✅ Валидный JSON (" . count($sources) . " источников)";
                    echo "<pre style='font-size: 10px; max-height: 100px; overflow-y: auto;'>";
                    print_r($sources);
                    echo "</pre>";
                } else {
                    echo "❌ Ошибка JSON: " . json_last_error_msg();
                    echo "<div style='background: #ffeeee; padding: 5px; font-size: 10px;'>";
                    echo htmlspecialchars($message['sources']);
                    echo "</div>";
                }
            } catch (Exception $e) {
                echo "❌ Исключение при парсинге JSON: " . $e->getMessage();
            }
        } else {
            echo "⚪ Пустое поле";
        }
        echo "</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</p>";
    echo "<p>Детали:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h3>Тест API get_chat.php</h3>";

// Тестируем API напрямую
$apiUrl = "https://kp-opt.ru/issledovatel/api/get_chat.php?id=" . $chatId;
echo "<p>Вызываем: <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP код:</strong> $httpCode</p>";

if ($httpCode == 200) {
    echo "<p>✅ API работает корректно</p>";
    echo "<p><strong>Размер ответа:</strong> " . strlen($apiResponse) . " байт</p>";
    
    $jsonData = json_decode($apiResponse, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p>✅ JSON валидный</p>";
        echo "<h4>Структура ответа:</h4>";
        echo "<pre style='max-height: 200px; overflow-y: auto; font-size: 10px;'>";
        print_r($jsonData);
        echo "</pre>";
    } else {
        echo "<p>❌ Ошибка JSON в API ответе: " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ API вернул код $httpCode</p>";
    echo "<p>Ответ:</p>";
    echo "<pre>" . htmlspecialchars($apiResponse) . "</pre>";
}

echo "<hr>";
echo "<p><small>Тест завершен: " . date('Y-m-d H:i:s') . "</small></p>";
?>
