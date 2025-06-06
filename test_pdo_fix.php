<?php
require_once 'vendor/autoload.php';

use ResearcherAI\CacheManager;
use ResearcherAI\VectorCacheManager;

echo "🧪 Тестируем исправление PDO visibility...\n\n";

try {
    $dbBaseDir = __DIR__ . '/db';
    echo "📂 Директория БД: {$dbBaseDir}\n";
    
    // Создаем директорию если не существует
    if (!is_dir($dbBaseDir)) {
        echo "📁 Создаем директорию db...\n";
        mkdir($dbBaseDir, 0755, true);
    }
    
    // Тестируем базовый CacheManager
    echo "🔍 Тестируем базовый CacheManager:\n";
    $cacheManager = new CacheManager($dbBaseDir);
    echo "✅ CacheManager создан успешно\n";
    
    // Тестируем VectorCacheManager
    echo "\n🔍 Тестируем VectorCacheManager:\n";
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    echo "✅ VectorCacheManager создан успешно\n";
    
    echo "\n🎉 Все тесты прошли успешно! PDO проблема решена.\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
    echo "📋 Стэк вызовов:\n" . $e->getTraceAsString() . "\n";
}
