<?php

namespace Acms\Services\Unit\Contracts;

interface AssetProvider
{
    /**
     * ファイルのパスを配列で取得
     *
     * @return string[]
     */
    public function getFilePaths(): array;

    /**
     * ファイルのパスを配列でセット
     *
     * @param string[]|string $paths
     * @return void
     */
    public function setFilePaths($paths): void;

    /**
     * ファイルを保存する
     *
     * @param array $post $_POSTデータ
     * @return void
     */
    public function saveFiles(array $post): void;
}
