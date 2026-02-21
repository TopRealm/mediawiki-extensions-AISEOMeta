<?php
namespace AISEOMeta;

use HTMLForm;
use Title;
use Html;
use AISEOMeta\Job\GenerateMetaJob;
use MediaWiki\MediaWikiServices;

class SpecialAISEOMetaBatch extends SpecialAISEOMetaBase {
    public function __construct() {
        parent::__construct('AISEOMetaBatch', 'aiseometa-admin');
    }

    public function execute($subPage) {
        $this->setHeaders();
        $this->checkPermissions();
        $this->outputHeader();

        $this->getOutput()->addModuleStyles(['codex.styles-all']);

        $this->showBatchForm();
    }

    private function showBatchForm() {
        $out = $this->getOutput();
        $request = $this->getRequest();

        if ($request->getVal('action') === 'regenerate' && $request->getVal('page')) {
            if ($this->pushJobForPage($request->getVal('page'))) {
                $out->addHTML($this->getCodexMessage('success', $this->msg('aiseometa-job-pushed', $request->getVal('page'))->text()));
            }
        }

        $out->addHTML(Html::element('h2', [], $this->msg('aiseometa-batch-title')->text()));

        $formDescriptor = [
            'pagetitles' => [
                'type' => 'textarea',
                'label-message' => 'aiseometa-batch-pages',
                'help-message' => 'aiseometa-batch-help',
                'required' => true,
                'rows' => 5
            ]
        ];

        $htmlForm = HTMLForm::factory('codex', $formDescriptor, $this->getContext(), 'batchform');
        $htmlForm->setSubmitTextMsg('aiseometa-batch-submit');
        $htmlForm->setSubmitCallback([$this, 'processBatchForm']);
        $htmlForm->show();
    }

    public function processBatchForm($formData) {
        $titles = explode("\n", $formData['pagetitles']);
        $count = 0;
        $out = $this->getOutput();

        foreach ($titles as $titleText) {
            $titleText = trim($titleText);
            if ($titleText === '') continue;

            if ($this->pushJobForPage($titleText)) {
                $count++;
            }
        }

        $out->addHTML($this->getCodexMessage('success', $this->msg('aiseometa-batch-success', $count)->text()));
        return true;
    }

    private function pushJobForPage(string $titleText): bool {
        $title = Title::newFromText($titleText);
        if (!$title || !$title->exists()) {
            return false;
        }

        $job = new GenerateMetaJob(
            $title,
            [
                'pageId' => $title->getArticleID(),
                'revId' => $title->getLatestRevID()
            ]
        );
        MediaWikiServices::getInstance()->getJobQueueGroup()->push($job);
        return true;
    }
}
