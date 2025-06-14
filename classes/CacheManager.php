<?php

namespace ResearcherAI;

use PDO;
use PDOException;
// Logger будет использоваться статически: Logger::info(), Logger::error()

class CacheManager {
    protected $pdo;
    private $dbDirectory;
    private $parsedTextsDirectory;
    private $sqliteFile = 'cache.sqlite';

    public function __construct($dbBaseDir) {
        $this->dbDirectory = rtrim($dbBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->parsedTextsDirectory = $this->dbDirectory . 'parsed_texts' . DIRECTORY_SEPARATOR;

        $this->_initializeStorage();
        $this->_connectDb();
        $this->_createTable();
    }

    private function _initializeStorage() {
        if (!is_dir($this->dbDirectory)) {
            if (!mkdir($this->dbDirectory, 0777, true)) {
                Logger::error('[CacheManager] Failed to create DB directory: ' . $this->dbDirectory);
                throw new \RuntimeException('Failed to create DB directory: ' . $this->dbDirectory);
            }
            Logger::info('[CacheManager] Created DB directory: ' . $this->dbDirectory);
        }
        if (!is_dir($this->parsedTextsDirectory)) {
            if (!mkdir($this->parsedTextsDirectory, 0777, true)) {
                Logger::error('[CacheManager] Failed to create parsed_texts directory: ' . $this->parsedTextsDirectory);
                throw new \RuntimeException('Failed to create parsed_texts directory: ' . $this->parsedTextsDirectory);
            }
            Logger::info('[CacheManager] Created parsed_texts directory: ' . $this->parsedTextsDirectory);
        }
    }

    private function _connectDb() {
        try {
            $dsn = 'sqlite:' . $this->dbDirectory . $this->sqliteFile;
            $this->pdo = new PDO($dsn);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            Logger::info('[CacheManager] Successfully connected to SQLite DB: ' . $this->dbDirectory . $this->sqliteFile);
        } catch (PDOException $e) {
            Logger::error('[CacheManager] SQLite Connection Error: ' . $e->getMessage(), $e);
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
                    cache_key TEXT UNIQUE,
                    cached_at TEXT NOT NULL
                );";
        try {
            $this->pdo->exec($sql);
            // Ensure the new column `cache_key` exists even for legacy installations
            $this->_ensureCacheKeyColumn();
            Logger::info('[CacheManager] Cache table `cache_metadata` is ready.');
        } catch (PDOException $e) {
            Logger::error('[CacheManager] SQLite Create Table Error: ' . $e->getMessage(), $e);
            throw $e;
        }
    }

    /**
     * Ensure the `cache_key` column exists in the `cache_metadata` table. If the project was
     * deployed before this column was introduced, we add it via ALTER TABLE to prevent runtime SQL errors.
     */
    private function _ensureCacheKeyColumn() {
        try {
            $columnsStmt = $this->pdo->query("PRAGMA table_info(cache_metadata)");
            $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN, 1); // fetch second column which is the column name

            if (!in_array('cache_key', $columns, true)) {
                // На старых версиях SQLite нельзя добавлять колонку с ограничением UNIQUE в одном запросе
                $this->pdo->exec("ALTER TABLE cache_metadata ADD COLUMN cache_key TEXT");
                // Create explicit unique index for faster lookups (if not auto-created)
                $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_cache_key ON cache_metadata(cache_key)");
                Logger::info('[CacheManager] Added missing column `cache_key` to cache_metadata table.');
            }
        } catch (PDOException $e) {
            // We log the error but do not throw, to avoid breaking runtime in case of concurrent migrations
            Logger::error('[CacheManager] Failed to ensure `cache_key` column exists: ' . $e->getMessage(), $e);
        }
    }

    public function getCachedContent($yandexDiskPath, $yandexDiskModified, $yandexDiskMd5 = null) {
        $stmt = $this->pdo->prepare("SELECT parsed_text_filename, yandex_disk_modified, yandex_disk_md5 
                                      FROM cache_metadata 
                                      WHERE yandex_disk_path = :path");
        $stmt->execute([':path' => $yandexDiskPath]);
        $row = $stmt->fetch();

        if ($row) {
            if ($row['yandex_disk_modified'] === $yandexDiskModified && 
                ($yandexDiskMd5 === null || $row['yandex_disk_md5'] === null || $row['yandex_disk_md5'] === $yandexDiskMd5)) {
                
                $cachedFilePath = $this->parsedTextsDirectory . $row['parsed_text_filename'];
                if (file_exists($cachedFilePath)) {
                    Logger::info("[CacheManager] Cache hit for '{$yandexDiskPath}'. Using cached file: {$row['parsed_text_filename']}");
                    return file_get_contents($cachedFilePath);
                }
                Logger::warning("[CacheManager] Cache metadata found for '{$yandexDiskPath}', but text file '{$cachedFilePath}' is missing. Invalidating.");
                $this->deleteCacheEntry($yandexDiskPath); 
            } else {
                Logger::info("[CacheManager] Cache stale for '{$yandexDiskPath}'. DB: mod={$row['yandex_disk_modified']}, md5={$row['yandex_disk_md5']}. YD: mod={$yandexDiskModified}, md5={$yandexDiskMd5}");
            }
        }
        Logger::info("[CacheManager] Cache miss for '{$yandexDiskPath}'.");
        return false;
    }

    public function setCache($yandexDiskPath, $yandexDiskModified, $yandexDiskMd5, $parsedContent) {
        $parsedTextFilename = md5($yandexDiskPath) . '.txt';
        $cachedFilePath = $this->parsedTextsDirectory . $parsedTextFilename;

        try {
            if (file_put_contents($cachedFilePath, $parsedContent) === false) {
                Logger::error("[CacheManager] Failed to write parsed content to cache file: {$cachedFilePath}");
                return false;
            }

            $sql = "INSERT INTO cache_metadata (yandex_disk_path, yandex_disk_modified, yandex_disk_md5, parsed_text_filename, cache_key, cached_at) 
                    VALUES (:path, :modified, :md5, :filename, :cache_key, :cached_at)
                    ON CONFLICT(yandex_disk_path) DO UPDATE SET
                        yandex_disk_modified = excluded.yandex_disk_modified,
                        yandex_disk_md5 = excluded.yandex_disk_md5,
                        parsed_text_filename = excluded.parsed_text_filename,
                        cache_key = excluded.cache_key,
                        cached_at = excluded.cached_at;";
            
            $stmt = $this->pdo->prepare($sql);
            $cacheKey = md5($yandexDiskPath);
            $stmt->execute([
                ':path' => $yandexDiskPath,
                ':modified' => $yandexDiskModified,
                ':md5' => $yandexDiskMd5,
                ':filename' => $parsedTextFilename,
                ':cache_key' => $cacheKey,
                ':cached_at' => date('Y-m-d H:i:s')
            ]);
            Logger::info("[CacheManager] Successfully cached content for '{$yandexDiskPath}' to file '{$parsedTextFilename}'.");
            return true;
        } catch (PDOException $e) {
            Logger::error("[CacheManager] SQLite Set Cache Error for '{$yandexDiskPath}': " . $e->getMessage(), $e);
            if (file_exists($cachedFilePath)) {
                unlink($cachedFilePath);
            }
            return false;
        } catch (\Exception $e) {
            Logger::error("[CacheManager] General Set Cache Error for '{$yandexDiskPath}': " . $e->getMessage(), $e);
            if (file_exists($cachedFilePath)) {
                unlink($cachedFilePath);
            }
            return false;
        }
    }

    public function deleteCacheEntry($yandexDiskPath) {
        try {
            $stmt = $this->pdo->prepare("SELECT parsed_text_filename FROM cache_metadata WHERE yandex_disk_path = :path");
            $stmt->execute([':path' => $yandexDiskPath]);
            $row = $stmt->fetch();

            if ($row) {
                $cachedFilePath = $this->parsedTextsDirectory . $row['parsed_text_filename'];
                if (file_exists($cachedFilePath)) {
                    if (!unlink($cachedFilePath)) {
                        Logger::warning("[CacheManager] Failed to delete cached text file: {$cachedFilePath}");
                    }
                }
            }

            $deleteStmt = $this->pdo->prepare("DELETE FROM cache_metadata WHERE yandex_disk_path = :path");
            $deleteStmt->execute([':path' => $yandexDiskPath]);
            Logger::info("[CacheManager] Deleted cache entry for '{$yandexDiskPath}'.");
            return true;
        } catch (PDOException $e) {
            Logger::error("[CacheManager] SQLite Delete Cache Entry Error for '{$yandexDiskPath}': " . $e->getMessage(), $e);
            return false;
        }
    }

    public function getCachedText($cacheKey) {
        // Ищем кэшированный текст по ключу (MD5 пути файла)
        try {
            // ИСПРАВЛЕНО: используем prepared statement вместо SQL функции MD5()
            $stmt = $this->pdo->prepare("SELECT parsed_text_filename FROM cache_metadata WHERE cache_key = ?");
            $stmt->execute([$cacheKey]);
            $row = $stmt->fetch();
            
            if ($row) {
                $cachedFilePath = $this->parsedTextsDirectory . $row['parsed_text_filename'];
                if (file_exists($cachedFilePath)) {
                    Logger::info("[CacheManager] Found cached text for key: {$cacheKey}");
                    return file_get_contents($cachedFilePath);
                } else {
                    Logger::warning("[CacheManager] Cached text file missing: {$cachedFilePath}");
                }
            }
            
            Logger::info("[CacheManager] No cached text found for key: {$cacheKey}");
            return false;
        } catch (PDOException $e) {
            Logger::error("[CacheManager] Error getting cached text for key '{$cacheKey}': " . $e->getMessage());
            return false;
        }
    }
}

?>
