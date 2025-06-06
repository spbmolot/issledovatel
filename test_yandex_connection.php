<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\YandexDiskClient;

echo "🔍 Тестируем подключение к Яндекс.Диску...\n";

try {
    $stmt = $pdo->prepare('SELECT yandex_token, yandex_folder FROM researcher_settings WHERE id = 1');
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $client = new YandexDiskClient($settings['yandex_token']);
    $files = $client->searchFiles($settings['yandex_folder'], '.xlsx');
    
    echo "✅ Найдено Excel файлов: " . count($files) . "\n";
    
    if (!empty($files)) {
        echo "📄 Первый файл: " . $files[0]['name'] . "\n";
        echo "📊 Размер: " . (isset($files[0]['size']) ? $files[0]['size'] : 'неизвестно') . " байт\n";
        
        // Показываем структуру данных файла
        echo "\n🔍 Структура данных файла:\n";
        foreach ($files[0] as $key => $value) {
            echo "   {$key}: " . (is_string($value) ? $value : print_r($value, true)) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
