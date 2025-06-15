<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorCacheManager;

try {
    // Получаем настройки
    $settings = $pdo->query("SELECT * FROM researcher_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        throw new Exception('Settings not found');
    }

    $yandexToken  = $settings['yandex_token'];
    $yandexFolder = $settings['yandex_folder'] ?: '/';

    $baseDir = __DIR__ . '/../db';
    $vectorManager = new VectorCacheManager($baseDir);
    $yandexClient  = new YandexDiskClient($yandexToken);

    // Получаем список всех файлов (рекурсивно)
    $files = $yandexClient->listFiles($yandexFolder);

    $result = [];
    foreach ($files as $file) {
        $code = $vectorManager->checkVectorStatus($file['path'], $file['modified'] ?? '', $file['md5'] ?? '');
        switch ($code) {
            case 'UP_TO_DATE': $status = 'ok'; break;
            case 'CHANGED':   $status = 'outdated'; break;
            case 'NEW':       $status = 'missing'; break;
            default:          $status = 'missing';
        }
        $result[] = [
            'path'     => $file['path'],
            'name'     => $file['name'],
            'modified' => $file['modified'] ?? '',
            'status'   => $status
        ];
    }

    echo json_encode(['files' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
