<?php
require_once 'vendor/autoload.php';

use ResearcherAI\CacheManager;

echo "🧪 Тестируем базовый CacheManager...\n\n";

try {
    $dbBaseDir = __DIR__ . '/db';
    echo "📂 Директория БД: {$dbBaseDir}\n";
    
    if (!is_dir($dbBaseDir)) {
        echo "📁 Создаем директорию db...\n";
        mkdir($dbBaseDir, 0755, true);
    }
    
    $cacheManager = new CacheManager($dbBaseDir);
    echo "✅ CacheManager создан\n";
    
    $reflection = new ReflectionClass($cacheManager);
    $pdoProperty = $reflection->getProperty('pdo');
    $pdoProperty->setAccessible(true);
    $pdo = $pdoProperty->getValue($cacheManager);
    
    if ($pdo !== null) {
        echo "✅ PDO подключение инициализировано\n";
        echo "📊 Тип PDO: " . get_class($pdo) . "\n";
        
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "📋 Таблицы в БД: " . implode(', ', $tables) . "\n";
    } else {
        echo "❌ PDO подключение null\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";
}
