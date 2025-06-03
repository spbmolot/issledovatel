
<?php



// Database configuration for Researcher AI

// Using existing Bitrix database connection



// Get Bitrix database settings

$bitrixConfig = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/.settings.php";

$bitrixDbConfig = null;



if (file_exists($bitrixConfig)) {

    $arSettings = include($bitrixConfig);

    if (isset($arSettings['connections']['value']['default'])) {

        $bitrixDbConfig = $arSettings['connections']['value']['default'];

    }

}



// Use Bitrix database settings

if ($bitrixDbConfig) {

    $host = $bitrixDbConfig['host'];

    $dbname = $bitrixDbConfig['database'];

    $username = $bitrixDbConfig['login'];

    $password = $bitrixDbConfig['password'];

} else {

    // Fallback to your specific settings

    $host = 'localhost';

    $dbname = 'ch38922_yl1dy';

    $username = 'ch38922_yl1dy';

    $password = 'PQP0twSH';

}



try {

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [

        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES => false,

        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"

    ]);

} catch (PDOException $e) {

    error_log('Database connection failed: ' . $e->getMessage());

    die('Database connection failed');

}



// Initialize database tables if they don't exist

function initDatabase($pdo) {

    try {

        // Settings table with prefix to avoid conflicts

        $pdo->exec("

            CREATE TABLE IF NOT EXISTS researcher_settings (

                id INT PRIMARY KEY AUTO_INCREMENT,

                openai_key VARCHAR(255) NOT NULL,

                yandex_token VARCHAR(255) NOT NULL,

                proxy_url VARCHAR(255) DEFAULT NULL,

                yandex_folder VARCHAR(255) DEFAULT '/Прайсы',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

        ");

        

        // Chats table with prefix

        $pdo->exec("

            CREATE TABLE IF NOT EXISTS researcher_chats (

                id INT PRIMARY KEY AUTO_INCREMENT,

                title VARCHAR(255) NOT NULL DEFAULT 'Новый чат',

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

        ");

        

        // Chat messages table with prefix

        $pdo->exec("

            CREATE TABLE IF NOT EXISTS researcher_chat_messages (

                id INT PRIMARY KEY AUTO_INCREMENT,

                chat_id INT NOT NULL,

                type ENUM('user', 'assistant') NOT NULL,

                message TEXT NOT NULL,

                sources JSON DEFAULT NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (chat_id) REFERENCES researcher_chats(id) ON DELETE CASCADE,

                INDEX idx_chat_id (chat_id),

                INDEX idx_created_at (created_at)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

        ");

        

        // File cache table with prefix

        $pdo->exec("

            CREATE TABLE IF NOT EXISTS researcher_file_cache (

                id INT PRIMARY KEY AUTO_INCREMENT,

                file_path VARCHAR(500) NOT NULL UNIQUE,

                file_name VARCHAR(255) NOT NULL,

                file_size BIGINT NOT NULL,

                file_hash VARCHAR(64) NOT NULL,

                content_preview TEXT,

                keywords TEXT,

                last_modified TIMESTAMP NULL,

                cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                INDEX idx_file_path (file_path),

                INDEX idx_file_hash (file_hash),

                INDEX idx_last_modified (last_modified)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

        ");

        

        // Query log table for analytics with prefix

        $pdo->exec("

            CREATE TABLE IF NOT EXISTS researcher_query_log (

                id INT PRIMARY KEY AUTO_INCREMENT,

                chat_id INT DEFAULT NULL,

                query_text TEXT NOT NULL,

                keywords TEXT,

                files_processed INT DEFAULT 0,

                processing_time FLOAT DEFAULT 0,

                success BOOLEAN DEFAULT TRUE,

                error_message TEXT DEFAULT NULL,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (chat_id) REFERENCES researcher_chats(id) ON DELETE SET NULL,

                INDEX idx_chat_id (chat_id),

                INDEX idx_created_at (created_at),

                INDEX idx_success (success)

            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

        ");

        

        return true;

    } catch (PDOException $e) {

        error_log('Database initialization failed: ' . $e->getMessage());

        return false;

    }

}



// Initialize the database

initDatabase($pdo);



// Utility functions (updated table names)

function logQuery($pdo, $chatId, $queryText, $keywords, $filesProcessed, $processingTime, $success = true, $errorMessage = null) {

    try {

        $stmt = $pdo->prepare("

            INSERT INTO researcher_query_log (chat_id, query_text, keywords, files_processed, processing_time, success, error_message)

            VALUES (?, ?, ?, ?, ?, ?, ?)

        ");

        $stmt->execute([

            $chatId,

            $queryText,

            is_array($keywords) ? implode(', ', $keywords) : $keywords,

            $filesProcessed,

            $processingTime,

            $success,

            $errorMessage

        ]);

    } catch (PDOException $e) {

        error_log('Failed to log query: ' . $e->getMessage());

    }

}



function getAnalytics($pdo, $days = 30) {

    try {

        $stmt = $pdo->prepare("

            SELECT 

                COUNT(*) as total_queries,

                COUNT(DISTINCT chat_id) as unique_chats,

                AVG(processing_time) as avg_processing_time,

                AVG(files_processed) as avg_files_processed,

                COUNT(CASE WHEN success = 1 THEN 1 END) as successful_queries,

                COUNT(CASE WHEN success = 0 THEN 1 END) as failed_queries

            FROM researcher_query_log 

            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)

        ");

        $stmt->execute([$days]);

        return $stmt->fetch();

    } catch (PDOException $e) {

        error_log('Failed to get analytics: ' . $e->getMessage());

        return null;

    }

}



function cleanOldData($pdo, $daysToKeep = 90) {

    try {

        // Clean old chats without messages

        $pdo->exec("

            DELETE c FROM researcher_chats c 

            LEFT JOIN researcher_chat_messages cm ON c.id = cm.chat_id 

            WHERE cm.id IS NULL AND c.created_at < DATE_SUB(NOW(), INTERVAL $daysToKeep DAY)

        ");

        

        // Clean old query logs

        $pdo->exec("

            DELETE FROM researcher_query_log 

            WHERE created_at < DATE_SUB(NOW(), INTERVAL $daysToKeep DAY)

        ");

        

        // Clean old file cache

        $pdo->exec("

            DELETE FROM researcher_file_cache 

            WHERE cached_at < DATE_SUB(NOW(), INTERVAL 7 DAY)

        ");

        

        return true;

    } catch (PDOException $e) {

        error_log('Failed to clean old data: ' . $e->getMessage());

        return false;

    }

}

?>

