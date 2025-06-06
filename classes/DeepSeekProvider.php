<?php
namespace ResearcherAI;

class DeepSeekProvider extends AIProvider {
    public function __construct($apiKey) {
        parent::__construct($apiKey);
        $this->baseUrl = "https://api.deepseek.com/v1";
    }

    public function testConnection() { return true; }
    public function analyzeQuery($query, $priceData) { return array("text" => "Test response", "sources" => array()); }
    public function extractKeywords($query) { return array("test"); }
    public function getEmbedding($text) { return array_fill(0, 100, 0.1); }
    protected function sendRequest($endpoint, $data, $method = "POST") { return array(); }
}
