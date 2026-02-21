<?php
namespace AISEOMeta\Provider;

use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;
use OpenAI;

class OpenAIProvider implements AIProviderInterface {
    private LoggerInterface $logger;

    public function __construct() {
        $this->logger = \MediaWiki\Logger\LoggerFactory::getInstance('AISEOMeta');
    }

    public function generate(string $text): array {
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $key = $config->get('ASMOpenAIKey');
        $model = $config->get('ASMOpenAIModel');
        $promptTemplate = $config->get('ASMPromptTemplate');
        $endpoint = $config->get('ASMOpenAIEndpoint');

        if (empty($key)) {
            $this->logger->error('OpenAI API key is not configured.');
            return [];
        }

        $prompt = str_replace('{text}', $text, $promptTemplate);

        try {
            // Configure custom endpoint if provided (useful for OpenAI-compatible APIs)
            $factory = OpenAI::factory()
                ->withApiKey($key)
                ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 30.0]));
            
            if (!empty($endpoint) && $endpoint !== 'https://api.openai.com/v1/chat/completions') {
                // Extract base URI from endpoint (e.g., https://api.openai.com/v1)
                $baseUri = preg_replace('#/chat/completions$#', '', $endpoint);
                $factory = $factory->withBaseUri($baseUri);
            }

            $client = $factory->make();

            $additionalParams = $config->get('ASMOpenAIAdditionalParams');
            if (!is_array($additionalParams)) {
                $additionalParams = [];
            }

            $requestParams = array_merge([
                'model' => $model,
                'response_format' => ['type' => 'json_object']
            ], $additionalParams);

            $requestParams['messages'] = [
                ['role' => 'system', 'content' => 'You are an SEO expert.'],
                ['role' => 'user', 'content' => $prompt]
            ];

            $response = $client->chat()->create($requestParams);

            if (isset($response->choices[0]->message->content)) {
                $content = $response->choices[0]->message->content;
                
                // Robust JSON extraction: find the first '{' and last '}'
                if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                    $content = $matches[0];
                }
                
                $tags = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error('Failed to decode JSON from OpenAI response content: {error}', [
                        'error' => json_last_error_msg(),
                        'content' => $content
                    ]);
                    return [];
                }
                
                return is_array($tags) ? $tags : [];
            }

            $this->logger->warning('Unexpected OpenAI API response structure.');

        } catch (\Exception $e) {
            $this->logger->error('Exception during OpenAI API call: {message}', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return [];
    }

    public function testConnection(string $message): string {
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $key = $config->get('ASMOpenAIKey');
        $model = $config->get('ASMOpenAIModel');
        $endpoint = $config->get('ASMOpenAIEndpoint');

        if (empty($key)) {
            throw new \Exception('OpenAI API key is not configured.');
        }

        $factory = OpenAI::factory()
            ->withApiKey($key)
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => 15.0]));
        
        if (!empty($endpoint) && $endpoint !== 'https://api.openai.com/v1/chat/completions') {
            $baseUri = preg_replace('#/chat/completions$#', '', $endpoint);
            $factory = $factory->withBaseUri($baseUri);
        }

        $client = $factory->make();

        $additionalParams = $config->get('ASMOpenAIAdditionalParams');
        if (!is_array($additionalParams)) {
            $additionalParams = [];
        }

        $requestParams = array_merge([
            'model' => $model,
        ], $additionalParams);

        // Remove response_format for simple text test
        unset($requestParams['response_format']);

        $requestParams['messages'] = [
            ['role' => 'user', 'content' => $message]
        ];

        $response = $client->chat()->create($requestParams);

        if (isset($response->choices[0]->message->content)) {
            return $response->choices[0]->message->content;
        }

        throw new \Exception('Unexpected OpenAI API response structure.');
    }
}