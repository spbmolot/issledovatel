<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';

use ResearcherAI\Logger;
use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorPriceAnalyzer;
use ResearcherAI\CacheManager;

echo "🚀 Запускаем векторизацию существующих файлов...\n\n";

try {
    // Загружаем настройки
    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        throw new Exception('Настройки не найдены');
    }
    
    // Создаем провайдеры
    $aiApiKey = ($settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key']);
    $proxyUrl = !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null;
    $aiProvider = AIProviderFactory::create($settings['ai_provider'], $aiApiKey, $proxyUrl);
    $yandexDiskClient = new YandexDiskClient($settings['yandex_token']);
    $dbBaseDir = __DIR__ . '/db';
    $cacheManager = new CacheManager($dbBaseDir);
    
    echo "✅ Компоненты инициализированы\n";
    
    // Создаем VectorPriceAnalyzer
    $vectorAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexDiskClient, $cacheManager);
    echo "✅ VectorPriceAnalyzer готов\n\n";
    
    // Получаем список файлов с Яндекс.Диска из настроек
    $folderPath = $settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';
    echo "📁 Получаем список файлов из папки (из настроек): {$folderPath}\n";
    
    $files = $yandexDiskClient->searchFilesByExtension($folderPath, '.xlsx');
    
    if (empty($files)) {
        echo "⚠️ Excel файлы не найдены, пробуем другие форматы...\n";
        
        // Пробуем разные расширения
        $extensions = array('.xls', '.csv', '.txt');
        foreach ($extensions as $ext) {
            $files = $yandexDiskClient->searchFilesByExtension($folderPath, $ext);
            if (!empty($files)) {
                echo "✅ Найдены файлы с расширением {$ext}: " . count($files) . "\n";
                break;
            }
        }
    }
    
    if (empty($files)) {
        // Показываем содержимое папки для диагностики
        echo "🔍 Содержимое папки {$folderPath}:\n";
        $allFiles = $yandexDiskClient->listFiles($folderPath);
        if (!empty($allFiles)) {
            foreach ($allFiles as $file) {
                $fileType = isset($file['type']) ? $file['type'] : 'file';
                echo "   " . ($fileType === 'dir' ? '📁' : '📄') . " " . $file['name'] . "\n";
            }
        } else {
            echo "   Папка пуста или недоступна\n";
        }
        exit(1);
    }
    
    echo "📊 Найдено файлов для векторизации: " . count($files) . "\n\n";
    
    $processed = 0;
    $errors = 0;
    
    foreach ($files as $file) {
        echo "🔄 Обрабатываем: " . $file['name'] . "\n";
        
        try {
            // Проверяем, есть ли уже кэшированный текст для этого файла
            $cacheKey = md5($file['path']);
            $cachedText = $cacheManager->getCachedText($cacheKey);
            
            if ($cachedText) {
                echo "   📋 Используем кэшированный текст\n";
                $content = $cachedText;
            } else {
                echo "   📥 Загружаем файл с Яндекс.Диска...\n";
                
                // Получаем download URL через API
                $downloadUrl = $yandexDiskClient->getDownloadUrl($file['path']);
                if (!$downloadUrl) {
                    echo "   ❌ Не удалось получить ссылку для загрузки\n";
                    continue;
                }
                
                // Загружаем файл с Яндекс.Диска
                $tempFilePath = sys_get_temp_dir() . '/' . basename($file['name']);
                $downloadSuccess = $yandexDiskClient->downloadFile($downloadUrl, $tempFilePath);
                
                if (!$downloadSuccess) {
                    echo "   ❌ Не удалось загрузить файл\n";
                    continue;
                }
                
                echo "   📊 Извлекаем текст из Excel файла...\n";
                $content = $vectorAnalyzer->extractTextFromFile($tempFilePath);
                
                // Удаляем временный файл
                if (file_exists($tempFilePath)) {
                    unlink($tempFilePath);
                }
                
                if (empty($content)) {
                    echo "   ❌ Не удалось извлечь текст из файла\n";
                    continue;
                }
                
                echo "   💾 Сохраняем текст в кэш...\n";
                $cacheManager->setCache($file['path'], $file['modified'] ?? '', '', $content);
            }
            
            // Разбиваем на чанки
            $chunks = explode("\n\n", $content);
            $chunks = array_filter($chunks, function($chunk) {
                return strlen(trim($chunk)) > 50;
            });
            
            if (empty($chunks)) {
                $chunks = explode("\n", $content);
                $chunks = array_filter($chunks, function($chunk) {
                    return strlen(trim($chunk)) > 30;
                });
            }
            
            if (empty($chunks)) {
                echo "   ⚠️ Не найдено подходящих чанков\n";
                continue;
            }
            
            // Ограничиваем количество чанков для теста
            $chunks = array_slice($chunks, 0, 5);
            
            // Сохраняем векторные данные
            $result = $vectorAnalyzer->vectorCacheManager->storeVectorData($file['path'], $chunks);
            
            if ($result) {
                echo "   ✅ Векторизировано чанков: " . count($chunks) . "\n";
                $processed++;
            } else {
                echo "   ❌ Ошибка при сохранении векторных данных\n";
                $errors++;
            }
            
            sleep(1);
            
        } catch (Exception $e) {
            echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n🎯 Результаты векторизации:\n";
    echo "   - Обработано файлов: {$processed}\n";
    echo "   - Ошибок: {$errors}\n";
    
    $stats = $vectorAnalyzer->getVectorSearchStats();
    echo "\n📊 Статистика:\n";
    echo "   - Векторизованных файлов: " . $stats['vectorized_files_count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
