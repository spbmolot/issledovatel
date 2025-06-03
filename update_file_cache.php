
<?php

require_once 'config/database.php';

require_once 'classes/YandexDiskClient.php';



// Функция для обновления кэша файлов

function updateFileCache() {

    global $pdo;

    

    $stmt = $pdo->prepare("SELECT yandex_token, yandex_folder FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch();

    

    if (!$settings) return false;

    

    $yandex = new YandexDiskClient($settings['yandex_token']);

    $files = $yandex->listFiles($settings['yandex_folder'], true);

    

    // Очищаем старый кэш

    $pdo->exec("DELETE FROM researcher_file_cache");

    

    // Сохраняем новый список файлов

    $stmt = $pdo->prepare("

        INSERT INTO researcher_file_cache 

        (file_path, file_name, file_size, file_hash, last_modified, cached_at) 

        VALUES (?, ?, ?, ?, ?, NOW())

    ");

    

    foreach ($files as $file) {

        $hash = md5($file['path'] . $file['size'] . $file['modified']);

        $stmt->execute([

            $file['path'],

            $file['name'], 

            $file['size'],

            $hash,

            $file['modified']

        ]);

    }

    

    echo "✅ Кэш обновлен: " . count($files) . " файлов\n";

    return true;

}



updateFileCache();

?>

