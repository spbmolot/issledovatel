#!/usr/bin/env php
<?php
// cron/vectorize_new.php
// Автоматическая векторизация новых или изменённых прайсов.
// Запускать по крону, например: */30 * * * * /usr/bin/php /path/to/cron/vectorize_new.php

use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorCacheManager;
use ResearcherAI\FileParser;
use ResearcherAI\CacheManager;
use ResearcherAI\Logger;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Europe/Moscow');

function logMsg(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . "] " . $msg . PHP_EOL;
}

try {
    $baseDir  = __DIR__ . '/../db';

    // Настройки приложения
    /** @var PDO $pdo Получен из config/database.php */
    $settings = $pdo->query("SELECT * FROM researcher_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        throw new Exception('Settings not found');
    }

    // Инициализация провайдера AI
    $aiProvider = AIProviderFactory::create(
        $settings['ai_provider'] ?? 'deepseek',
        ($settings['ai_provider'] === 'openai') ? $settings['openai_key'] : ($settings['deepseek_key'] ?? $settings['openai_key']),
        (!empty($settings['proxy_enabled']) && !empty($settings['proxy_url'])) ? $settings['proxy_url'] : null
    );

    // Классы ядра
    $yandex  = new YandexDiskClient($settings['yandex_token']);
    $vectorManager = new VectorCacheManager($baseDir);
    $vectorManager->initializeEmbeddingManager($aiProvider);
    $cacheManager  = new CacheManager($baseDir);
    $parser        = new FileParser();

    $folder = $settings['yandex_folder'] ?: '/';

    // Получаем все файлы из Яндекс.Диска
    logMsg('Сканируем папку ' . $folder . ' на Яндекс.Диске...');
    $files = $yandex->listFiles($folder);
    logMsg('Найдено файлов: ' . count($files));

    $toProcess = [];
    foreach ($files as $file) {
        $code = $vectorManager->checkVectorStatus($file['path'], $file['modified'] ?? '', $file['md5'] ?? '');
        if (in_array($code, ['NEW', 'CHANGED'])) {
            $toProcess[] = $file;
        }
    }

    if (empty($toProcess)) {
        logMsg('Все файлы уже векторизированы.');
        exit(0);
    }

    logMsg('Файлов к векторизации: ' . count($toProcess));

    foreach ($toProcess as $idx => $file) {
        $path = $file['path'];
        logMsg("[" . ($idx+1) . "/" . count($toProcess) . "] Векторизация: $path");
        try {
            // Проверяем/получаем текст (кэш)
            $cacheKey = md5($path);
            $text = $cacheManager->getCachedText($cacheKey);
            if (!$text) {
                // Скачиваем файл
                $tmp = tempnam(sys_get_temp_dir(), 'yf');
                $downloadUrl = $yandex->getDownloadUrl($path);
                if (!$yandex->downloadFile($downloadUrl, $tmp)) {
                    throw new Exception('Download failed');
                }
                $textData = $parser->parse(file_get_contents($tmp), $file['name']);
                $text = is_array($textData)
                    ? implode("\n", array_map(function ($r) {
                        return is_array($r) ? implode(' | ', $r) : $r;
                    }, $textData))
                    : $textData;
                unlink($tmp);
                $cacheManager->setCache($path, $file['modified'] ?? '', $file['md5'] ?? '', $text);
            }

            // Сохраняем векторные представления
            if (!$vectorManager->storeVectorDataEnhanced($path, $text, $aiProvider)) {
                throw new Exception('Vector save failed');
            }

            // Отмечаем как векторизированный
            $vectorManager->markVectorized($path, $file['modified'] ?? '', $file['md5'] ?? '');
            logMsg('✓ УСПЕХ');
        } catch (Throwable $e) {
            logMsg('❌ ОШИБКА: ' . $e->getMessage());
        }
    }

    logMsg('Готово.');
} catch (Throwable $e) {
    logMsg('FATAL: ' . $e->getMessage());
    exit(1);
}
