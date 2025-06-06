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
                chunk_index INTEGER NOT NULL,
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
    
    public function getQueryEmbedding($text) {
        if ($this->embeddingManager === null) {
            Logger::error("[VectorCacheManager] EmbeddingManager не инициализирован для получения embedding");
            return null;
        }
        
        return $this->embeddingManager->getEmbedding($text);
    }
    
    public function storeVectorData($filePath, $chunks) {
        if (!$this->isEmbeddingManagerInitialized()) {
            Logger::error("[VectorCacheManager] EmbeddingManager не инициализирован");
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO vector_embeddings (file_path, chunk_text, embedding, chunk_index) VALUES (?, ?, ?, ?)");
        } catch (\Exception $e) {
            Logger::error("[VectorCacheManager] Ошибка подготовки SQL: " . $e->getMessage());
            return false;
        }

        $stored = 0;
        foreach ($chunks as $index => $chunk) {
            try {
                $embedding = $this->embeddingManager->getEmbedding($chunk);
                if ($embedding === null || !is_array($embedding)) {
                    Logger::error("[VectorCacheManager] Не удалось получить embedding для чанка #" . ($index + 1));
                    continue;
                }

                $embeddingJson = json_encode($embedding);
                if ($embeddingJson === false) {
                    Logger::error("[VectorCacheManager] Ошибка JSON кодирования embedding для чанка #" . ($index + 1));
                    continue;
                }

                $stmt->execute([$filePath, $chunk, $embeddingJson, $index]);
                $stored++;
                
            } catch (\Exception $e) {
                Logger::error("[VectorCacheManager] Исключение в чанке #" . ($index + 1) . ": " . $e->getMessage());
                continue;
            }
        }

        Logger::info("[VectorCacheManager] Сохранено векторов: {$stored} из " . count($chunks));
        return $stored > 0;
    }
    
    public function findSimilarContent($query, $limit = 5) {
        try {
            if ($this->embeddingManager === null) {
                Logger::error("[VectorCacheManager] EmbeddingManager не инициализирован для поиска");
                return array();
            }
            
            // Получаем embedding для поискового запроса
            $queryEmbedding = $this->embeddingManager->getEmbedding($query);
            if ($queryEmbedding === null || !is_array($queryEmbedding)) {
                Logger::error("[VectorCacheManager] Не удалось получить embedding для запроса");
                return array();
            }
            
            // Получаем все векторы из базы
            $stmt = $this->pdo->prepare("SELECT file_path, chunk_text, embedding FROM vector_embeddings");
            $stmt->execute();
            
            $results = array();
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $chunkEmbedding = json_decode($row['embedding'], true);
                if ($chunkEmbedding === null) {
                    continue;
                }
                
                // Вычисляем косинусное сходство
                $similarity = $this->calculateCosineSimilarity($queryEmbedding, $chunkEmbedding);
                
                $results[] = array(
                    'file_path' => $row['file_path'],
                    'content' => $row['chunk_text'],
                    'similarity' => $similarity
                );
            }
            
            // Сортируем по убыванию сходства
            usort($results, function($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });
            
            // Возвращаем топ результатов
            return array_slice($results, 0, $limit);
            
        } catch (\Exception $e) {
            Logger::error("[VectorCacheManager] Ошибка поиска: " . $e->getMessage());
            return array();
        }
    }
    
    private function calculateCosineSimilarity($vectorA, $vectorB) {
        if (count($vectorA) !== count($vectorB)) {
            return 0;
        }
        
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        
        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }
        
        $normA = sqrt($normA);
        $normB = sqrt($normB);
        
        if ($normA == 0 || $normB == 0) {
            return 0;
        }
        
        return $dotProduct / ($normA * $normB);
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
