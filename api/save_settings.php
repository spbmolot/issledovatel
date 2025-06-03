
<?php

require_once '../config/database.php';



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: POST, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type');



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    exit(0);

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['error' => 'Method not allowed']);

    exit;

}



try {

    $input = json_decode(file_get_contents('php://input'), true);

    

    if (!$input) {

        throw new Exception('Нет данных для сохранения');

    }

    

    $aiProvider = $input['ai_provider'] ?? 'openai';

    $openaiKey = $input['openai_key'] ?? '';

    $deepseekKey = $input['deepseek_key'] ?? '';

    $yandexToken = $input['yandex_token'] ?? '';

    $proxyEnabled = isset($input['proxy_enabled']) ? (bool)$input['proxy_enabled'] : false;

    $proxyUrl = $input['proxy_url'] ?? null;

    $yandexFolder = $input['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';

    

    // Базовая валидация

    if (empty($yandexToken)) {

        throw new Exception('Yandex токен обязателен');

    }

    

    if ($aiProvider === 'openai' && empty($openaiKey)) {

        throw new Exception('OpenAI ключ обязателен для выбранного провайдера');

    }

    

    if ($aiProvider === 'deepseek' && empty($deepseekKey)) {

        throw new Exception('DeepSeek ключ обязателен для выбранного провайдера');

    }

    

    // Сохраняем настройки

    $stmt = $pdo->prepare("

        INSERT INTO researcher_settings (id, openai_key, deepseek_key, yandex_token, proxy_enabled, proxy_url, yandex_folder, ai_provider) 

        VALUES (1, ?, ?, ?, ?, ?, ?, ?)

        ON DUPLICATE KEY UPDATE 

        openai_key = VALUES(openai_key),

        deepseek_key = VALUES(deepseek_key),

        yandex_token = VALUES(yandex_token),

        proxy_enabled = VALUES(proxy_enabled),

        proxy_url = VALUES(proxy_url),

        yandex_folder = VALUES(yandex_folder),

        ai_provider = VALUES(ai_provider),

        updated_at = CURRENT_TIMESTAMP

    ");

    

    $stmt->execute([$openaiKey, $deepseekKey, $yandexToken, $proxyEnabled, $proxyUrl, $yandexFolder, $aiProvider]);

    

    echo json_encode(['success' => true, 'message' => 'Настройки сохранены']);

    

} catch (Exception $e) {

    error_log('Save settings error: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(['error' => $e->getMessage()]);

}

?>

