<?php

require_once 'vendor/autoload.php';
require_once 'config/database.php';

echo "🔍 Получаем настройки из базы данных...\n\n";

try {
    // Используем подключение из config/database.php
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        echo "❌ Настройки не найдены в таблице researcher_settings\n";
        
        // Проверяем какие таблицы есть
        echo "\n📋 Доступные таблицы:\n";
        $tables = $pdo->query("SHOW TABLES LIKE '%researcher%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            echo "   - {$table}\n";
        }
        
        if (empty($tables)) {
            echo "   Таблицы с префиксом 'researcher' не найдены\n";
        }
        
    } else {
        echo "✅ Настройки найдены:\n";
        foreach ($settings as $key => $value) {
            if (in_array($key, ['yandex_token', 'openai_key', 'deepseek_key'])) {
                // Скрываем ключи, показываем только длину
                if (!empty($value)) {
                    echo "   - {$key}: [" . strlen($value) . " символов]\n";
                } else {
                    echo "   - {$key}: ПУСТОЙ\n";
                }
            } else {
                echo "   - {$key}: {$value}\n";
            }
        }
        
        // Выводим токен для использования в debug_download.php
        if (!empty($settings['yandex_token'])) {
            echo "\n🔑 Токен Яндекс.Диска для debug_download.php:\n";
            echo $settings['yandex_token'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n";
}

echo "\n🏁 Готово!\n";
