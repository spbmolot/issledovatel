<?php

require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\YandexDiskClient;
use ResearcherAI\Logger;

echo "🔍 ДИАГНОСТИКА ЗАГРУЗКИ ФАЙЛОВ С ЯНДЕКС.ДИСКА\n";
echo "============================================\n\n";

// Получаем настройки из базы данных
echo "1️⃣ Получение настроек из базы данных...\n";
try {
    $stmt = $pdo->prepare("SELECT yandex_token, yandex_folder FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings || empty($settings['yandex_token'])) {
        throw new Exception("Настройки не найдены или токен пустой");
    }
    
    $yandexToken = $settings['yandex_token'];
    $yandexFolder = $settings['yandex_folder'];
    
    echo "✅ Настройки получены из базы:\n";
    echo "   - Токен: [" . strlen($yandexToken) . " символов]\n";
    echo "   - Папка: {$yandexFolder}\n\n";
    
} catch (Exception $e) {
    echo "❌ ОШИБКА получения настроек: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    // Создаем клиент
    $yandexDiskClient = new YandexDiskClient($yandexToken);
    
    // Проверяем соединение
    echo "🔗 Проверяем соединение с Яндекс.Диском...\n";
    if (!$yandexDiskClient->testConnection()) {
        die("❌ Не удалось подключиться к Яндекс.Диску!\n");
    }
    echo "✅ Соединение установлено\n\n";
    
    // Ищем файлы
    echo "📁 Ищем Excel файлы в папке: {$yandexFolder}\n";
    $files = $yandexDiskClient->searchFilesByExtension($yandexFolder, '.xlsx');
    echo "📊 Найдено файлов: " . count($files) . "\n\n";
    
    if (empty($files)) {
        die("❌ Файлы не найдены!\n");
    }
    
    // Тестируем первый файл
    $testFile = $files[0];
    echo "🧪 Тестируем файл: {$testFile['name']}\n";
    echo "   - Путь: {$testFile['path']}\n";
    echo "   - Размер: " . (isset($testFile['size']) ? $testFile['size'] : 'неизвестно') . "\n\n";
    
    // Получаем download URL
    echo "🔗 Получаем download URL...\n";
    $downloadUrl = $yandexDiskClient->getDownloadUrl($testFile['path']);
    
    if (!$downloadUrl) {
        echo "❌ Не удалось получить download URL\n";
        echo "   Проверьте логи для подробностей\n";
        die();
    }
    
    echo "✅ Download URL получен\n";
    echo "   URL: " . substr($downloadUrl, 0, 80) . "...\n\n";
    
    // Пробуем загрузить файл
    echo "📥 Пробуем загрузить файл...\n";
    $tempFilePath = sys_get_temp_dir() . '/' . basename($testFile['name']);
    echo "   Временный файл: {$tempFilePath}\n";
    
    $success = $yandexDiskClient->downloadFile($downloadUrl, $tempFilePath);
    
    if ($success && file_exists($tempFilePath)) {
        $fileSize = filesize($tempFilePath);
        echo "✅ Файл успешно загружен!\n";
        echo "   Размер: {$fileSize} байт\n";
        
        // Удаляем тестовый файл
        unlink($tempFilePath);
        echo "   Временный файл удален\n";
    } else {
        echo "❌ Ошибка загрузки файла\n";
        echo "   Проверьте логи для подробностей\n";
        
        if (file_exists($tempFilePath)) {
            echo "   Файл создан, но возможно пустой: " . filesize($tempFilePath) . " байт\n";
            unlink($tempFilePath);
        }
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
    echo "   Файл: " . $e->getFile() . "\n";
    echo "   Строка: " . $e->getLine() . "\n";
}

echo "\n🏁 Диагностика завершена!\n";
