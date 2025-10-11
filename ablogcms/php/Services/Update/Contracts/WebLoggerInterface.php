<?php

namespace Acms\Services\Update\Contracts;

interface WebLoggerInterface
{
    /**
     * ログ書き出し先を取得
     *
     * @return string
     */
    public function getDestinationPath(): string;

    /**
     * ログ書き出し先を設定
     *
     * @param string $path
     * @return void
     */
    public function setDestinationPath(string $path): void;

    /**
     * ファイルからログをロード
     */
    public function load(): void;
}
