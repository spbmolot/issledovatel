<?php
namespace ResearcherAI;

class VectorCacheManager extends CacheManager {
    private $embeddingManager;
    
    public function __construct($dbBaseDir) {
        parent::__construct($dbBaseDir);
        if ($this->pdo === null) {
            Logger::error("[VectorCacheManager] PDO не инициализирован после parent::__construct()");
            throw new \Exception("PDO подключение не может быть инициализировано");
        }
        Logger::info("[VectorCacheManager] PDO подключение успешно инициализировано");
        $this->createVectorTables();
        Logger::info("[VectorCacheManager] Инициализация завершена");
    }
    
    private function createVectorTables() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS vector_embeddings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                file_path TEXT NOT NULL,
                chunk_text TEXT NOT NULL,
                embedding TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $this->pdo->exec($sql);
            Logger::info("[VectorCacheManager] Таблица vector_embeddings создана");
            
            // Создаем индекс для быстрого поиска по файлам
            $indexSql = "CREATE INDEX IF NOT EXISTS idx_vector_file_path ON vector_embeddings(file_path)";
            $this->pdo->exec($indexSql);
            Logger::info("[VectorCacheManager] Индекс для vector_embeddings создан");
            
        } catch (\Exception $e) {
            Logger::error("[VectorCacheManager] Ошибка создания таблицы: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function initializeEmbeddingManager($aiProvider) {
        $this->embeddingManager = new EmbeddingManager($aiProvider, $this->pdo);
        Logger::info("[VectorCacheManager] EmbeddingManager инициализирован");
    }
    
    public function isEmbeddingManagerInitialized() {
        return $this->embeddingManager !== null;
    }
    
    public function storeVectorData($filePath, $chunks) {
        try {
            Logger::info("[VectorCacheManager] Начинаем сохранение векторных данных для: {$filePath}");
            echo "   [DEBUG] storeVectorData() вызван для: {$filePath}\n";
            echo "   [DEBUG] Количество чанков: " . count($chunks) . "\n";
            
            if ($this->embeddingManager === null) {
                Logger::error("[VectorCacheManager] EmbeddingManager не инициализирован");
                echo "   [DEBUG] ❌ EmbeddingManager is null\n";
                return false;
            }
            echo "   [DEBUG] ✅ EmbeddingManager проверен\n";
            
            Logger::info("[VectorCacheManager] Подготавливаем SQL запрос");
            echo "   [DEBUG] Подготавливаем SQL statement...\n";
            
            try {
                $stmt = $this->pdo->prepare("INSERT INTO vector_embeddings (file_path, chunk_text, embedding) VALUES (?, ?, ?)");
                echo "   [DEBUG] ✅ SQL statement подготовлен\n";
            } catch (\Exception $e) {
                echo "   [DEBUG] ❌ Ошибка подготовки SQL: " . $e->getMessage() . "\n";
                Logger::error("[VectorCacheManager] Ошибка подготовки SQL: " . $e->getMessage());
                return false;
            }
            
            $stored = 0;
            foreach ($chunks as $index => $chunk) {
                try {
                    echo "   [DEBUG] Обрабатываем чанк #" . ($index + 1) . "\n";
                    Logger::info("[VectorCacheManager] Обрабатываем чанк #" . ($index + 1) . ": " . substr($chunk, 0, 50) . "...");
                    
                    echo "   [DEBUG] Вызываем getEmbedding()...\n";
                    $embedding = $this->embeddingManager->getEmbedding($chunk);
                    
                    if ($embedding) {
                        echo "   [DEBUG] ✅ Embedding получен, размер: " . count($embedding) . "\n";
                        Logger::info("[VectorCacheManager] Embedding получен, размер: " . count($embedding));
                        
                        $embeddingJson = json_encode($embedding);
                        echo "   [DEBUG] JSON размер: " . strlen($embeddingJson) . " символов\n";
                        
                        echo "   [DEBUG] Выполняем SQL INSERT...\n";
                        $stmt->execute([$filePath, $chunk, $embeddingJson]);
                        $stored++;
                        
                        echo "   [DEBUG] ✅ Чанк #" . ($index + 1) . " сохранен в БД\n";
                        Logger::info("[VectorCacheManager] Чанк #" . ($index + 1) . " сохранен");
                    } else {
                        echo "   [DEBUG] ❌ Embedding = null для чанка #" . ($index + 1) . "\n";
                        Logger::error("[VectorCacheManager] Не удалось получить embedding для чанка #" . ($index + 1));
                    }
                } catch (\Exception $e) {
                    echo "   [DEBUG] ❌ Исключение в чанке #" . ($index + 1) . ": " . $e->getMessage() . "\n";
                    Logger::error("[VectorCacheManager] Ошибка векторизации чанка #" . ($index + 1) . ": " . $e->getMessage());
                }
            }
            
            echo "   [DEBUG] Сохранено векторов: {$stored} из " . count($chunks) . "\n";
            Logger::info("[VectorCacheManager] Сохранено векторов: {$stored}");
            return $stored > 0;
            
        } catch (\Exception $e) {
            echo "   [DEBUG] ❌ Общее исключение: " . $e->getMessage() . "\n";
            Logger::error("[VectorCacheManager] Ошибка storeVectorData: " . $e->getMessage());
            return false;
        }
    }
    
    public function findSimilarContent($query, $limit = 5) {
        try {
            if ($this->embeddingManager === null) {
                return array();
            }
            
            $stmt = $this->pdo->prepare("SELECT file_path, chunk_text FROM vector_embeddings LIMIT ?");
            $stmt->execute([$limit]);
            
            $results = array();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $results[] = array(
                    'file_path' => $row['file_path'],
                    'content' => $row['chunk_text']
                );
            }
            return $results;
        } catch (\Exception $e) {
            Logger::error("[VectorCacheManager] Ошибка поиска: " . $e->getMessage());
            return array();
        }
    }
    
    public function searchSimilar($query, $limit = 5) {
        // Псевдоним для findSimilarContent для совместимости
        return $this->findSimilarContent($query, $limit);
    }
    
    public function getVectorizedFilesPaths() {
        try {
            $stmt = $this->pdo->prepare("SELECT DISTINCT file_path FROM vector_embeddings");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return array();
        }
    }
    
    public function getVectorizationStats() {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT file_path) as vectorized_files_count FROM vector_embeddings");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return array(
                'vectorized_files_count' => $result['vectorized_files_count'] ?? 0
            );
        } catch (\Exception $e) {
            Logger::error("[VectorCacheManager] Ошибка получения статистики: " . $e->getMessage());
            return array(
                'vectorized_files_count' => 0
            );
        }
    }
}
