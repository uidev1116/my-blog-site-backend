<?php

namespace Acms\Services\Unit\Services\Processors;

use Acms\Services\Unit\Contracts\JsonNodeProcessorInterface;
use Acms\Services\BlockEditor\Helper;
use Acms\Services\Unit\Models\BlockEditor;
use Acms\Services\Facades\Application;

/**
 * ブロックエディタ用JSONノードプロセッサー
 */
class BlockEditorJsonProcessor implements JsonNodeProcessorInterface
{
    private Helper $helper;

    public function __construct()
    {
        $this->helper = Application::make('block-editor');
    }

    /**
     * @inheritDoc
     */
    public function getTargetUnitType(): string
    {
        return BlockEditor::getUnitType();
    }

    /**
     * @inheritDoc
     */
    public function process(array $node): array
    {
        $html = $node['attributes']['html'] ?? '';
        if ($html !== '') {
            $fixedHtml = $this->helper->fix(html: $html, resizeImage: false);
            $node['attributes']['html'] = $fixedHtml;
        }
        return $node;
    }
}
