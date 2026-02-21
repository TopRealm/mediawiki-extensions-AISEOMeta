<?php
namespace AISEOMeta\Job;

use Job;
use Title;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use AISEOMeta\Provider\ProviderFactory;
use AISEOMeta\MetaManager;

class GenerateMetaJob extends Job {
    public function __construct(Title $title, array $params) {
        parent::__construct('AISEOMetaGenerateJob', $params + [
            'namespace' => $title->getNamespace(),
            'title' => $title->getDBkey()
        ]);
    }

    public function run() {
        $pageId = $this->params['pageId'] ?? 0;
        $revId = $this->params['revId'] ?? 0;

        if (!$pageId || !$revId) {
            return true;
        }

        $services = MediaWikiServices::getInstance();
        $revisionLookup = $services->getRevisionLookup();
        $revision = $revisionLookup->getRevisionById($revId);

        if (!$revision) {
            return true;
        }

        $content = $revision->getContent(SlotRecord::MAIN);
        if (!$content) {
            return true;
        }

        // Parse wikitext to HTML to get the actual rendered text
        $parser = $services->getParser();
        $parserOptions = \ParserOptions::newFromAnon();
        $parserOutput = $parser->parse($content->getText(), $this->getTitle(), $parserOptions);
        $html = $parserOutput->getText();
        
        // Strip HTML tags and normalize whitespace
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Limit to 2000 chars to save tokens (usually enough for SEO context)
        $text = mb_substr($text, 0, 2000);

        $provider = ProviderFactory::create();
        $tags = $provider->generate($text);

        if (!empty($tags)) {
            $metaManager = new MetaManager();
            $metaManager->saveTagsToProps($pageId, $tags);
        }

        return true;
    }
}