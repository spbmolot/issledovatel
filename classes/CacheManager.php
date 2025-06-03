<?php

namespace ResearcherAI;

use PDO;
use PDOException;

class CacheManager {
    private $pdo;
    private $dbDirectory;
    private $parsedTextsDirectory;
    private $sqliteFile = 'cache.sqlite';
    private $logger;

    public function __construct($dbBaseDir, Logger $logger = null) {
        $this->dbDirectory = rtrim($dbBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->parsedTextsDirectory = $this->dbDirectory . 'parsed_texts' . DIRECTORY_SEPARATOR;
        $this->logger = $logger ?? new Logger(false); // Используем существующий логгер или создаем заглушку

        $this->_initializeStorage();
        $this->_connectDb();
        $this->_createTable();
    }

    private function _initializeStorage() {
        if (!is_dir($this->dbDirectory)) {
            if (!mkdir($this->dbDirectory, 0777, true)) {
                $this->log('error', 'Failed to create DB directory: ' . $this->dbDirectory);
                throw new \RuntimeException('Failed to create DB directory: ' . $this->dbDirectory);
            }
            $this->log('info', 'Created DB directory: ' . $this->dbDirectory);
        }
        if (!is_dir($this->parsedTextsDirectory)) {
            if (!mkdir($this->parsedTextsDirectory, 0777, true)) {
                $this->log('error', 'Failed to create parsed_texts directory: ' . $this->parsedTextsDirectory);
                throw new \RuntimeException('Failed to create parsed_texts directory: ' . $this->parsedTextsDirectory);
            }
            $this->log('info', 'Created parsed_texts directory: ' . $this->parsedTextsDirectory);
        }
    }

    private function _connectDb() {
        try {
            $dsn = 'sqlite:' . $this->dbDirectory . $this->sqliteFile;
            $this->pdo = new PDO($dsn);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->log('info', 'Successfully connected to SQLite DB: ' . $this->dbDirectory . $this->sqliteFile);
        } catch (PDOException $e) {
            $this->log('error', 'SQLite Connection Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function _createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS cache_metadata (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    yandex_disk_path TEXT UNIQUE NOT NULL,
                    yandex_disk_modified TEXT NOT NULL,
                    yandex_disk_md5 TEXT,
                    parsed_text_filename TEXT NOT NULL,
                    cached_at TEXT NOT NULL
                );";
        try {
            $this->pdo->exec($sql);
            $this->log('info', 'Cache table `cache_metadata` is ready.');
        } catch (PDOException $e) {
            $this->log('error', 'SQLite Create Table Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Пытается получить распарсенный текст из кэша.
     *
     * @param string $yandexDiskPath Путь к файлу на Яндекс.Диске.
     * @param string $yandexDiskModified Дата модификации файла на Яндекс.Диске.
     * @param string|null $yandexDiskMd5 MD5 хэш файла (если есть).
     * @return string|false Распарсенный текст, если кэш актуален, иначе false.
     */
    public function getCachedContent($yandexDiskPath, $yandexDiskModified, $yandexDiskMd5 = null) {
        $stmt = $this->pdo->prepare("SELECT parsed_text_filename, yandex_disk_modified, yandex_disk_md5 
                                      FROM cache_metadata 
                                      WHERE yandex_disk_path = :path");
        $stmt->execute([':path' => $yandexDiskPath]);
        $row = $stmt->fetch();

        if ($row) {
            // Проверяем актуальность кэша
            // Основной критерий - дата модификации. MD5 - дополнительный, если есть.
            if ($row['yandex_disk_modified'] === $yandexDiskModified && 
                ($yandexDiskMd5 === null || $row['yandex_disk_md5'] === null || $row['yandex_disk_md5'] === $yandexDiskMd5)) {
                
                $cachedFilePath = $this->parsedTextsDirectory . $row['parsed_text_filename'];
                if (file_exists($cachedFilePath)) {
                    $this->log('info', "Cache hit for '{$yandexDiskPath}'. Using cached file: {$row['parsed_text_filename']}");
                    return file_get_contents($cachedFilePath);
                }
                $this->log('warning', "Cache metadata found for '{$yandexDiskPath}', but text file '{$cachedFilePath}' is missing. Invalidating.");
                $this->deleteCacheEntry($yandexDiskPath); // Удаляем невалидную запись
            } else {
                $this->log('info', "Cache stale for '{$yandexDiskPath}'. DB: mod={$row['yandex_disk_modified']}, md5={$row['yandex_disk_md5']}. YD: mod={$yandexDiskModified}, md5={$yandexDiskMd5}");
            }
        }
        $this->log('info', "Cache miss for '{$yandexDiskPath}'.");
        return false;
    }

    /**
     * Сохраняет распарсенный текст в кэш.
     *
     * @param string $yandexDiskPath Путь к файлу на Яндекс.Диске.
     * @param string $yandexDiskModified Дата модификации файла на Яндекс.Диске.
     * @param string|null $yandexDiskMd5 MD5 хэш файла (если есть).
     * @param string $parsedContent Распарсенный текст.
     * @return bool True в случае успеха, false в случае ошибки.
     */
    public function setCache($yandexDiskPath, $yandexDiskModified, $yandexDiskMd5, $parsedContent) {
        $parsedTextFilename = md5($yandexDiskPath) . '.txt';
        $cachedFilePath = $this->parsedTextsDirectory . $parsedTextFilename;

        try {
            if (file_put_contents($cachedFilePath, $parsedContent) === false) {
                $this->log('error', "Failed to write parsed content to cache file: {$cachedFilePath}");
                return false;
            }

            $sql = "INSERT INTO cache_metadata (yandex_disk_path, yandex_disk_modified, yandex_disk_md5, parsed_text_filename, cached_at) 
                    VALUES (:path, :modified, :md5, :filename, :cached_at)
                    ON CONFLICT(yandex_disk_path) DO UPDATE SET
                        yandex_disk_modified = excluded.yandex_disk_modified,
                        yandex_disk_md5 = excluded.yandex_disk_md5,
                        parsed_text_filename = excluded.parsed_text_filename,
                        cached_at = excluded.cached_at;";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':path' => $yandexDiskPath,
                ':modified' => $yandexDiskModified,
                ':md5' => $yandexDiskMd5,
                ':filename' => $parsedTextFilename,
                ':cached_at' => date('Y-m-d H:i:s')
            ]);
            $this->log('info', "Successfully cached content for '{$yandexDiskPath}' to file '{$parsedTextFilename}'.");
            return true;
        } catch (PDOException $e) {
            $this->log('error', "SQLite Set Cache Error for '{$yandexDiskPath}': " . $e->getMessage());
            // Попытка удалить текстовый файл, если запись в БД не удалась
            if (file_exists($cachedFilePath)) {
                unlink($cachedFilePath);
            }
            return false;
        } catch (\Exception $e) {
            $this->log('error', "General Set Cache Error for '{$yandexDiskPath}': " . $e->getMessage());
            if (file_exists($cachedFilePath)) {
                unlink($cachedFilePath);
            }
            return false;
        }
    }

    /**
     * Удаляет запись из кэша (метаданные и текстовый файл).
     *
     * @param string $yandexDiskPath Путь к файлу на Яндекс.Диске.
     * @return bool True в случае успеха, false в случае ошибки.
     */
    public function deleteCacheEntry($yandexDiskPath) {
        try {
            $stmt = $this->pdo->prepare("SELECT parsed_text_filename FROM cache_metadata WHERE yandex_disk_path = :path");
            $stmt->execute([':path' => $yandexDiskPath]);
            $row = $stmt->fetch();

            if ($row) {
                $cachedFilePath = $this->parsedTextsDirectory . $row['parsed_text_filename'];
                if (file_exists($cachedFilePath)) {
                    if (!unlink($cachedFilePath)) {
                        $this->log('warning', "Failed to delete cached text file: {$cachedFilePath}");
                    }
                }
            }

            $deleteStmt = $this->pdo->prepare("DELETE FROM cache_metadata WHERE yandex_disk_path = :path");
            $deleteStmt->execute([':path' => $yandexDiskPath]);
            $this->log('info', "Deleted cache entry for '{$yandexDiskPath}'.");
            return true;
        } catch (PDOException $e) {
            $this->log('error', "SQLite Delete Cache Entry Error for '{$yandexDiskPath}': " . $e->getMessage());
            return false;
        }
    }

    private function log($level, $message) {
        if ($this->logger) {
            // Адаптируем под ваш метод логирования, если он отличается
            // Например, $this->logger->log($level, $message) или $this->logger->info($message) и т.д.
            if (method_exists($this->logger, $level)) {
                $this->logger->$level("[CacheManager] " . $message);
            } else {
                $this->logger->log("[CacheManager] [{$level}] " . $message); // Общий метод log, если есть
            }
        }
    }
}

?>
