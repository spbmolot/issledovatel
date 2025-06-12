<?php

/**
 * Улучшенная векторизация с поддержкой DeepSeek R1 структурирования
 * Файл → PHPSpreadsheet → Сырой текст → DeepSeek R1 анализ/OpenAI Embedding → Структурированные данные → Векторизация
 */

require_once 'vendor/autoload.php';
require_once 'config/database.php'; // Подключаем MySQL базу Bitrix

use ResearcherAI\Logger;
use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorCacheManager;
use ResearcherAI\FileParser;
use ResearcherAI\CacheManager;

// Функция для отображения прогресс-бара в SSH
function showProgressBar($current, $total, $prefix = '', $width = 50) {
    $percent = round(($current / $total) * 100);
    $filled = round(($width * $current) / $total);
    $empty = $width - $filled;

    $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
    $info = sprintf("%s [%s] %d%% (%d/%d)", $prefix, $bar, $percent, $current, $total);
    
    echo "\r" . $info;
    if ($current == $total) {
        echo "\n";
    }
}

echo "🚀 УЛУЧШЕННАЯ ВЕКТОРИЗАЦИЯ С DEEPSEEK R1 ПОДДЕРЖКОЙ\n";
echo "===============================================\n\n";

try {
    $startTime = time();
    
    // Получаем настройки из MySQL
    echo "🔧 Получаем настройки из MySQL базы данных...\n";
    
    try {
        $settingsStmt = $mysql_pdo->prepare("SELECT setting_key, setting_value FROM researcher_settings");
        $settingsStmt->execute();
        $settingsRows = $settingsStmt->fetchAll();
        
        $settings = array();
        foreach ($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        if (empty($settings)) {
            echo "❌ Настройки не найдены в таблице researcher_settings\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "❌ Ошибка получения настроек: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "✅ Настройки получены из MySQL:\n";
    echo "   - AI Provider: " . ($settings['ai_provider'] ?? 'deepseek') . "\n";
    echo "   - Yandex Folder: " . ($settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ') . "\n";
    echo "   - Yandex Token: [" . strlen($settings['yandex_token']) . " символов]\n\n";

    // Создаем AI провайдер
    $aiProvider = AIProviderFactory::create(
        $settings['ai_provider'] ?? 'deepseek',
        $settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key'],
        !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null
    );

    $yandexClient = new YandexDiskClient($settings['yandex_token']);
    
    // Настройка путей
    $dbBaseDir = __DIR__ . '/db';
    
    // Создаем менеджеры
    $cacheManager = new CacheManager($dbBaseDir);
    $vectorCacheManager = new VectorCacheManager($dbBaseDir);
    $vectorCacheManager->initializeEmbeddingManager($aiProvider);
    $fileParser = new FileParser();

    echo "✅ Компоненты инициализированы\n";
    echo "   - AI Provider: " . get_class($aiProvider) . "\n";
    echo "   - Database: {$dbBaseDir}/cache.sqlite\n\n";

    // Получаем список файлов с Яндекс.Диска
    echo "📥 Получаем список файлов с Яндекс.Диска...\n";
    $files = $yandexClient->listFiles($settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ');

    if (empty($files)) {
        echo "❌ Файлы не найдены на Яндекс.Диске\n";
        exit(1);
    }

    // Фильтруем только Excel файлы
    $excelFiles = array_filter($files, function($file) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($extension, ['xlsx', 'xls']);
    });

    echo "🔍 Найдено Excel файлов: " . count($excelFiles) . " из " . count($files) . " общих файлов\n\n";

    if (empty($excelFiles)) {
        echo "❌ Excel файлы не найдены\n";
        exit(1);
    }

    // Выводим статистику провайдера
    $providerClass = get_class($aiProvider);
    $isDeepSeek = (strpos($providerClass, 'DeepSeek') !== false);
    
    if ($isDeepSeek) {
        echo "🧠 РЕЖИМ: DeepSeek R1 - Структурирование + Векторизация\n";
        echo "   📊 Этапы: Сырой текст → R1 анализ → Структурированные данные → Hash-based векторы\n";
    } else {
        echo "⚡ РЕЖИМ: OpenAI - Прямая векторизация\n";
        echo "   📊 Этапы: Сырой текст → OpenAI embeddings → Векторы\n";
    }
    echo "\n";

    // Обрабатываем все Excel файлы
    $processedFiles = 0;
    $successfulVectorizations = 0;
    $failedVectorizations = 0;
    $totalChunks = 0;
    $vectorizationStartTime = time();

    foreach ($excelFiles as $index => $file) {
        echo "\n📄 [" . ($index+1) . "/" . count($excelFiles) . "] " . $file['name'] . "\n";

        try {
            // Проверяем, есть ли уже кэшированный текст для этого файла
            $cacheKey = md5($file['path']);
            $cachedText = $cacheManager->getCachedText($cacheKey);

            if ($cachedText) {
                echo "   📋 Используем кэшированный текст (размер: " . strlen($cachedText) . " символов)\n";
                $rawText = $cachedText;
            } else {
                // Загружаем файл
                echo "   📥 Загружаем файл с Яндекс.Диска...\n";
                $downloadUrl = $yandexClient->getDownloadUrl($file['path']);
                if (!$downloadUrl) {
                    echo "   ❌ Не удалось получить ссылку для загрузки\n";
                    $failedVectorizations++;
                    continue;
                }

                $tempFile = sys_get_temp_dir() . '/' . $file['name'];

                if (!$yandexClient->downloadFile($downloadUrl, $tempFile)) {
                    echo "   ❌ Ошибка загрузки файла\n";
                    $failedVectorizations++;
                    continue;
                }

                // Извлекаем текст
                echo "   📊 Извлекаем текст из Excel файла...\n";
                $fileContent = file_get_contents($tempFile);
                $extractedData = $fileParser->parse($fileContent, $file['name']);

                // Преобразуем данные в сырой текст
                $rawText = '';
                if (is_array($extractedData) && !empty($extractedData)) {
                    foreach ($extractedData as $row) {
                        if (is_array($row)) {
                            $rawText .= implode(' | ', array_filter($row)) . "\n";
                        } else {
                            $rawText .= $row . "\n";
                        }
                    }
                } else {
                    $rawText = is_string($extractedData) ? $extractedData : '';
                }

                // Удаляем временный файл
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }

                if (empty($rawText)) {
                    echo "   ❌ Не удалось извлечь текст из файла\n";
                    $failedVectorizations++;
                    continue;
                }

                // Сохраняем в кэш
                echo "   💾 Сохраняем текст в кэш...\n";
                $cacheManager->setCache($file['path'], $file['modified'] ?? '', '', $rawText);
            }

            echo "   📏 Размер сырого текста: " . strlen($rawText) . " символов\n";

            // КЛЮЧЕВОЕ УЛУЧШЕНИЕ: Используем новый метод с поддержкой R1
            echo "   🔄 Начинаем улучшенную векторизацию...\n";
            
            if ($vectorCacheManager->storeVectorDataEnhanced($file['path'], $rawText, $aiProvider)) {
                $successfulVectorizations++;
                echo "   ✅ Файл успешно векторизирован\n";
            } else {
                $failedVectorizations++;
                echo "   ❌ Ошибка векторизации\n";
            }

            $processedFiles++;

            // Показываем прогресс-бар
            $elapsedTime = time() - $vectorizationStartTime;
            $eta = $elapsedTime / ($processedFiles + 1) * (count($excelFiles) - $processedFiles);
            $etaStr = gmdate("H:i:s", $eta);
            showProgressBar($processedFiles, count($excelFiles), "Векторизация (ETA: {$etaStr})", 50);

        } catch (Exception $e) {
            echo "   ❌ Ошибка обработки файла: " . $e->getMessage() . "\n";
            $failedVectorizations++;
        }
    }

    // Финальная статистика
    $totalTime = time() - $startTime;
    $vectorizationTime = time() - $vectorizationStartTime;
    
    echo "\n\n🎯 СТАТИСТИКА УЛУЧШЕННОЙ ВЕКТОРИЗАЦИИ:\n";
    echo "=====================================\n";
    echo "⏱️  Общее время выполнения: " . gmdate("H:i:s", $totalTime) . "\n";
    echo "⏱️  Время векторизации: " . gmdate("H:i:s", $vectorizationTime) . "\n";
    echo "📄 Обработано файлов: " . $processedFiles . " из " . count($excelFiles) . "\n";
    echo "✅ Успешно векторизировано файлов: " . $successfulVectorizations . "\n";
    echo "❌ Неудачно векторизировано файлов: " . $failedVectorizations . "\n";
    echo "🎯 Успешность: " . round(($successfulVectorizations / count($excelFiles)) * 100, 1) . "%\n";
    
    // Статистика векторной базы
    $vectorStats = $vectorCacheManager->getVectorizationStats();
    echo "📊 Всего векторизированных файлов в БД: " . $vectorStats['vectorized_files_count'] . "\n";
    
    if ($isDeepSeek) {
        echo "\n🧠 DEEPSEEK R1 ПРЕИМУЩЕСТВА:\n";
        echo "   📈 Структурированные данные для лучшего поиска\n";
        echo "   🎯 Стандартизированные форматы цен и товаров\n";
        echo "   🧹 Очистка от служебной информации\n";
        echo "   🔍 Улучшенная точность векторного поиска\n";
    } else {
        echo "\n⚡ OPENAI ПРЕИМУЩЕСТВА:\n";
        echo "   🚀 Высокая скорость векторизации\n";
        echo "   💎 Качественные семантические векторы\n";
        echo "   🔄 Прямая обработка без промежуточного анализа\n";
    }
    
    echo "\n🚀 ВЕКТОРИЗАЦИЯ ЗАВЕРШЕНА! Система готова к AI-поиску!\n";

} catch (Exception $e) {
    echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

?>
