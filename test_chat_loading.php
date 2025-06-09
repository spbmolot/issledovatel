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
    // Подключаемся к базе данных
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

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

    echo "<h3>Сообщения чата (" . count($messages) . " шт.):</h3>";
    
    foreach ($messages as $message) {
        echo "<div style='border: 1px solid #ddd; margin: 10px 0; padding: 10px;'>";
        echo "<strong>ID:</strong> " . $message['id'] . "<br>";
        echo "<strong>Тип:</strong> " . $message['type'] . "<br>";
        echo "<strong>Сообщение:</strong> " . htmlspecialchars(substr($message['message'], 0, 100)) . "...<br>";
        echo "<strong>Sources:</strong> " . htmlspecialchars($message['sources'] ?? 'NULL') . "<br>";
        echo "<strong>Время:</strong> " . $message['created_at'] . "<br>";
        
        // Проверяем поле sources
        if (!empty($message['sources'])) {
            echo "<strong>Парсинг Sources:</strong> ";
            try {
                $sources = json_decode($message['sources'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "✅ OK<br>";
                } else {
                    echo "❌ JSON ERROR: " . json_last_error_msg() . "<br>";
                }
            } catch (Exception $e) {
                echo "❌ EXCEPTION: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "<strong>Парсинг Sources:</strong> ✅ Empty (OK)<br>";
        }
        
        echo "</div>";
    }

    // Тестируем API напрямую
    echo "<h3>Тест API get_chat.php:</h3>";
    
    $apiUrl = "http://localhost/issledovatel/api/get_chat.php?id=$chatId";
    echo "<p>URL: $apiUrl</p>";
    
    // Делаем запрос к API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<strong>HTTP Code:</strong> $httpCode<br>";
    if ($error) {
        echo "<strong>CURL Error:</strong> $error<br>";
    }
    
    echo "<strong>Raw Response:</strong><br>";
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // Пробуем распарсить JSON
    if ($response) {
        echo "<strong>JSON Parsing:</strong> ";
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ OK<br>";
            echo "<pre>";
            print_r($data);
            echo "</pre>";
        } else {
            echo "❌ JSON ERROR: " . json_last_error_msg() . "<br>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
    h1, h2, h3 { color: #333; }
</style>
