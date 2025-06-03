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