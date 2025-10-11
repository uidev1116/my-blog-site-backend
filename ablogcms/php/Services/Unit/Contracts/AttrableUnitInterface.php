<?php

namespace Acms\Services\Unit\Contracts;

/**
 * attr 機能を提供するインターフェース
 */
interface AttrableUnitInterface
{
    /**
     * attr getter
     *
     * @return string
     */
    public function getAttr(): string;

    /**
     * attr setter
     *
     * @param string $attr
     * @return void
     */
    public function setAttr(string $attr): void;
}
