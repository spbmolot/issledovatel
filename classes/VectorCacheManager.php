<?php
namespace ResearcherAI;

class VectorCacheManager extends CacheManager {
    private $embeddingManager;
    public function initializeEmbeddingManager($aiProvider) {
        $this->embeddingManager = new EmbeddingManager($aiProvider, $this->pdo);
        Logger::info("[VectorCacheManager] EmbeddingManager initialized");
    }
    public function findSimilarContent($query, $limit = 5) {
        if (!$this->embeddingManager) return array();
        return $this->embeddingManager->findSimilarChunks($query, $limit);
    }
    public function getVectorizedFilesPaths() {
        try {
            $stmt = $this->pdo->prepare("SELECT DISTINCT file_path FROM vector_embeddings");
            $stmt->execute(); $paths = array();
            while ($row = $stmt->fetch()) { $paths[] = $row["file_path"]; }
            return $paths;
        } catch (Exception $e) { return array(); }
    }
}
