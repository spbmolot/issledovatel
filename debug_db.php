<?php
require_once 'config/database.php';

echo "=== Структура таблицы researcher_chat_messages ===\n";
$stmt = $pdo->query('DESCRIBE researcher_chat_messages');
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
}

echo "\n=== Первые 3 записи ===\n";
$stmt = $pdo->query('SELECT * FROM researcher_chat_messages LIMIT 3');
while($row = $stmt->fetch()) {
    print_r($row);
    echo "---\n";
}
?>
