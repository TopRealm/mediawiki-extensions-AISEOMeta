<?php
namespace AISEOMeta;

use HTMLForm;
use Title;
use Html;

class SpecialAISEOMetaQuery extends SpecialAISEOMetaBase {
    public function __construct() {
        parent::__construct('AISEOMetaQuery', 'edit');
    }

    public function execute($subPage) {
        $this->setHeaders();
        $this->checkPermissions();
        $this->outputHeader();

        $this->getOutput()->addModuleStyles(['codex.styles-all']);

        $this->showQueryForm();
    }

    private function showQueryForm() {
        $out = $this->getOutput();

        $out->addHTML(Html::element('h2', [], $this->msg('aiseometa-query-title')->text()));

        $formDescriptor = [
            'pagetitle' => [
                'type' => 'text',
                'label-message' => 'aiseometa-page-title',
                'required' => true,
            ]
        ];

        $htmlForm = HTMLForm::factory('codex', $formDescriptor, $this->getContext(), 'queryform');
        $htmlForm->setSubmitTextMsg('aiseometa-query-submit');
        $htmlForm->setSubmitCallback([$this, 'processQueryForm']);
        $htmlForm->show();
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

        $regenerateUrl = $this->getPageTitle()->getLocalURL(['action' => 'regenerate', 'page' => $title->getPrefixedDBkey()]);
        $btn = Html::element('a', [
            'href' => $regenerateUrl,
            'class' => 'cdx-button cdx-button--action-progressive cdx-button--weight-primary'
        ], $this->msg('aiseometa-regenerate-btn')->text());
        
        $out->addHTML(Html::rawElement('p', [], $btn));

        return true;
    }
}
