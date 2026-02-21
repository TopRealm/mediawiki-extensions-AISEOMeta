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
        $config = MediaWikiServices::getInstance()->getMainConfig();
        $targetNamespaces = $config->get('ASMTargetNamespaces');

        // Only process configured namespaces
        if (!in_array($wikiPage->getTitle()->getNamespace(), $targetNamespaces, true)) {
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
        if (!$title) {
            return;
        }

        $config = MediaWikiServices::getInstance()->getMainConfig();
        $targetNamespaces = $config->get('ASMTargetNamespaces');

        if (!in_array($title->getNamespace(), $targetNamespaces, true)) {
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