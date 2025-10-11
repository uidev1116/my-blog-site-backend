<?php

namespace Acms\Services\Unit\Contracts;

interface ImageUnit
{
    /**
     * メイン画像のパスを取得。
     *
     * @return string[]
     */
    public function getPaths(): array;

    /**
     * メイン画像のAltを取得
     *
     * @return string[]
     */
    public function getAlts(): array;

    /**
     * メイン画像のキャプションを取得
     *
     * @return string[]
     */
    public function getCaptions(): array;

    /**
     * メイン画像かどうか
     *
     * @return bool
     */
    public function isPrimaryImage(): bool;

    /**
     * メイン画像かどうかを設定
     *
     * @param bool $isPrimaryImage
     * @return void
     */
    public function setIsPrimaryImage(bool $isPrimaryImage): void;

    /**
     * メイン画像に設定可能な状態かどうか
     *
     * @return bool
     */
    public function canBePrimaryImage(): bool;
}
