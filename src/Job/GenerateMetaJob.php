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

        // Get plain text (simplified, ideally use Parser to strip wikitext)
        $text = $content->getText();
        $text = strip_tags($text);
        $text = mb_substr($text, 0, 2000); // Limit to 2000 chars to save tokens

        $provider = ProviderFactory::create();
        $tags = $provider->generate($text);

        if (!empty($tags)) {
            $metaManager = new MetaManager();
            $metaManager->saveTagsToProps($pageId, $tags);
        }

        return true;
    }
}