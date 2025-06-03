<?php
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/test_write.log';
$message = "[" . date('Y-m-d H:i:s') . "] PHP test write successful." . PHP_EOL;

echo "Attempting to write to: " . $logFile . "<br>";

if (!is_dir($logDir)) {
    echo "Log directory $logDir does not exist.<br>";
    if (mkdir($logDir, 0775, true)) {
        echo "Log directory $logDir created.<br>";
    } else {
        echo "Failed to create log directory $logDir.<br>";
    }
}

if (!is_writable($logDir)) {
    echo "Log directory $logDir is NOT writable.<br>";
} else {
    echo "Log directory $logDir IS writable.<br>";
}

if (file_put_contents($logFile, $message, FILE_APPEND)) {
    echo "Successfully wrote to $logFile.<br>";
    echo "Content written: $message<br>";
} else {
    echo "Failed to write to $logFile.<br>";
    $error = error_get_last();
    if ($error) {
        echo "PHP Error: " . $error['message'] . "<br>";
    }
}

echo "<br>Current script owner: " . get_current_user() . "<br>";
echo "Current script UID: " . getmyuid() . "<br>";
echo "Current script GID: " . getmygid() . "<br>";

phpinfo(); // Временно для проверки настроек error_log и disable_functions
?>