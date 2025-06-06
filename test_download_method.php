<?php

require_once 'vendor/autoload.php';
require_once 'config/bootstrap.php';

use ResearcherAI\YandexDiskClient;

echo "🔍 Тестируем метод downloadFile...\n";

try {
    // Создаем экземпляр класса
    $client = new YandexDiskClient('test_token');
    
    // Проверяем, существует ли метод
    if (method_exists($client, 'downloadFile')) {
        echo "✅ Метод downloadFile существует\n";
    } else {
        echo "❌ Метод downloadFile НЕ существует\n";
    }
    
    // Выводим все методы класса
    $methods = get_class_methods($client);
    echo "📋 Методы класса YandexDiskClient:\n";
    foreach ($methods as $method) {
        echo "   - {$method}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
