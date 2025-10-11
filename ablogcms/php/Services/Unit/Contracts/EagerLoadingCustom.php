<?php

namespace Acms\Services\Unit\Contracts;

use Field;

interface EagerLoadingCustom
{
    /**
     * カスタムフィールドを取得
     *
     * @return Field|null
     */
    public function getCustomUnitField(): ?Field;

    /**
     * 事前読み込みされたカスタムフィールドマップを設定
     *
     * @param array $fields
     * @return void
     */
    public function setEagerLoadedCustomUnitFields(array $fields): void;
}
