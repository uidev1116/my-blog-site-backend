<?php

namespace Acms\Services\Unit;

use Acms\Services\Unit\Contracts\Model;

class UnitTreeNode
{
    /**
     * @var Model
     */
    public $unit;

    /**
     * @var UnitTreeNode[]
     */
    public $children = [];

    /**
     * @param Model $unit
     */
    public function __construct(Model $unit)
    {
        $this->unit = $unit;
    }
}
