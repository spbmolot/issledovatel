
<?php

require_once 'classes/Logger.php';

require_once 'classes/CacheManager.php';



use ResearcherAI\Logger;

use ResearcherAI\CacheManager;



echo "🚀 Настройка векторной системы...\n";



try {

    // Инициализируем CacheManager для доступа к SQLite

    $dbBaseDir = __DIR__ . '/db';

    $cacheManager = new CacheManager($dbBaseDir);

    

    // Получаем подключение к SQLite

    $reflection = new ReflectionClass($cacheManager);

    $pdoProperty = $reflection->getProperty('pdo');

    $pdoProperty->setAccessible(true);

    $pdo = $pdoProperty->getValue($cacheManager);

    

    // Выполняем SQL команды

    $sql = file_get_contents(__DIR__ . '/setup_vector_db.sql');

    $statements = explode(';', $sql);

    

    foreach ($statements as $statement) {

        $statement = trim($statement);

        if (!empty($statement)) {

            $pdo->exec($statement);

            echo "✅ Выполнено: " . substr($statement, 0, 50) . "...\n";

        }

    }

    

    echo "🎉 Векторная база данных настроена успешно!\n";

    

} catch (Exception $e) {

    echo "❌ Ошибка: " . $e->getMessage() . "\n";

}

