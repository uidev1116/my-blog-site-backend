<?php

namespace Acms\Services\Unit\Contracts;

/**
 * JSONノード処理の戦略インターフェース
 */
interface JsonNodeProcessorInterface
{
    /**
     * 処理対象のユニットタイプを取得
     *
     * @return string
     */
    public function getTargetUnitType(): string;

    /**
     * ノードを処理する
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    public function process(array $node): array;
}
