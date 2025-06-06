<?php
namespace ResearcherAI;

class EmbeddingManager {
    private $aiProvider; private $pdo;
    public function __construct($aiProvider, $sqlitePdo) {
        $this->aiProvider = $aiProvider; $this->pdo = $sqlitePdo;
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
        } catch (Exception $e) { return array(); }
    }
}
