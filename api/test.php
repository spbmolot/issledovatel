
<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);



echo "Current directory: " . __DIR__ . "\n";

echo "Config file path: " . __DIR__ . '/../config/database.php' . "\n";

echo "Config exists: " . (file_exists(__DIR__ . '/../config/database.php') ? 'YES' : 'NO') . "\n";



if (file_exists(__DIR__ . '/../config/database.php')) {

    try {

        require_once __DIR__ . '/../config/database.php';

        echo "Database connection: SUCCESS\n";

    } catch (Exception $e) {

        echo "Database error: " . $e->getMessage() . "\n";

    }

}

?>

