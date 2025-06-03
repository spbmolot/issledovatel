
<?php

require_once 'config/database.php';



echo "🔧 Обновляем структуру базы данных...\n";



try {

    // Проверяем существующие колонки

    $result = $pdo->query("DESCRIBE researcher_settings");

    $columns = [];

    while ($row = $result->fetch()) {

        $columns[] = $row['Field'];

    }

    

    // Добавляем недостающие колонки

    if (!in_array('ai_provider', $columns)) {

        $pdo->exec("ALTER TABLE researcher_settings ADD ai_provider VARCHAR(20) DEFAULT 'openai'");

        echo "✅ Добавлена колонка ai_provider\n";

    }

    

    if (!in_array('deepseek_key', $columns)) {

        $pdo->exec("ALTER TABLE researcher_settings ADD deepseek_key VARCHAR(255) DEFAULT NULL");

        echo "✅ Добавлена колонка deepseek_key\n";

    }

    

    if (!in_array('proxy_enabled', $columns)) {

        $pdo->exec("ALTER TABLE researcher_settings ADD proxy_enabled BOOLEAN DEFAULT FALSE");

        echo "✅ Добавлена колонка proxy_enabled\n";

    }

    

    echo "✅ База данных обновлена успешно!\n";

    

} catch (Exception $e) {

    echo "❌ Ошибка: " . $e->getMessage() . "\n";

}

?>

