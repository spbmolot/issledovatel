<?php

namespace ResearcherAI;

class AIProviderFactory {
    public static function create($provider, $apiKey, $proxyUrl = null) {
        switch (strtolower($provider)) {
            case 'openai':
                // Убедимся, что OpenAIProvider будет доступен.
                // Если он в том же неймспейсе, дополнительный use не нужен.
                return new OpenAIProvider($apiKey, $proxyUrl);
            case 'deepseek':
                // Аналогично для DeepSeekProvider.
                return new DeepSeekProvider($apiKey);
            default:
                throw new \InvalidArgumentException("Unsupported AI provider: " . $provider);
        }
    }
}
