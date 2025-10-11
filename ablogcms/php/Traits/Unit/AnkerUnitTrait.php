<?php

namespace Acms\Traits\Unit;

/**
 * アンカー機能を提供するトレイト
 */
trait AnkerUnitTrait
{
    /**
     * アンカー
     * @var string
     */
    private $anker = '';

    /**
     * anker getter
     *
     * @return string
     */
    public function getAnker(): string
    {
        return $this->anker;
    }

    /**
     * anker setter
     *
     * @param string $anker
     * @return void
     */
    public function setAnker(string $anker): void
    {
        $this->anker = $anker;
    }
}
