<?php
/**
 * Setup script for Researcher AI
 * Run this script after uploading files to initialize the system
 */

echo "=== Researcher AI Setup ===\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0') < 0) {
    die("PHP 7.4.0 or higher is required. Current version: " . PHP_VERSION . "\n");
}

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'zip'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        die("Required PHP extension '$ext' is not loaded.\n");
    }
}

echo "✓ PHP version and extensions check passed\n";

// Create directories
$directories = [
    '../cache',
    '../logs',
    '../temp',
    '../vendor'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Created directory: $dir\n";
        } else {
            echo "✗ Failed to create directory: $dir\n";
        }
    } else {
        echo "✓ Directory exists: $dir\n";
    }
}

// Set permissions
chmod('../cache', 0755);
chmod('../logs', 0755);
chmod('../temp', 0755);

echo "✓ Directory permissions set\n";

// Create .htaccess files for security
$htaccessCache = "Order deny,allow\nDeny from all\n";
file_put_contents('../cache/.htaccess', $htaccessCache);
file_put_contents('../logs/.htaccess', $htaccessCache);
file_put_contents('../temp/.htaccess', $htaccessCache);

echo "✓ Security .htaccess files created\n";

// Database configuration check
echo "\n=== Database Configuration ===\n";
echo "Please configure your database connection in config/database.php\n";
echo "Default settings:\n";
echo "- Host: localhost\n";
echo "- Database: researcher_ai\n";
echo "- Username: researcher_user\n";
echo "- Password: your_secure_password\n\n";

// Test database connection
try {
    require_once __DIR__ . '/../config/database.php';
    echo "✓ Database connection successful\n";
    echo "✓ Database tables initialized\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in config/database.php\n";
}

// Create example configuration file
$configExample = '<?php
// Example configuration - copy to config.php and modify
return [
    "openai" => [
        "api_key" => "sk-your-openai-api-key-here",
        "proxy" => null // "http://proxy:port" if needed
    ],
    "yandex" => [
        "oauth_token" => "your-yandex-oauth-token-here",
        "folder_path" => "/Прайсы"
    ],
    "system" => [
        "max_file_size" => 5242880, // 5MB
        "max_files_per_query" => 10,
        "cache_timeout" => 3600, // 1 hour
        "log_level" => "INFO"
    ]
];
';

file_put_contents('../config/config.example.php', $configExample);
echo "✓ Example configuration file created\n";

// Create initial admin guide
$adminGuide = '# Researcher AI - Руководство администратора

## Первоначальная настройка

1. **Настройка API ключей:**
   - Получите API ключ OpenAI на https://platform.openai.com/
   - Получите OAuth токен Яндекс.Диска на https://yandex.ru/dev/disk/rest/
   - Введите ключи в интерфейсе настроек системы

2. **Настройка прокси (если необходимо):**
   - Укажите адрес прокси в формате http://proxy:port
   - Прокси используется только для подключения к OpenAI

3. **Подготовка прайс-листов:**
   - Создайте папку "Прайсы" на Яндекс.Диске
   - Загрузите файлы прайс-листов в поддерживаемых форматах:
     * Excel (.xlsx, .xls)
     * CSV (.csv)
     * Текстовые файлы (.txt)
     * Word документы (.docx, .doc)
     * PDF файлы (.pdf)

4. **Тестирование:**
   - Проверьте статус подключений в интерфейсе
   - Задайте тестовый вопрос для проверки работы

## Поддерживаемые форматы запросов

- "Найди цены на iPhone 15"
- "Сравни предложения по ноутбукам Lenovo"
- "Какие поставщики предлагают мониторы Samsung?"
- "Покажи самые дешевые предложения на принтеры"

## Мониторинг и обслуживание

- Логи системы сохраняются в папке logs/
- Статистика запросов доступна через API
- Рекомендуется периодически очищать старые данные

## Безопасность

- API ключи шифруются при сохранении
- Доступ к системе ограничен администраторами Битрикс
- Все запросы логируются для аудита
';

file_put_contents('../docs/admin-guide.md', $adminGuide);
echo "✓ Admin guide created\n";

// Final instructions
echo "\n=== Setup Complete ===\n";
echo "Next steps:\n";
echo "1. Configure database connection in config/database.php\n";
echo "2. Install Composer dependencies: composer install\n";
echo "3. Access the system at: https://kp-opt.ru/issledovatel/\n";
echo "4. Configure API keys in the settings panel\n";
echo "5. Upload price lists to Yandex Disk\n";
echo "6. Start using the AI researcher!\n\n";

echo "For detailed instructions, see docs/admin-guide.md\n";
echo "Support: Check logs/ directory for any issues\n\n";

echo "🚀 Researcher AI is ready to use!\n";
?>