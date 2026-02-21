<?php
namespace AISEOMeta;

use SpecialPage;
use HTMLForm;
use Title;
use MediaWiki\MediaWikiServices;
use AISEOMeta\Job\GenerateMetaJob;

class SpecialAISEOMeta extends SpecialPage {
    public function __construct() {
        parent::__construct('AISEOMeta', 'edit');
    }

    public function execute($subPage) {
        $this->setHeaders();
        $this->checkPermissions();
        $this->outputHeader();

        $this->showConfig();
        $this->showQueryForm();
        $this->showBatchForm();
    }

    private function showConfig() {
        $out = $this->getOutput();
        $config = MediaWikiServices::getInstance()->getMainConfig();
        
        $provider = $config->get('ASMProvider');
        $openAiKey = $config->get('ASMOpenAIKey') ? '✅ Set' : '❌ Not set';
        $geminiKey = $config->get('ASMGeminiKey') ? '✅ Set' : '❌ Not set';

        $html = "<h2>" . $this->msg('aiseometa-config-title')->escaped() . "</h2>";
        $html .= "<ul>";
        $html .= "<li><b>Provider:</b> " . htmlspecialchars($provider) . "</li>";
        $html .= "<li><b>OpenAI Model:</b> " . htmlspecialchars($config->get('ASMOpenAIModel')) . "</li>";
        $html .= "<li><b>OpenAI Key:</b> " . $openAiKey . "</li>";
        $html .= "<li><b>Gemini Model:</b> " . htmlspecialchars($config->get('ASMGeminiModel')) . "</li>";
        $html .= "<li><b>Gemini Key:</b> " . $geminiKey . "</li>";
        $html .= "</ul>";

        $out->addHTML($html);
    }

    private function showQueryForm() {
        $out = $this->getOutput();

        $out->addHTML("<h2>" . $this->msg('aiseometa-query-title')->escaped() . "</h2>");

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

        $htmlForm = HTMLForm::factory('ooui', $formDescriptor, $this->getContext(), 'queryform');
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
            $out->addHTML("<p style='color:red;'>" . $this->msg('aiseometa-invalid-page')->escaped() . "</p>");
            return true;
        }

        $pageId = $title->getArticleID();
        $metaManager = new MetaManager();
        $tags = $metaManager->getTagsFromProps($pageId);
        $updated = $metaManager->getUpdateTime($pageId);

        $out->addHTML("<h3>" . $this->msg('aiseometa-result-title', $title->getPrefixedText())->escaped() . "</h3>");
        
        if ($updated) {
            $lang = $this->getLanguage();
            $timeStr = $lang->timeanddate($updated, true);
            $out->addHTML("<p><b>" . $this->msg('aiseometa-last-updated')->escaped() . ":</b> " . htmlspecialchars($timeStr) . "</p>");
        } else {
            $out->addHTML("<p><b>" . $this->msg('aiseometa-last-updated')->escaped() . ":</b> " . $this->msg('aiseometa-never')->escaped() . "</p>");
        }

        if (!empty($tags)) {
            $out->addHTML("<pre>" . htmlspecialchars(json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>");
        } else {
            $out->addHTML("<p>" . $this->msg('aiseometa-no-tags')->escaped() . "</p>");
        }

        $regenerateUrl = $this->getPageTitle()->getLocalURL(['action' => 'regenerate', 'page' => $title->getPrefixedDBkey()]);
        $out->addHTML("<p><a href='" . htmlspecialchars($regenerateUrl) . "' class='oo-ui-buttonElement-button'>" . $this->msg('aiseometa-regenerate-btn')->escaped() . "</a></p>");

        return true;
    }

    private function showBatchForm() {
        $out = $this->getOutput();
        $request = $this->getRequest();

        if ($request->getVal('action') === 'regenerate' && $request->getVal('page')) {
            if ($this->pushJobForPage($request->getVal('page'))) {
                $out->addHTML("<p style='color:green;'>" . $this->msg('aiseometa-job-pushed', htmlspecialchars($request->getVal('page')))->escaped() . "</p>");
            }
        }

        $out->addHTML("<h2>" . $this->msg('aiseometa-batch-title')->escaped() . "</h2>");

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

        $htmlForm = HTMLForm::factory('ooui', $formDescriptor, $this->getContext(), 'batchform');
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

        $out->addHTML("<p style='color:green;'>" . $this->msg('aiseometa-batch-success', $count)->escaped() . "</p>");
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
