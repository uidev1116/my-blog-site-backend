<?php

namespace Acms\Services\Common;

use RecursiveArrayIterator;

/**
 * children キーを持つ配列構造を再帰的に処理するためのイテレータ
 */
final class ChildrenRecursiveIterator extends RecursiveArrayIterator
{
    public function hasChildren(): bool
    {
        $cur = $this->current();
        $hasChildren = is_array($cur)
            && isset($cur['children'])
            && is_array($cur['children']);

        return $hasChildren;
    }

    public function getChildren(): self
    {
        /** @var array<int, array<string, mixed>> $kids */
        $kids = $this->current()['children'];
        return new self($kids);
    }
}
