<?php
require_once getenv('MW_INSTALL_PATH') !== false
    ? getenv('MW_INSTALL_PATH') . '/maintenance/Maintenance.php'
    : __DIR__ . '/../../../maintenance/Maintenance.php';

use AISEOMeta\Job\GenerateMetaJob;
use AISEOMeta\MetaManager;

class RegenerateSEOMeta extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('Regenerate AI SEO meta tags by pushing jobs to the queue.');
        $this->addOption('all', 'Regenerate for all pages in configured target namespaces', false, false);
        $this->addOption('page', 'Regenerate for a specific page title', false, true);
        $this->addOption('force', 'Force regeneration even if tags already exist', false, false);
        $this->requireExtension('AISEOMeta');
    }

    public function execute() {
        $all = $this->hasOption('all');
        $page = $this->getOption('page');
        $force = $this->hasOption('force');

        if (!$all && !$page) {
            $this->fatalError("You must specify either --all or --page=<Title>");
        }

        $dbr = $this->getDB(DB_REPLICA);
        $metaManager = new MetaManager();

        if ($page) {
            $title = Title::newFromText($page);
            if (!$title || !$title->exists()) {
                $this->fatalError("Invalid or non-existent page title: $page");
            }
            $this->pushJob($title, $metaManager, $force);
            $this->output("Job pushed for page: {$title->getPrefixedText()}\n");
        } else {
            $config = \MediaWiki\MediaWikiServices::getInstance()->getMainConfig();
            $targetNamespaces = $config->get('ASMTargetNamespaces');
            
            $this->output("Finding all pages in target namespaces (" . implode(', ', $targetNamespaces) . ")...\n");
            $res = $dbr->select(
                'page',
                ['page_id', 'page_title', 'page_latest'],
                ['page_namespace' => $targetNamespaces, 'page_is_redirect' => 0],
                __METHOD__
            );

            $count = 0;
            foreach ($res as $row) {
                $title = Title::newFromRow($row);
                if ($this->pushJob($title, $metaManager, $force, $row->page_latest)) {
                    $count++;
                }
            }
            $this->output("Done. Pushed $count jobs to the queue.\n");
        }
    }

    private function pushJob(Title $title, MetaManager $metaManager, bool $force, $revId = null): bool {
        $pageId = $title->getArticleID();
        
        if (!$force) {
            $existing = $metaManager->getTagsFromProps($pageId);
            if (!empty($existing)) {
                return false;
            }
        }

        if (!$revId) {
            $revId = $title->getLatestRevID();
        }

        $job = new GenerateMetaJob(
            $title,
            [
                'pageId' => $pageId,
                'revId' => $revId
            ]
        );
        \MediaWiki\MediaWikiServices::getInstance()->getJobQueueGroup()->push($job);
        return true;
    }
}

$maintClass = RegenerateSEOMeta::class;
require_once RUN_MAINTENANCE_IF_MAIN;