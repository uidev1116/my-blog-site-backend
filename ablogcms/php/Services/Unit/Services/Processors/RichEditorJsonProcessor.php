<?php

namespace Acms\Services\Unit\Services\Processors;

use Acms\Services\Unit\Contracts\JsonNodeProcessorInterface;
use Acms\Services\Facades\RichEditor as RichEditorHelper;

/**
 * リッチエディター用JSONノードプロセッサー
 */
class RichEditorJsonProcessor implements JsonNodeProcessorInterface
{
    /**
     * @inheritDoc
     */
    public function getTargetUnitType(): string
    {
        return 'rich-editor';
    }

    /**
     * @inheritDoc
     */
    public function process(array $node): array
    {
        $json = $node['attributes']['json'] ?? '';
        if ($json !== '') {
            $node['attributes']['json'] = json_encode([
                'html' => RichEditorHelper::render($json),
                'title' => RichEditorHelper::renderTitle($json),
            ]);
        }
        return $node;
    }
}
