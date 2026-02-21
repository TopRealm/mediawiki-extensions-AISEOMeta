<?php
namespace AISEOMeta\Provider;

use MediaWiki\MediaWikiServices;

class ProviderFactory {
    public static function create(): AIProviderInterface {
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $providerName = $config->get('ASMProvider');

        if ($providerName === 'gemini') {
            return new GeminiProvider();
        }

        return new OpenAIProvider();
    }
}