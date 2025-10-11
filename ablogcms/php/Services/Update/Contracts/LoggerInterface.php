<?php

namespace Acms\Services\Update\Contracts;

interface LoggerInterface
{
    /**
     * 初期化
     *
     * @return void
     */
    public function init(): void;

    /**
     * メッセージを表示
     *
     * @param string $message
     * @param int $percentage
     * @param int $status
     */
    public function message(string $message, int $percentage = 0, int $status = 1): void;

    /**
     * 進行状況（パーセント）を追加
     *
     * @param int $percentage
     * @return void
     */
    public function incrementProgress($percentage = 0): void;

    /**
     * ファイルのアップデート成功時
     *
     * @return void
     */
    public function fileUpdateSuccess(): void;

    /**
     * アップデート成功時
     *
     * @return void
     */
    public function dbUpdateSuccess(): void;

    /**
     * アップデート失敗時
     *
     * @param string $message
     * @return void
     */
    public function failure(string $message): void;

    /**
     * アップデート終了時
     *
     * @return void
     */
    public function terminate(): void;
}
