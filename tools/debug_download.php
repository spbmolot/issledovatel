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
    echo "⬇️ Пробуем загрузить файл...\n";
    $tempPath = "/tmp/" . $testFile['name'];
    echo "   Временный файл: {$tempPath}\n";
    
    $result = $yandexDiskClient->downloadFile($downloadUrl, $tempPath);
    
    if ($result) {
        echo "✅ Файл успешно загружен!\n";
        echo "   Размер загруженного файла: " . filesize($tempPath) . " байт\n";
        
        // Удаляем временный файл
        if (file_exists($tempPath)) {
            unlink($tempPath);
            echo "   Временный файл удален\n";
        }
    } else {
        echo "❌ Ошибка загрузки файла\n";
        
        // Дополнительная диагностика
        echo "\n🔍 ДЕТАЛЬНАЯ ДИАГНОСТИКА ОШИБКИ:\n";
        
        // Проверяем доступность URL напрямую
        echo "🌐 Тестируем download URL напрямую...\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true); // Только заголовки
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $headers = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "   HTTP код: {$httpCode}\n";
        if ($error) {
            echo "   CURL ошибка: {$error}\n";
        }
        
        if ($httpCode === 200) {
            echo "   ✅ URL доступен для загрузки\n";
        } else {
            echo "   ❌ URL недоступен или ошибка: {$httpCode}\n";
            echo "   Заголовки ответа:\n";
            echo "   " . str_replace("\n", "\n   ", trim($headers)) . "\n";
        }
        
        // Проверяем права записи в /tmp
        echo "\n📂 Проверяем права записи...\n";
        if (is_writable('/tmp')) {
            echo "   ✅ Папка /tmp доступна для записи\n";
        } else {
            echo "   ❌ Папка /tmp недоступна для записи\n";
        }
        
        // Пробуем создать тестовый файл
        $testWrite = file_put_contents('/tmp/test_write.txt', 'test');
        if ($testWrite) {
            echo "   ✅ Можем создавать файлы в /tmp\n";
            unlink('/tmp/test_write.txt');
        } else {
            echo "   ❌ Не можем создавать файлы в /tmp\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ОБЩАЯ ОШИБКА: " . $e->getMessage() . "\n";
}

// Выводим последние логи для диагностики
echo "\n📋 ПОСЛЕДНИЕ ЛОГИ СИСТЕМЫ:\n";
if (file_exists('logs/app.log')) {
    $logs = file_get_contents('logs/app.log');
    $logLines = explode("\n", $logs);
    $recentLogs = array_slice($logLines, -10); // Последние 10 строк
    
    foreach ($recentLogs as $line) {
        if (!empty(trim($line))) {
            echo "   " . $line . "\n";
        }
    }
} else {
    echo "   ⚠️ Файл логов не найден (logs/app.log)\n";
}

echo "\n🏁 Диагностика завершена!\n";
