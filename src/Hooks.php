<?php
namespace AISEOMeta;

use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Page\Hook\PageSaveCompleteHook;
use MediaWiki\MediaWikiServices;

class Hooks implements PageSaveCompleteHook, BeforePageDisplayHook {
    
    public function onPageSaveComplete(
        $wikiPage,
        $user,
        $summary,
        $flags,
        $revisionRecord,
        $editResult
    ) {
        // Only process main namespace
        if ($wikiPage->getTitle()->getNamespace() !== NS_MAIN) {
            return true;
        }

        // Push to Job Queue
        $job = new Job\GenerateMetaJob(
            $wikiPage->getTitle(),
            [
                'pageId' => $wikiPage->getId(),
                'revId' => $revisionRecord->getId()
            ]
        );
        MediaWikiServices::getInstance()->getJobQueueGroup()->push($job);

        return true;
    }

    public function onBeforePageDisplay($out, $skin): void {
        $title = $out->getTitle();
        if (!$title || $title->getNamespace() !== NS_MAIN) {
            return;
        }

        $pageId = $title->getArticleID();
        if (!$pageId) {
            return;
        }

        $metaManager = new MetaManager();
        $aiTags = $metaManager->getTagsFromProps($pageId);
        $customTags = $metaManager->getCustomTags();

        $mergedTags = $metaManager->mergeTags($aiTags, $customTags);

        foreach ($mergedTags as $name => $content) {
            if (!empty($content)) {
                $out->addMeta($name, $content);
            }
        }
    }
}