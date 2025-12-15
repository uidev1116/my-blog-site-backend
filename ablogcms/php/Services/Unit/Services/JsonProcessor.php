<?php

namespace Acms\Services\Unit\Services;

use Acms\Services\Unit\Contracts\JsonNodeProcessorInterface;

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
        $result = [];
        foreach ($data as $key => $node) {
            // まず子ノードを再帰的に処理
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->replaceNodesByType($node['children'], $replacer);
            }

            // 次にこのノード自体を処理
            /** @var array<string, mixed> $newNode */
            $newNode = $replacer($node);
            $result[$key] = $newNode;
        }

        return $result;
    }
}
