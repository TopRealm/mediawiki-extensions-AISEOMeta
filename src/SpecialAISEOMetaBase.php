<?php
namespace AISEOMeta;

use SpecialPage;
use Html;

abstract class SpecialAISEOMetaBase extends SpecialPage {
    protected function getCodexMessage(string $type, string $text): string {
        $icon = Html::element('span', ['class' => 'cdx-message__icon']);
        $content = Html::rawElement('div', ['class' => 'cdx-message__content'], Html::element('p', [], $text));
        
        $role = $type === 'error' ? 'alert' : 'status';
        
        return Html::rawElement('div', [
            'class' => "cdx-message cdx-message--block cdx-message--{$type}",
            'role' => $role
        ], $icon . $content);
    }

    protected function getGroupName() {
        return 'aiseometa';
    }
}
