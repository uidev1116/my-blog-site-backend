<?php

namespace Acms\Services\Unit\Contracts;

/**
 * ユニット設定で特殊な処理を行うためのインターフェース
 * @phpstan-type UnitConfig array{
 *   id: string,
 *   name: string,
 *   collapsed: bool,
 *   type: string,
 *   align: string,
 *   group: string,
 *   size: string,
 *   edit: string,
 *   field_1: string,
 *   field_2: string,
 *   field_3: string,
 *   field_4: string,
 *   field_5: string,
 * }
 */
interface ConfigProcessable
{
    /**
     * ユニット設定の専用コンフィグ設定を処理
     *
     * @param UnitConfig $config
     * @return UnitConfig
     */
    public function processConfig(array $config): array;
}
