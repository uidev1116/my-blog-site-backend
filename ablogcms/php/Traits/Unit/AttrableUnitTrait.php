<?php

namespace Acms\Traits\Unit;

/**
 * attr 機能を提供するトレイト
 */
trait AttrableUnitTrait
{
    /**
     * 属性
     * @var string
     */
    private $attr = '';

    /**
     * attr getter
     *
     * @return string
     */
    public function getAttr(): string
    {
        return $this->attr;
    }

    /**
     * attr setter
     *
     * @param string $attr
     * @return void
     */
    public function setAttr(string $attr): void
    {
        $this->attr = $attr;
    }
}
