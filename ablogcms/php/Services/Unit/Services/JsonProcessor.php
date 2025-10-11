<?php

namespace Acms\Services\Unit\Services;

use Acms\Services\Common\ChildrenRecursiveIterator;
use Acms\Services\Unit\Contracts\JsonNodeProcessorInterface;
use RecursiveIteratorIterator;

/**
 * 汎用JSON処理クラス
 */
class JsonProcessor
{
    /**
     * @var JsonNodeProcessorInterface[]
     */
    private array $processors = [];

    public function __construct()
    {
    }

    /**
     * プロセッサーを登録
     *
     * @param JsonNodeProcessorInterface $processor
     * @return void
     */
    public function registerProcessor(JsonNodeProcessorInterface $processor): void
    {
        $this->processors[$processor->getTargetUnitType()] = $processor;
    }

    /**
     * JSONを処理
     *
     * @param array<int, array<string, mixed>> $json
     * @return array<int, array<string, mixed>>
     */
    public function process(array $json): array
    {
        return $this->replaceNodesByType($json, function (array $node): array {
            $type = $node['type'] ?? '';
            $processor = $this->processors[$type] ?? null;

            if ($processor) {
                /** @var array<string, mixed> $processedNode */
                $processedNode = $processor->process($node);
                return $processedNode;
            }

            return $node;
        });
    }

    /**
     * 再帰的にノードを処理
     *
     * @param array<int, array<string, mixed>> $data
     * @param callable(array<string, mixed>): array<string, mixed> $replacer
     * @return array<int, array<string, mixed>>
     */
    private function replaceNodesByType(array $data, callable $replacer): array
    {
        $rit = new RecursiveIteratorIterator(
            new ChildrenRecursiveIterator($data),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($rit as $key => $node) {
            if (!is_array($node)) {
                continue;
            }

            /** @var array<string, mixed> $newNode */
            $newNode = $replacer($node);
            $rit->getSubIterator()[$key] = $newNode;
        }

        /** @var \RecursiveArrayIterator $root */
        $root = $rit->getSubIterator(0);
        return $root->getArrayCopy();
    }
}
