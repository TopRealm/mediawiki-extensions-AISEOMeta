<?php
require_once getenv('MW_INSTALL_PATH') !== false
    ? getenv('MW_INSTALL_PATH') . '/maintenance/Maintenance.php'
    : __DIR__ . '/../../../maintenance/Maintenance.php';

use AISEOMeta\MetaManager;

class CleanSEOMeta extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('Clean AI generated SEO meta tags from page_props.');
        $this->addOption('all', 'Clean all SEO meta tags', false, false);
        $this->addOption('page', 'Clean SEO meta tags for a specific page title', false, true);
        $this->requireExtension('AISEOMeta');
    }

    public function execute() {
        $all = $this->hasOption('all');
        $page = $this->getOption('page');

        if (!$all && !$page) {
            $this->fatalError("You must specify either --all or --page=<Title>");
        }

        $dbw = $this->getDB(DB_PRIMARY);

        if ($all) {
            $this->output("Cleaning all AI SEO meta tags...\n");
            $dbw->delete(
                'page_props',
                ['pp_propname' => MetaManager::PROP_NAME],
                __METHOD__
            );
            $this->output("Done. Deleted " . $dbw->affectedRows() . " rows.\n");
        } else {
            $title = Title::newFromText($page);
            if (!$title || !$title->exists()) {
                $this->fatalError("Invalid or non-existent page title: $page");
            }
            $this->output("Cleaning AI SEO meta tags for page: {$title->getPrefixedText()}...\n");
            $dbw->delete(
                'page_props',
                [
                    'pp_page' => $title->getArticleID(),
                    'pp_propname' => MetaManager::PROP_NAME
                ],
                __METHOD__
            );
            $this->output("Done. Deleted " . $dbw->affectedRows() . " rows.\n");
        }
    }
}

$maintClass = CleanSEOMeta::class;
require_once RUN_MAINTENANCE_IF_MAIN;