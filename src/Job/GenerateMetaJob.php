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

        // Skip redirects
        if ($this->getTitle()->isRedirect()) {
            return true;
        }

        $text = $content->getText();

        // Optimization: Avoid parsing the entire wikitext to HTML, which is extremely slow and memory-intensive.
        // Instead, we strip out common wikitext noise and send the raw wikitext to the AI.
        // Modern LLMs are very good at understanding raw wikitext.
        
        // Remove HTML comments
        $text = preg_replace('/<!--.*?-->/s', '', $text);
        // Remove references
        $text = preg_replace('/<ref.*?>.*?<\/ref>/is', '', $text);
        // Remove tables (simple heuristic)
        $text = preg_replace('/\{\|.*?\|\}/s', '', $text);
        
        // Strip HTML tags and normalize whitespace
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Limit to 3000 chars to save tokens and memory (usually enough for SEO context)
        $text = mb_substr($text, 0, 3000);

        $provider = ProviderFactory::create();
        $tags = $provider->generate($text);

        if (!empty($tags)) {
            $metaManager = new MetaManager();
            $metaManager->saveTagsToProps($pageId, $tags);
        }

        return true;
    }
}