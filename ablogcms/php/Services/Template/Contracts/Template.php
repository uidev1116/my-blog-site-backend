<?php

namespace Acms\Services\Template\Contracts;

interface Template
{
    /**
     * パスからテンプレートをロード
     *
     * @param string $path
     * @param string $theme
     * @param int $bid
     * @return void
     */
    public function load(string $path, string $theme, int $bid): void;

    /**
     * 文字列からテンプレートをロード
     *
     * @param string $txt
     * @param string $path
     * @param string $theme
     * @param int $bid
     * @return void
     */
    public function loadFromString(string $txt, string $path, string $theme, int $bid): void;

    /**
     * レンダリング
     *
     * @return string
     */
    public function render(): string;
}
