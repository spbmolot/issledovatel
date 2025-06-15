<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use ResearcherAI\AIProviderFactory;
use ResearcherAI\YandexDiskClient;
use ResearcherAI\VectorCacheManager;
use ResearcherAI\FileParser;
use ResearcherAI\CacheManager;
use ResearcherAI\Logger;

try {
    Logger::info("[vectorize_file] Запрос на векторизацию файла: {$path}");
    $path = $_GET['path'] ?? '';
    if (empty($path)) {
        throw new Exception('Missing path');
    }

    // Настройки
    $settings = $pdo->query("SELECT * FROM researcher_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$settings) {
        throw new Exception('Settings not found');
    }

    $aiProvider = AIProviderFactory::create(
        $settings['ai_provider'] ?? 'deepseek',
        ($settings['ai_provider'] === 'openai') ? $settings['openai_key'] : ($settings['deepseek_key'] ?? $settings['openai_key']),
        (!empty($settings['proxy_enabled']) && !empty($settings['proxy_url'])) ? $settings['proxy_url'] : null
    );

    $yandex = new YandexDiskClient($settings['yandex_token']);
    $baseDir = __DIR__ . '/../db';
    $cacheManager  = new CacheManager($baseDir);
    $vectorManager = new VectorCacheManager($baseDir);
    $vectorManager->initializeEmbeddingManager($aiProvider);
    $parser = new FileParser();

    // Получаем метаданные
    $info = $yandex->stat($path); // нужно реализовать stat() если нет, временно fallback
    if (!$info) {
        throw new Exception('Unable to stat file');
    }

    // Проверяем статус
    $status = $vectorManager->checkVectorStatus($path, $info['modified'] ?? '', $info['md5'] ?? '');
    if ($status === 'UP_TO_DATE') {
        Logger::info("[vectorize_file] Файл уже векторизирован и актуален: {$path}");
        echo json_encode(['status' => 'already_up_to_date']);
        exit;
    }
    if ($status === 'CHANGED') {
        Logger::info("[vectorize_file] Файл изменён, удаляем старые вектора: {$path}");
        $vectorManager->deleteEmbeddings($path);
    }

    // Кэшированный текст?
    $cacheKey = md5($path);
    $text = $cacheManager->getCachedText($cacheKey);
    if (!$text) {
        $tmp = tempnam(sys_get_temp_dir(),'yf');
        $downloadUrl = $yandex->getDownloadUrl($path);
        if (!$yandex->downloadFile($downloadUrl,$tmp)) {
            throw new Exception('Download failed');
        }
        $textData = $parser->parse(file_get_contents($tmp), $info['name']);
        $text = is_array($textData) ? implode("\n", array_map(function($r){return is_array($r)?implode(' | ',$r):$r;},$textData)) : $textData;
        unlink($tmp);
        $cacheManager->setCache($path, $info['modified']??'',$info['md5']??'', $text);
    }

    if (!$vectorManager->storeVectorDataEnhanced($path,$text,$aiProvider)) {
        Logger::error("[vectorize_file] Ошибка при сохранении векторов для файла: {$path}");
        throw new Exception('Vectorize failed');
    }
    $vectorManager->markVectorized($path, $info['modified']??'',$info['md5']??'');
    Logger::info("[vectorize_file] Векторизация завершена успешно для файла: {$path}");

    echo json_encode(['status'=>'vectorized']);
} catch (Throwable $e) {
    Logger::error("[vectorize_file] Исключение: " . $e->getMessage(), $e);
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
