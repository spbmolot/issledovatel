<?php
// Проверяем структуру БД SQLite
$dbPath = __DIR__ . '/db/cache.sqlite';

if (!file_exists($dbPath)) {
    echo "❌ Файл БД не найден: {$dbPath}\n";
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    
    echo "🔍 Структура базы данных:\n\n";
    
    // Получаем список всех таблиц
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "❌ Таблицы не найдены в БД\n";
        exit(1);
    }
    
    echo "📊 Найденные таблицы:\n";
    foreach ($tables as $table) {
        echo "  - {$table}\n";
        
        // Показываем структуру каждой таблицы
        $stmt = $pdo->query("PRAGMA table_info({$table})");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($columns)) {
            echo "    Колонки:\n";
            foreach ($columns as $column) {
                echo "      • {$column['name']} ({$column['type']})\n";
            }
        }
        
        // Показываем количество записей
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $stmt->fetch()['count'];
        echo "    Записей: {$count}\n\n";
    }
    
    // Проверяем есть ли настройки где-то еще
    echo "🔍 Поиск настроек в других таблицах:\n";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT * FROM {$table} LIMIT 3");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                echo "  📄 {$table}:\n";
                foreach ($rows as $i => $row) {
                    if ($i >= 2) break; // Показываем только первые 2 записи
                    echo "    [" . ($i+1) . "] " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
                }
                echo "\n";
            }
        } catch (Exception $e) {
            echo "    ❌ Ошибка чтения {$table}: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
?>
