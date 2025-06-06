<?php

require_once 'vendor/autoload.php';

use ResearcherAI\YandexDiskClient;

echo "Тестируем метод getDownloadUrl...\n";

try {
    // Создаем экземпляр класса
    $client = new YandexDiskClient('test_token');
    
    // Проверяем, существует ли метод getDownloadUrl
    if (method_exists($client, 'getDownloadUrl')) {
        echo "✅ Метод getDownloadUrl существует\n";
        
        // Также проверяем downloadFile
        if (method_exists($client, 'downloadFile')) {
            echo "✅ Метод downloadFile существует\n";
        } else {
            echo "❌ Метод downloadFile НЕ существует\n";
        }
        
    } else {
        echo "❌ Метод getDownloadUrl НЕ существует - требуется синхронизация!\n";
    }
    
    // Выводим все методы класса
    $methods = get_class_methods($client);
    echo "\nМетоды класса YandexDiskClient (" . count($methods) . "):\n";
    foreach ($methods as $method) {
        echo "   - {$method}\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}

echo "\nПроверка завершена!\n";
