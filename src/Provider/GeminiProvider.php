<?php
namespace AISEOMeta\Provider;

use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use Gemini\Client;

class GeminiProvider implements AIProviderInterface {
    private LoggerInterface $logger;

    public function __construct() {
        $this->logger = MediaWikiServices::getInstance()->getLogger('AISEOMeta');
    }

    public function generate(string $text): array {
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $key = $config->get('ASMGeminiKey');
        $model = $config->get('ASMGeminiModel');
        $promptTemplate = $config->get('ASMPromptTemplate');

        if (empty($key)) {
            $this->logger->error('Gemini API key is not configured.');
            return [];
        }

        $prompt = str_replace('{text}', $text, $promptTemplate);

        try {
            $client = \Gemini::client($key);
            
            $response = $client->generativeModel(model: $model)->generateContent($prompt);

            if ($response && $response->text()) {
                $content = $response->text();

                // Robust JSON extraction: find the first '{' and last '}'
                if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                    $content = $matches[0];
                }

                $tags = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('Failed to decode JSON from Gemini response content: {error}', [
                        'error' => json_last_error_msg(),
                        'content' => $content
                    ]);
                    return [];
                }
                
                return is_array($tags) ? $tags : [];
            }
            
            $this->logger->warning('Unexpected Gemini API response structure.');

        } catch (\Exception $e) {
            $this->logger->error('Exception during Gemini API call: {message}', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return [];
    }
}