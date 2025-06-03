
<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);



echo "=== Testing Classes ===\n";



// Test autoload

echo "1. Testing autoload...\n";

if (file_exists('vendor/autoload.php')) {

    require_once 'vendor/autoload.php';

    echo "✓ Autoload included\n";

} else {

    echo "✗ Autoload not found\n";

}



// Test direct class loading

echo "\n2. Testing direct class loading...\n";

try {

    require_once 'classes/OpenAIClient.php';

    echo "✓ OpenAIClient.php loaded\n";

    

    $test = new OpenAIClient('test-key');

    echo "✓ OpenAIClient instance created\n";

} catch (Exception $e) {

    echo "✗ OpenAIClient error: " . $e->getMessage() . "\n";

}



try {

    require_once 'classes/YandexDiskClient.php';

    echo "✓ YandexDiskClient.php loaded\n";

    

    $test = new YandexDiskClient('test-token');

    echo "✓ YandexDiskClient instance created\n";

} catch (Exception $e) {

    echo "✗ YandexDiskClient error: " . $e->getMessage() . "\n";

}



echo "\n=== Test Complete ===\n";

?>

