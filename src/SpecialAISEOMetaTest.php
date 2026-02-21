<?php
namespace AISEOMeta;

use HTMLForm;
use MediaWiki\MediaWikiServices;
use Html;

class SpecialAISEOMetaTest extends SpecialAISEOMetaBase {
    public function __construct() {
        parent::__construct('AISEOMetaTest', 'aiseometa-admin');
    }

    public function execute($subPage) {
        $this->setHeaders();
        $this->checkPermissions();
        $this->outputHeader();

        $this->getOutput()->addModuleStyles(['codex.styles-all']);

        $this->showConfig();
        $this->showTestForm();
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

        $targetNamespaces = $config->get('ASMTargetNamespaces');
        $listItems .= Html::rawElement('li', [], Html::element('b', [], 'Target Namespaces: ') . htmlspecialchars(implode(', ', $targetNamespaces)));

        $out->addHTML(Html::rawElement('ul', [], $listItems));
    }

    private function showTestForm() {
        $out = $this->getOutput();

        $out->addHTML(Html::element('h2', [], $this->msg('aiseometa-test-title')->text()));

        $formDescriptor = [
            'testmessage' => [
                'type' => 'text',
                'label-message' => 'aiseometa-test-message',
                'default' => 'Hello, are you working?',
                'required' => true,
            ]
        ];

        $htmlForm = HTMLForm::factory('codex', $formDescriptor, $this->getContext(), 'testform');
        $htmlForm->setSubmitTextMsg('aiseometa-test-submit');
        $htmlForm->setSubmitCallback([$this, 'processTestForm']);
        $htmlForm->show();
    }

    public function processTestForm($formData) {
        $message = $formData['testmessage'];
        $out = $this->getOutput();

        $config = MediaWikiServices::getInstance()->getMainConfig();
        $providerName = $config->get('ASMProvider');

        try {
            if ($providerName === 'gemini') {
                $provider = new \AISEOMeta\Provider\GeminiProvider();
            } else {
                $provider = new \AISEOMeta\Provider\OpenAIProvider();
            }

            $response = $provider->testConnection($message);
            
            $out->addHTML($this->getCodexMessage('success', $this->msg('aiseometa-test-success')->text() . ' ' . htmlspecialchars($response)));
        } catch (\Exception $e) {
            $out->addHTML($this->getCodexMessage('error', $this->msg('aiseometa-test-error', $e->getMessage())->text()));
        }

        return true;
    }
}
