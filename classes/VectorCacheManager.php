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
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS vector_embeddings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                file_path TEXT NOT NULL,
                chunk_text TEXT NOT NULL,
                embedding TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            Logger::info("[VectorCacheManager] Таблица vector_embeddings готова");
        } catch (\Exception $e) {
            Logger::error("[VectorCacheManager] Ошибка создания таблиц: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function initializeEmbeddingManager($aiProvider) {
        $this->embeddingManager = new EmbeddingManager($aiProvider, $this->pdo);
        Logger::info("[VectorCacheManager] EmbeddingManager инициализирован");
    }
    
    public function storeVectorData($filePath, $chunks) {
        try {
            if ($this->embeddingManager === null) {
                Logger::error("[VectorCacheManager] EmbeddingManager не инициализирован");
                return false;
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO vector_embeddings (file_path, chunk_text, embedding) VALUES (?, ?, ?)");
            
            $stored = 0;
            foreach ($chunks as $chunk) {
                try {
                    $embedding = $this->embeddingManager->getEmbedding($chunk);
                    if ($embedding) {
                        $embeddingJson = json_encode($embedding);
                        $stmt->execute([$filePath, $chunk, $embeddingJson]);
                        $stored++;
                    }
                } catch (\Exception $e) {
                    Logger::error("[VectorCacheManager] Ошибка векторизации: " . $e->getMessage());
                }
            }
            
            Logger::info("[VectorCacheManager] Сохранено векторов: {$stored}");
            return $stored > 0;
            
        } catch (\Exception $e) {
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
    
    public function getVectorizedFilesPaths() {
        try {
            $stmt = $this->pdo->prepare("SELECT DISTINCT file_path FROM vector_embeddings");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return array();
        }
    }
}
