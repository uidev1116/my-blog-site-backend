<?php

namespace Acms\Services\Unit\Contracts;

use Acms\Services\Unit\Constants\UnitAlign;

/**
 * 配置機能を提供するインターフェース
 */
interface AlignableUnitInterface
{
    /**
     * align getter
     *
     * @return UnitAlign
     */
    public function getAlign(): UnitAlign;

    /**
     * align setter
     *
     * @param UnitAlign $align
     * @return void
     */
    public function setAlign(UnitAlign $align): void;
}
