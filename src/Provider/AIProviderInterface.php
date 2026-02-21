<?php
namespace AISEOMeta\Provider;

interface AIProviderInterface {
    /**
     * Generate SEO tags from text
     * @param string $text
     * @return array Associative array of tags, e.g., ['description' => '...', 'keywords' => '...']
     */
    public function generate(string $text): array;

    /**
     * Test the connection to the AI provider with a simple message
     * @param string $message The test message to send
     * @return string The raw response from the AI
     * @throws \Exception If the connection fails
     */
    public function testConnection(string $message): string;
}