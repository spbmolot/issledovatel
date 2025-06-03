<?php
// api/save_settings.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $openaiKey = $input['openai_key'] ?? '';
    $yandexToken = $input['yandex_token'] ?? '';
    $proxyUrl = $input['proxy_url'] ?? null;
    $yandexFolder = $input['yandex_folder'] ?? '/Прайсы';
    
    if (empty($openaiKey) || empty($yandexToken)) {
        http_response_code(400);
        echo json_encode(['error' => 'OpenAI key and Yandex token are required']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO researcher_settings (openai_key, yandex_token, proxy_url, yandex_folder) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        openai_key = VALUES(openai_key),
        yandex_token = VALUES(yandex_token),
        proxy_url = VALUES(proxy_url),
        yandex_folder = VALUES(yandex_folder),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([$openaiKey, $yandexToken, $proxyUrl, $yandexFolder]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// api/check_status.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/OpenAIClient.php';
require_once __DIR__ . '/../classes/YandexDiskClient.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    
    $status = [
        'openai' => false,
        'yandex' => false
    ];
    
    if ($settings) {
        if (!empty($settings['openai_key'])) {
            $openAI = new OpenAIClient($settings['openai_key'], $settings['proxy_url']);
            $status['openai'] = $openAI->testConnection();
        }
        
        if (!empty($settings['yandex_token'])) {
            $yandexDisk = new YandexDiskClient($settings['yandex_token']);
            $status['yandex'] = $yandexDisk->testConnection();
        }
    }
    
    echo json_encode($status);
} catch (Exception $e) {
    echo json_encode(['openai' => false, 'yandex' => false]);
}
?>

<?php
// api/create_chat.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $title = $input['title'] ?? 'Новый чат';
    
    $stmt = $pdo->prepare("INSERT INTO chats (title) VALUES (?)");
    $stmt->execute([$title]);
    
    $chatId = $pdo->lastInsertId();
    
    echo json_encode([
        'id' => $chatId,
        'title' => $title,
        'created_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// api/get_chats.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(cm.id) as message_count,
               MAX(cm.created_at) as last_message_at
        FROM chats c 
        LEFT JOIN chat_messages cm ON c.id = cm.chat_id 
        GROUP BY c.id 
        ORDER BY COALESCE(last_message_at, c.created_at) DESC 
        LIMIT 50
    ");
    $stmt->execute();
    
    $chats = $stmt->fetchAll();
    echo json_encode($chats);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// api/get_chat.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Chat ID required']);
    exit;
}

try {
    $chatId = (int)$_GET['id'];
    
    // Get chat info
    $stmt = $pdo->prepare("SELECT * FROM chats WHERE id = ?");
    $stmt->execute([$chatId]);
    $chat = $stmt->fetch();
    
    if (!$chat) {
        http_response_code(404);
        echo json_encode(['error' => 'Chat not found']);
        exit;
    }
    
    // Get messages
    $stmt = $pdo->prepare("
        SELECT type, message as text, sources, created_at 
        FROM chat_messages 
        WHERE chat_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$chatId]);
    $messages = $stmt->fetchAll();
    
    // Decode JSON sources
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

<?php
// api/save_message.php
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
        INSERT INTO chat_messages (chat_id, type, message, sources) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $chatId,
        $type,
        $text,
        json_encode($sources, JSON_UNESCAPED_UNICODE)
    ]);
    
    // Update chat timestamp
    $pdo->prepare("UPDATE chats SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$chatId]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<?php
// api/download_file.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/YandexDiskClient.php';

header('Content-Type: application/octet-stream');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $filePath = $input['path'] ?? '';
    
    if (empty($filePath)) {
        http_response_code(400);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT yandex_token FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch();
    
    if (!$settings || empty($settings['yandex_token'])) {
        http_response_code(500);
        exit;
    }
    
    $yandexDisk = new YandexDiskClient($settings['yandex_token']);
    $content = $yandexDisk->downloadFile($filePath);
    
    if ($content === null) {
        http_response_code(404);
        exit;
    }
    
    $filename = basename($filePath);
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo $content;
} catch (Exception $e) {
    http_response_code(500);
    exit;
}
?>

<?php
// api/analytics.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $days = $_GET['days'] ?? 30;
    $analytics = getAnalytics($pdo, $days);
    
    // Get popular keywords
    $stmt = $pdo->prepare("
        SELECT keywords, COUNT(*) as usage_count
        FROM query_log 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND keywords IS NOT NULL
        GROUP BY keywords
        ORDER BY usage_count DESC
        LIMIT 10
    ");
    $stmt->execute([$days]);
    $popularKeywords = $stmt->fetchAll();
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as queries
        FROM query_log 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $stmt->execute([$days]);
    $dailyActivity = $stmt->fetchAll();
    
    echo json_encode([
        'summary' => $analytics,
        'popular_keywords' => $popularKeywords,
        'daily_activity' => $dailyActivity
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>