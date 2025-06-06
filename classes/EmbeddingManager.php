<?php
namespace ResearcherAI;

class EmbeddingManager {
    private $aiProvider; 
    private $pdo;
    
    public function __construct($aiProvider, $sqlitePdo) {
        $this->aiProvider = $aiProvider; 
        $this->pdo = $sqlitePdo;
    }
    
    public function getEmbedding($text) {
        try {
            echo "       [DEBUG-EM] Вызван getEmbedding(), длина текста: " . strlen($text) . "\n";
            echo "       [DEBUG-EM] AI Provider класс: " . get_class($this->aiProvider) . "\n";
            
            $result = $this->aiProvider->getEmbedding($text);
            
            if ($result === null) {
                echo "       [DEBUG-EM] ❌ AI Provider вернул null\n";
            } elseif (is_array($result)) {
                echo "       [DEBUG-EM] ✅ AI Provider вернул массив размером: " . count($result) . "\n";
            } else {
                echo "       [DEBUG-EM] ⚠️ AI Provider вернул не массив: " . gettype($result) . "\n";
            }
            
            return $result;
        } catch (\Exception $e) {
            echo "       [DEBUG-EM] ❌ Исключение в getEmbedding(): " . $e->getMessage() . "\n";
            Logger::error("[EmbeddingManager] Ошибка получения embedding: " . $e->getMessage());
            return null;
        }
    }
    
    public function findSimilarChunks($query, $limit = 5) {
        Logger::info("[EmbeddingManager] Searching for similar chunks");
        try {
            $queryEmbedding = $this->aiProvider->getEmbedding($query);
            if (!$queryEmbedding) return array();
            $stmt = $this->pdo->prepare("SELECT file_path, chunk_text FROM vector_embeddings LIMIT ?");
            $stmt->execute(array($limit));
            $results = array();
            while ($row = $stmt->fetch()) {
                $results[] = array("file_path" => $row["file_path"], "chunk_text" => $row["chunk_text"], "similarity" => 0.8);
            }
            return $results;
        } catch (\Exception $e) { 
            Logger::error("[EmbeddingManager] Ошибка поиска: " . $e->getMessage());
            return array(); 
        }
    }
}
