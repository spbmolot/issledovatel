
<?php

require_once 'classes/AIProvider.php';



echo "🧪 Тестируем DeepSeek API...\n\n";



// Создаем тестовый провайдер (нужен реальный ключ для теста)

$testKey = 'sk-test-key'; // Замените на реальный ключ для теста



try {

    $deepseek = AIProviderFactory::create('deepseek', $testKey);

    echo "✅ DeepSeek провайдер создан\n";

    

    // Тестируем подключение

    $connected = $deepseek->testConnection();

    echo "🔗 Подключение: " . ($connected ? "✅ Успешно" : "❌ Ошибка") . "\n";

    

    // Тестируем извлечение ключевых слов

    $keywords = $deepseek->extractKeywords("Найди цены на ламинат");

    echo "🔍 Ключевые слова: " . implode(', ', $keywords) . "\n";

    

} catch (Exception $e) {

    echo "❌ Ошибка: " . $e->getMessage() . "\n";

}



echo "\n✅ Тест завершен!\n";

?>

