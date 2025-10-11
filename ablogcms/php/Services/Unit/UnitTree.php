<?php

namespace Acms\Services\Unit;

class UnitTree
{
    /** @var UnitTreeNode[] */
    private $roots;

    /**
     * @param UnitTreeNode[] $roots
     */
    public function __construct(array $roots)
    {
        $this->roots = $roots;
    }

    /**
     * @return UnitTreeNode[]
     */
    public function getRoots(): array
    {
        return $this->roots;
    }
}
