
<?php

require_once 'config/database.php';

require_once 'classes/YandexDiskClient.php';



// Получаем токен из настроек

$stmt = $pdo->prepare("SELECT yandex_token FROM researcher_settings WHERE id = 1");

$stmt->execute();

$settings = $stmt->fetch();



if (!$settings || empty($settings['yandex_token'])) {

    echo "❌ Yandex токен не настроен\n";

    exit;

}



$yandex = new YandexDiskClient($settings['yandex_token']);



echo "🔍 Проверяем папку /2 АКТУАЛЬНЫЕ ПРАЙСЫ...\n";

$files = $yandex->listFiles('/2 АКТУАЛЬНЫЕ ПРАЙСЫ', true);



echo "📁 Найдено файлов: " . count($files) . "\n\n";



foreach ($files as $file) {

    echo "📄 " . $file['name'] . "\n";

    echo "   Размер: " . round($file['size']/1024, 2) . " KB\n";

    echo "   Путь: " . $file['path'] . "\n\n";

}



if (empty($files)) {

    echo "⚠️  Файлы не найдены. Попробуем найти папку...\n";

    

    // Ищем все папки в корне

    echo "🔍 Ищем все папки в корне диска:\n";

    $rootFiles = $yandex->listFiles('/', false);

    foreach ($rootFiles as $file) {

        if (strpos($file['name'], 'ПРАЙС') !== false || strpos($file['name'], 'прайс') !== false) {

            echo "📁 Найдена папка: " . $file['name'] . "\n";

            echo "   Путь: " . $file['path'] . "\n";

        }

    }

}

?>

