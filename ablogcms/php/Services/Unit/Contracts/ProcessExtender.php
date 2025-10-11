<?php

namespace Acms\Services\Unit\Contracts;

interface ProcessExtender
{
    /**
     * ユニットロード時に拡張処理を行います
     *
     * @return void
     */
    public function extendOnLoad(): void;

    /**
     * ユニット保存時に拡張処理を行います
     *
     * @return void
     */
    public function extendOnSave(): void;
}
