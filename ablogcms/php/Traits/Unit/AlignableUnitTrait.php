<?php

namespace Acms\Traits\Unit;

use Acms\Services\Unit\Constants\UnitAlign;

/**
 * 配置機能を提供するトレイト
 */
trait AlignableUnitTrait
{
    /**
     * 配置
     * @var UnitAlign
     */
    private $align = UnitAlign::CENTER;

    /**
     * align getter
     *
     * @return UnitAlign
     */
    public function getAlign(): UnitAlign
    {
        $align = $this->align;
        $version = config('unit_align_version', 'v2');
        if ($version === 'v2' && $align === UnitAlign::AUTO) {
            // v2ではautoはcenterに変換する
            return UnitAlign::CENTER;
        }
        return $align;
    }

    /**
     * align setter
     *
     * @param UnitAlign $align
     * @return void
     */
    public function setAlign(UnitAlign $align): void
    {
        $version = config('unit_align_version', 'v2');
        if ($version === 'v2' && $align === UnitAlign::AUTO) {
            $align = UnitAlign::CENTER;
        }
        $this->align = $align;
    }
}
