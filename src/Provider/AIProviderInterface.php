<?php
namespace AISEOMeta\Provider;

interface AIProviderInterface {
    /**
     * Generate SEO tags from text
     * @param string $text
     * @return array Associative array of tags, e.g., ['description' => '...', 'keywords' => '...']
     */
    public function generate(string $text): array;
}