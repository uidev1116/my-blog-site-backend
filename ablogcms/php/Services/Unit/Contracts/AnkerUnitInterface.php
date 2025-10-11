<?php

namespace Acms\Services\Unit\Contracts;

/**
 * アンカー機能を提供するインターフェース
 */
interface AnkerUnitInterface
{
    /**
     * anker getter
     *
     * @return string
     */
    public function getAnker(): string;

    /**
     * anker setter
     *
     * @param string $anker
     * @return void
     */
    public function setAnker(string $anker): void;
}
