<?php

namespace ResearcherAI;

abstract class AIProvider {
    protected $apiKey;
    protected $baseUrl;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    abstract public function testConnection();
    abstract public function analyzeQuery($query, $priceData);
    abstract public function extractKeywords($query);
    protected abstract function sendRequest($endpoint, $data); // Убедитесь, что этот метод объявлен, если он используется в дочерних классах
}

?>