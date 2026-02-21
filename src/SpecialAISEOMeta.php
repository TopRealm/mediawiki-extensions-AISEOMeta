<?php
namespace AISEOMeta;

use SpecialPage;
use HTMLForm;
use Title;
use MediaWiki\MediaWikiServices;
use AISEOMeta\Job\GenerateMetaJob;
use Html;

class SpecialAISEOMeta extends SpecialPage {
    public function __construct() {
        parent::__construct('AISEOMeta', 'edit');
    }

    public function execute($subPage) {
        $this->setHeaders();
        $this->checkPermissions();
        $this->outputHeader();

        // Load Codex styles for the custom HTML elements
        $this->getOutput()->addModuleStyles([
            'codex.styles-all'
        ]);

        $this->showConfig();
        $this->showQueryForm();
        $this->showBatchForm();
    }

    private function getCodexMessage(string $type, string $text): string {
        $icon = Html::element('span', ['class' => 'cdx-message__icon']);
        $content = Html::rawElement('div', ['class' => 'cdx-message__content'], Html::element('p', [], $text));
        
        $role = $type === 'error' ? 'alert' : 'status';
        
        return Html::rawElement('div', [
            'class' => "cdx-message cdx-message--block cdx-message--{$type}",
            'role' => $role
        ], $icon . $content);
    }

    private function showConfig() {
        $out = $this->getOutput();
        $config = MediaWikiServices::getInstance()->getMainConfig();
        
        $provider = $config->get('ASMProvider');
        $openAiKey = $config->get('ASMOpenAIKey') ? '✅ Set' : '❌ Not set';
        $geminiKey = $config->get('ASMGeminiKey') ? '✅ Set' : '❌ Not set';

        $out->addHTML(Html::element('h2', [], $this->msg('aiseometa-config-title')->text()));

        $listItems = '';
        $listItems .= Html::rawElement('li', [], Html::element('b', [], 'Provider: ') . htmlspecialchars($provider));
        $listItems .= Html::rawElement('li', [], Html::element('b', [], 'OpenAI Model: ') . htmlspecialchars($config->get('ASMOpenAIModel')));
        $listItems .= Html::rawElement('li', [], Html::element('b', [], 'OpenAI Key: ') . $openAiKey);
        
        $additionalParams = $config->get('ASMOpenAIAdditionalParams');
        if (is_array($additionalParams) && !empty($additionalParams)) {
            $pre = Html::element('pre', [], json_encode($additionalParams, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $listItems .= Html::rawElement('li', [], Html::element('b', [], 'OpenAI Additional Params: ') . $pre);
        }

        $listItems .= Html::rawElement('li', [], Html::element('b', [], 'Gemini Model: ') . htmlspecialchars($config->get('ASMGeminiModel')));
        $listItems .= Html::rawElement('li', [], Html::element('b', [], 'Gemini Key: ') . $geminiKey);

        $out->addHTML(Html::rawElement('ul', [], $listItems));
    }

    private function showQueryForm() {
        $out = $this->getOutput();

        $out->addHTML(Html::element('h2', [], $this->msg('aiseometa-query-title')->text()));

        $formDescriptor = [
            'pagetitle' => [
                'type' => 'text',
                'label-message' => 'aiseometa-page-title',
                'required' => true,
            ],
            'action_type' => [
                'type' => 'hidden',
                'default' => 'query'
            ]
        ];

        $htmlForm = HTMLForm::factory('codex', $formDescriptor, $this->getContext(), 'queryform');
        $htmlForm->setSubmitTextMsg('aiseometa-query-submit');
        $htmlForm->setSubmitCallback([$this, 'processQueryForm']);
        $htmlForm->show();
    }

    public function processQueryForm($formData) {
        if (($formData['action_type'] ?? '') !== 'query') return false;

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
            ],
            'action_type' => [
                'type' => 'hidden',
                'default' => 'batch'
            ]
        ];

        $htmlForm = HTMLForm::factory('codex', $formDescriptor, $this->getContext(), 'batchform');
        $htmlForm->setSubmitTextMsg('aiseometa-batch-submit');
        $htmlForm->setSubmitCallback([$this, 'processBatchForm']);
        $htmlForm->show();
    }

    public function processBatchForm($formData) {
        if (($formData['action_type'] ?? '') !== 'batch') return false;

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
