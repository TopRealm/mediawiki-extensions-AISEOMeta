<?php
namespace AISEOMeta;

use MediaWiki\Hook\ParserAfterParseHook;
use MediaWiki\Output\Hook\OutputPageParserOutputHook;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\MediaWikiServices;
use Html;

class Hooks implements PageSaveCompleteHook, ParserAfterParseHook, OutputPageParserOutputHook {
    
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

        // Only process configured namespaces (non-strict to handle string/int mismatches)
        if (!in_array($wikiPage->getTitle()->getNamespace(), $targetNamespaces)) {
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

    public function onParserAfterParse($parser, &$text, $stripState) {
        $title = $parser->getTitle();
        if (!$title) {
            return;
        }

        $config = MediaWikiServices::getInstance()->getMainConfig();
        $targetNamespaces = $config->get('ASMTargetNamespaces');

        if (!in_array($title->getNamespace(), $targetNamespaces)) {
            return;
        }

        $pageId = $title->getArticleID();
        if (!$pageId) {
            return;
        }

        $metaManager = new MetaManager();
        $tags = $metaManager->getTagsFromProps($pageId);
        $updated = $metaManager->getUpdateTime($pageId);

        if (!empty($tags)) {
            $parser->getOutput()->setProperty('aiseometa_tags', json_encode($tags, JSON_UNESCAPED_UNICODE));
        }
        if ($updated) {
            $parser->getOutput()->setProperty('aiseometa_updated', $updated);
        }
    }

    public function onOutputPageParserOutput($out, $parserOutput): void {
        $tagsJson = $parserOutput->getProperty('aiseometa_tags');
        
        // If tags are not in ParserOutput (e.g., cache cleared but page not re-parsed yet),
        // fallback to fetching from database (page_props)
        if ($tagsJson === null) {
            $title = $out->getTitle();
            if ($title && $title->exists()) {
                $config = MediaWikiServices::getInstance()->getMainConfig();
                $targetNamespaces = $config->get('ASMTargetNamespaces');
                
                if (in_array($title->getNamespace(), $targetNamespaces)) {
                    $metaManager = new MetaManager();
                    $aiTags = $metaManager->getTagsFromProps($title->getArticleID());
                } else {
                    $aiTags = [];
                }
            } else {
                $aiTags = [];
            }
        } else {
            $aiTags = json_decode($tagsJson, true);
        }
        
        $metaManager = new MetaManager();
        $customTags = $metaManager->getCustomTags();

        $mergedTags = $metaManager->mergeTags(is_array($aiTags) ? $aiTags : [], $customTags);

        foreach ($mergedTags as $name => $content) {
            if (!empty($content)) {
                $out->addMeta($name, $content);
                
                // Automatically add Open Graph and Twitter Card descriptions
                if ($name === 'description') {
                    $out->addHeadItem('og:description', Html::element('meta', ['property' => 'og:description', 'content' => $content]));
                    $out->addMeta('twitter:description', $content);
                }
            }
        }
    }
}