<?php

/**

 * Скрипт векторизации с детальным debug-логированием

 * Исправлена проблема с получением настроек из правильной базы данных

 */



require_once 'vendor/autoload.php';

require_once 'config/database.php'; // Подключаем MySQL базу Bitrix



use ResearcherAI\Logger;

use ResearcherAI\AIProviderFactory;

use ResearcherAI\YandexDiskClient;

use ResearcherAI\VectorPriceAnalyzer;

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



    // Очищаем строку и выводим новую

    echo "\r" . str_pad($info, 100, ' ') . "\r";

    if ($current == $total) {

        echo "\n"; // Новая строка в конце

    }

}



echo "\n🚀 Запускаем векторизацию с debug-логированием...\n\n";



try {

    // Инициализация компонентов

    $dbBaseDir = __DIR__ . '/db';

    $cacheManager = new CacheManager($dbBaseDir);

    $fileParser = new FileParser();



    // ИСПРАВЛЕНО: Загружаем настройки из MySQL (Bitrix), а не из SQLite

    echo "📊 Загружаем настройки из MySQL базы данных Bitrix...\n";

    $stmt = $pdo->prepare("SELECT * FROM researcher_settings WHERE id = 1");

    $stmt->execute();

    $settings = $stmt->fetch(PDO::FETCH_ASSOC);



    if (!$settings || empty($settings['yandex_token'])) {

        echo "❌ Ошибка: Настройки не найдены или Yandex токен пустой\n";

        echo "   Настройте токен через веб-интерфейс\n";

        exit(1);

    }



    echo "✅ Настройки получены из MySQL:\n";

    echo "   - AI Provider: " . ($settings['ai_provider'] ?? 'openai') . "\n";

    echo "   - Yandex Folder: " . ($settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ') . "\n";

    echo "   - Yandex Token: [" . strlen($settings['yandex_token']) . " символов]\n\n";



    // Создаем AI провайдер

    $aiProvider = AIProviderFactory::create(

        $settings['ai_provider'] ?? 'deepseek',

        $settings['ai_provider'] === 'openai' ? $settings['openai_key'] : $settings['deepseek_key'],

        !empty($settings['proxy_enabled']) && !empty($settings['proxy_url']) ? $settings['proxy_url'] : null

    );



    $yandexClient = new YandexDiskClient($settings['yandex_token']);



    // Создаем VectorCacheManager

    $vectorCacheManager = new VectorCacheManager($dbBaseDir);

    $vectorCacheManager->initializeEmbeddingManager($aiProvider);



    // Создаем VectorPriceAnalyzer

    $vectorAnalyzer = new VectorPriceAnalyzer($aiProvider, $yandexClient, $cacheManager);



    echo "✅ Компоненты инициализированы\n";

    echo "✅ VectorPriceAnalyzer готов\n\n";



    // Получаем список файлов

    $folderPath = $settings['yandex_folder'] ?? '/2 АКТУАЛЬНЫЕ ПРАЙСЫ';

    echo "📁 Получаем список файлов из папки: {$folderPath}\n\n";



    $files = $yandexClient->listFiles($folderPath);

    if (empty($files)) {

        echo "❌ Файлы не найдены в папке {$folderPath}\n";

        exit(1);

    }



    // Фильтруем только Excel файлы

    $excelFiles = array_filter($files, function($file) {

        return strpos($file['name'], '.xlsx') !== false || strpos($file['name'], '.xls') !== false;

    });



    echo "🔍 Найдено Excel файлов: " . count($excelFiles) . " из " . count($files) . " общих файлов\n\n";



    if (empty($excelFiles)) {

        echo "❌ Excel файлы не найдены\n";

        exit(1);

    }



    // Обрабатываем все Excel файлы

    $processedFiles = 0;

    $successfulVectorizations = 0;

    $failedVectorizations = 0;

    $totalChunks = 0;

    $startTime = time();



    foreach ($excelFiles as $index => $file) {

        echo "\n📄 [" . ($index+1) . "/" . count($excelFiles) . "] " . $file['name'] . "\n";



        try {

            // Проверяем, есть ли уже кэшированный текст для этого файла

            $cacheKey = md5($file['path']);

            $cachedText = $cacheManager->getCachedText($cacheKey);



            if ($cachedText) {

                echo "   📋 Используем кэшированный текст\n";

                $content = $cachedText;

            } else {

                // Загружаем файл

                echo "   📥 Загружаем файл с Яндекс.Диска...\n";

                $downloadUrl = $yandexClient->getDownloadUrl($file['path']);

                if (!$downloadUrl) {

                    echo "   ❌ Не удалось получить ссылку для загрузки\n";

                    continue;

                }



                $tempFile = sys_get_temp_dir() . '/' . $file['name'];



                if (!$yandexClient->downloadFile($downloadUrl, $tempFile)) {

                    echo "   ❌ Ошибка загрузки файла\n";

                    continue;

                }



                // Извлекаем текст

                echo "   📊 Извлекаем текст из Excel файла...\n";

                $fileContent = file_get_contents($tempFile);

                $extractedData = $fileParser->parse($fileContent, $file['name']);



                // Преобразуем данные в текст

                $content = '';

                if (is_array($extractedData) && !empty($extractedData)) {

                    foreach ($extractedData as $row) {

                        if (is_array($row)) {

                            $content .= implode(' | ', array_filter($row)) . "\n";

                        } else {

                            $content .= $row . "\n";

                        }

                    }

                } else {

                    $content = is_string($extractedData) ? $extractedData : '';

                }



                // Удаляем временный файл

                if (file_exists($tempFile)) {

                    unlink($tempFile);

                }



                if (empty($content)) {

                    echo "   ❌ Не удалось извлечь текст из файла\n";

                    continue;

                }



                // Сохраняем в кэш

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

                    return strlen(trim($chunk)) > 50;

                });

            }



            if (empty($chunks)) {

                echo "   ⚠️ Не найдено подходящих чанков\n";

                continue;

            }



            echo "   [DEBUG] ✅ Приступаем к векторизации " . count($chunks) . " чанков...\n";



            // Показываем содержимое чанка

            foreach ($chunks as $i => $chunk) {

                echo "   [DEBUG] Чанк #" . ($i + 1) . ": " . substr($chunk, 0, 50) . "...\n";

            }



            // Векторизируем

            if ($vectorAnalyzer->vectorCacheManager->storeVectorData($file['path'], $chunks)) {

                $successfulVectorizations++;

                $totalChunks += count($chunks);

                echo "   ✅ Файл векторизирован: " . count($chunks) . " чанков\n";

            } else {

                $failedVectorizations++;

                echo "   ❌ Ошибка векторизации\n";

            }



            $processedFiles++;



            // Показываем прогресс-бар

            $elapsedTime = time() - $startTime;

            $eta = $elapsedTime / ($processedFiles + 1) * (count($excelFiles) - $processedFiles);

            $etaStr = gmdate("H:i:s", $eta);

            showProgressBar($processedFiles, count($excelFiles), "Обработка файлов (ETA: {$etaStr})", 50);



        } catch (Exception $e) {

            echo "   ❌ Ошибка обработки файла: " . $e->getMessage() . "\n";

            $failedVectorizations++;

        }

    }



    // Статистика с временем выполнения

    $totalTime = time() - $startTime;

    $timeStr = gmdate("H:i:s", $totalTime);



    echo "\n\n🎯 Результаты векторизации:\n";

    echo "   ⏱️  Время выполнения: {$timeStr}\n";

    echo "   📄 Обработано файлов: {$processedFiles} из " . count($excelFiles) . "\n";

    echo "   ✅ Векторизировано файлов: {$successfulVectorizations}\n";

    echo "   ❌ Неудачно векторизировано файлов: {$failedVectorizations}\n";

    echo "   📊 Всего векторизированных чанков: {$totalChunks}\n\n";



    // Финальная статистика из БД

    $stats = $vectorAnalyzer->vectorCacheManager->getVectorizationStats();

    echo "📊 Статистика в БД:\n";

    echo "   - Векторизованных файлов: " . $stats['vectorized_files_count'] . "\n\n";



} catch (Exception $e) {

    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";

    echo "📍 Файл: " . $e->getFile() . " строка " . $e->getLine() . "\n";

    exit(1);

}

?>