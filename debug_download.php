<?php

require_once 'vendor/autoload.php';

use ResearcherAI\YandexDiskClient;
use ResearcherAI\SettingsManager;
use ResearcherAI\Logger;

echo "🔍 Диагностика загрузки файлов с Яндекс.Диска...\n\n";

try {
    // Получаем настройки
    $settingsManager = new SettingsManager();
    $token = $settingsManager->get('yandex_disk_token');
    $folderPath = $settingsManager->get('yandex_disk_folder');
    
    echo "📋 Настройки:\n";
    echo "   - Токен: " . (empty($token) ? "НЕТ" : "ЕСТЬ (" . strlen($token) . " символов)") . "\n";
    echo "   - Папка: {$folderPath}\n\n";
    
    if (empty($token)) {
        die("❌ Токен Яндекс.Диска не настроен!\n");
    }
    
    // Создаем клиент
    $yandexDiskClient = new YandexDiskClient($token);
    
    // Проверяем соединение
    echo "🔗 Проверяем соединение с Яндекс.Диском...\n";
    if (!$yandexDiskClient->testConnection()) {
        die("❌ Не удалось подключиться к Яндекс.Диску!\n");
    }
    echo "✅ Соединение установлено\n\n";
    
    // Ищем файлы
    echo "📁 Ищем Excel файлы в папке: {$folderPath}\n";
    $files = $yandexDiskClient->searchFilesByExtension($folderPath, '.xlsx');
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
