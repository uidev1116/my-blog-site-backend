<?php

namespace Acms\Services\Unit\Contracts;

/**
 * サイズ機能を提供するインターフェース
 */
interface SizeableUnitInterface
{
    /**
     * size getter
     *
     * @return string
     */
    public function getSize(): string;

    /**
     * size setter
     *
     * @param string $size
     * @return void
     */
    public function setSize(string $size): void;
}
