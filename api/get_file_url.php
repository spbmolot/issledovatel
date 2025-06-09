<?php
/**
 * API для получения публичных ссылок на файлы в Яндекс.Диске
 */

require_once '../vendor/autoload.php';
require_once '../config/database.php';

use ResearcherAI\YandexDiskClient;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['fileName'])) {
        throw new Exception('Не указано имя файла');
    }
    
    $fileName = $input['fileName'];
    
    // Получаем настройки из базы данных
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT setting_value FROM researcher_settings WHERE setting_key = ?");
    
    $stmt->execute(['yandex_token']);
    $token = $stmt->fetchColumn();
    
    $stmt->execute(['yandex_folder']);
    $folder = $stmt->fetchColumn() ?: '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';
    
    if (!$token) {
        throw new Exception('Токен Яндекс.Диска не найден');
    }
    
    // Создаем клиент Яндекс.Диска
    $yandexDisk = new YandexDiskClient($token);
    
    // Получаем информацию о файле
    $filePath = rtrim($folder, '/') . '/' . $fileName;
    $fileInfo = $yandexDisk->getFileInfo($filePath);
    
    if (!$fileInfo) {
        throw new Exception('Файл не найден: ' . $fileName);
    }
    
    // Пытаемся получить публичную ссылку
    $publicUrl = null;
    
    // Если файл уже опубликован, используем его публичную ссылку
    if (isset($fileInfo['public_url'])) {
        $publicUrl = $fileInfo['public_url'];
    } else {
        // Пытаемся опубликовать файл и получить ссылку
        try {
            $publicUrl = $yandexDisk->publishFile($filePath);
        } catch (Exception $e) {
            // Если публикация не удалась, используем ссылку для скачивания
            $publicUrl = $yandexDisk->getDownloadUrl($filePath);
        }
    }
    
    if (!$publicUrl) {
        // Fallback: создаем ссылку для поиска файла в веб-интерфейсе
        $encodedFolder = urlencode(ltrim($folder, '/'));
        $encodedFileName = urlencode($fileName);
        $publicUrl = "https://disk.yandex.ru/client/disk/{$encodedFolder}?idApp=client&dialog=slider&idDialog=%2Fdisk%2F{$encodedFolder}%2F{$encodedFileName}";
    }
    
    echo json_encode([
        'success' => true,
        'url' => $publicUrl,
        'fileName' => $fileName,
        'filePath' => $filePath
    ]);
    
} catch (Exception $e) {
    error_log("Ошибка get_file_url.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
