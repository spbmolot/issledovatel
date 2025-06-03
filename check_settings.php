
<?php

require_once 'config/database.php';



$stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

$stmt->execute();

$settings = $stmt->fetch();



if ($settings) {

    echo "📁 Папка с прайсами: " . $settings['yandex_folder'] . "\n";

    echo "🔑 OpenAI ключ: " . (empty($settings['openai_key']) ? "НЕ НАСТРОЕН" : "НАСТРОЕН (***" . substr($settings['openai_key'], -4) . ")") . "\n";

    echo "🔑 Yandex токен: " . (empty($settings['yandex_token']) ? "НЕ НАСТРОЕН" : "НАСТРОЕН (***" . substr($settings['yandex_token'], -4) . ")") . "\n";

    echo "🌐 Прокси: " . ($settings['proxy_url'] ?: "НЕ НАСТРОЕН") . "\n";

} else {

    echo "❌ Настройки не найдены\n";

}

?>

