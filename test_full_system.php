
<?php

require_once 'config/database.php';

require_once 'classes/OpenAIClient.php';

require_once 'classes/YandexDiskClient.php';

require_once 'classes/PriceAnalyzer.php';



echo "🔍 Тестируем полную систему...\n\n";



// Получаем настройки

$stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

$stmt->execute();

$settings = $stmt->fetch();



if (!$settings) {

    echo "❌ Настройки не найдены\n";

    exit;

}



echo "📁 Папка: " . $settings['yandex_folder'] . "\n";

echo "🔑 OpenAI: " . (empty($settings['openai_key']) ? "НЕ НАСТРОЕН" : "НАСТРОЕН") . "\n";

echo "🔑 Yandex: " . (empty($settings['yandex_token']) ? "НЕ НАСТРОЕН" : "НАСТРОЕН") . "\n\n";



// Инициализируем клиенты

$yandexDisk = new YandexDiskClient($settings['yandex_token']);



// Тестируем поиск файлов по ключевым словам

echo "🔍 Тестируем поиск файлов по запросу 'ламинат'...\n";

$keywords = array('ламинат');

$relevantFiles = $yandexDisk->searchFiles($keywords, $settings['yandex_folder']);



echo "📄 Найдено релевантных файлов: " . count($relevantFiles) . "\n\n";



foreach (array_slice($relevantFiles, 0, 3) as $file) {

    echo "📄 " . $file['name'] . "\n";

    echo "   Релевантность: " . round($file['relevance_score'], 2) . "\n";

    echo "   Размер: " . round($file['size']/1024, 2) . " KB\n\n";

}



// Тестируем загрузку файла

if (!empty($relevantFiles)) {

    echo "📥 Тестируем загрузку первого файла...\n";

    $firstFile = $relevantFiles[0];

    $content = $yandexDisk->downloadFile($firstFile['path']);

    

    if ($content) {

        echo "✅ Файл загружен, размер: " . strlen($content) . " байт\n";

        echo "🔍 Первые 200 символов:\n";

        echo substr($content, 0, 200) . "...\n\n";

    } else {

        echo "❌ Ошибка загрузки файла\n";

    }

}



echo "🎉 Тест завершен!\n";

?>

