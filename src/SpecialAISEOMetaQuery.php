<?php
namespace AISEOMeta;

use HTMLForm;
use Title;
use Html;
use AISEOMeta\Job\GenerateMetaJob;
use MediaWiki\MediaWikiServices;

class SpecialAISEOMetaQuery extends SpecialAISEOMetaBase {
    public function __construct() {
        parent::__construct('AISEOMetaQuery', 'aiseometa-admin');
    }

    public function execute($subPage) {
        $this->setHeaders();
        $this->checkPermissions();
        $this->outputHeader();

        $this->getOutput()->addModuleStyles(['codex.styles-all']);

        $request = $this->getRequest();
        if ($request->getVal('action') === 'regenerate' && $request->getVal('page')) {
            if ($this->pushJobForPage($request->getVal('page'))) {
                $this->getOutput()->addHTML($this->getCodexMessage('success', $this->msg('aiseometa-job-pushed', $request->getVal('page'))->text()));
            }
            $this->showQueryForm();
            $this->processQueryForm(['pagetitle' => $request->getVal('page')]);
            return;
        }

        $this->showQueryForm();
    }

    private function showQueryForm() {
        $out = $this->getOutput();
        $request = $this->getRequest();

        $out->addHTML(Html::element('h2', [], $this->msg('aiseometa-query-title')->text()));

        $formDescriptor = [
            'pagetitle' => [
                'type' => 'text',
                'label-message' => 'aiseometa-page-title',
                'required' => true,
                'default' => $request->getVal('page', ''),
            ]
        ];

        $htmlForm = HTMLForm::factory('codex', $formDescriptor, $this->getContext(), 'queryform');
        $htmlForm->setSubmitTextMsg('aiseometa-query-submit');
        $htmlForm->setSubmitCallback([$this, 'processQueryForm']);
        $htmlForm->showAlways();
    }

    public function processQueryForm($formData) {
        $titleText = $formData['pagetitle'];
        $title = Title::newFromText($titleText);
        $out = $this->getOutput();

        if (!$title || !$title->exists()) {
            $out->addHTML($this->getCodexMessage('error', $this->msg('aiseometa-invalid-page')->text()));
            return true;
        }

        $pageId = $title->getArticleID();
        $metaManager = new MetaManager();
        $tags = $metaManager->getTagsFromProps($pageId);
        $updated = $metaManager->getUpdateTime($pageId);

        $out->addHTML(Html::element('h3', [], $this->msg('aiseometa-result-title', $title->getPrefixedText())->text()));
        
        if ($updated) {
            $lang = $this->getLanguage();
            $timeStr = $lang->timeanddate($updated, true);
            $out->addHTML(Html::rawElement('p', [], Html::element('b', [], $this->msg('aiseometa-last-updated')->text() . ': ') . htmlspecialchars($timeStr)));
        } else {
            $out->addHTML(Html::rawElement('p', [], Html::element('b', [], $this->msg('aiseometa-last-updated')->text() . ': ') . $this->msg('aiseometa-never')->text()));
        }

        if (!empty($tags)) {
            $out->addHTML(Html::element('pre', [], json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
        } else {
            $out->addHTML(Html::element('p', [], $this->msg('aiseometa-no-tags')->text()));
        }

        $form = Html::openElement('form', [
            'method' => 'get',
            'action' => wfScript()
        ]);
        $form .= Html::hidden('title', $this->getPageTitle()->getPrefixedDBkey());
        $form .= Html::hidden('action', 'regenerate');
        $form .= Html::hidden('page', $title->getPrefixedDBkey());
        $form .= Html::element('button', [
            'type' => 'submit',
            'class' => 'cdx-button cdx-button--action-progressive cdx-button--weight-primary'
        ], $this->msg('aiseometa-regenerate-btn')->text());
        $form .= Html::closeElement('form');
        
        $out->addHTML(Html::rawElement('p', [], $form));

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
