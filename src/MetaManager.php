<?php
namespace AISEOMeta;

use MediaWiki\MediaWikiServices;

class MetaManager {
    public const PROP_NAME = 'aiseometa_tags';
    public const PROP_TIME = 'aiseometa_updated';

    public function getTagsFromProps(int $pageId): array {
        $dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection(DB_REPLICA);
        $row = $dbr->selectRow(
            'page_props',
            'pp_value',
            [
                'pp_page' => $pageId,
                'pp_propname' => self::PROP_NAME
            ],
            __METHOD__
        );

        if ($row && $row->pp_value) {
            $decoded = json_decode($row->pp_value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function saveTagsToProps(int $pageId, array $tags): void {
        $dbw = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection(DB_PRIMARY);
        
        if (empty($tags)) {
            $dbw->delete(
                'page_props',
                [
                    'pp_page' => $pageId,
                    'pp_propname' => [self::PROP_NAME, self::PROP_TIME]
                ],
                __METHOD__
            );
            return;
        }

        $dbw->replace(
            'page_props',
            [['pp_page', 'pp_propname']],
            [
                'pp_page' => $pageId,
                'pp_propname' => self::PROP_NAME,
                'pp_sortkey' => 0,
                'pp_value' => json_encode($tags, JSON_UNESCAPED_UNICODE)
            ],
            __METHOD__
        );

        $dbw->replace(
            'page_props',
            [['pp_page', 'pp_propname']],
            [
                'pp_page' => $pageId,
                'pp_propname' => self::PROP_TIME,
                'pp_sortkey' => 0,
                'pp_value' => wfTimestampNow()
            ],
            __METHOD__
        );
    }

    public function getUpdateTime(int $pageId): ?string {
        $dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection(DB_REPLICA);
        $row = $dbr->selectRow(
            'page_props',
            'pp_value',
            [
                'pp_page' => $pageId,
                'pp_propname' => self::PROP_TIME
            ],
            __METHOD__
        );

        return $row ? $row->pp_value : null;
    }

    public function getCustomTags(): array {
        $msg = wfMessage('ASM-custom-tags')->inContentLanguage();
        if ($msg->isDisabled()) {
            return [];
        }

        $text = $msg->plain();
        $lines = explode("\n", $text);
        $tags = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }
            list($name, $content) = explode('|', $line, 2);
            $tags[trim($name)] = trim($content);
        }

        return $tags;
    }

    public function mergeTags(array $aiTags, array $customTags): array {
        // Custom tags override AI tags
        return array_merge($aiTags, $customTags);
    }
}