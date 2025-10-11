<?php

namespace Acms\Services\Update\Logger;

use Acms\Services\Update\Contracts\LoggerInterface;
use Acms\Services\Facades\Logger;

class Auto implements LoggerInterface
{
    /**
     * 初期化
     *
     * @return void
     */
    public function init(): void
    {
    }

    /**
     * メッセージを表示
     *
     * @param string $message
     * @param int $percentage
     * @param int $status
     */
    public function message(string $message, int $percentage = 0, int $status = 1): void
    {
        Logger::debug("システム更新: {$message}");
    }

    /**
     * 進行状況（パーセント）を追加
     *
     * @param int $percentage
     * @return void
     */
    public function incrementProgress($percentage = 0): void
    {
    }

    /**
     * ファイルのアップデート成功時
     *
     * @return void
     */
    public function fileUpdateSuccess(): void
    {
        Logger::debug('システム更新: ファイルの展開に成功しました');
    }

    /**
     * アップデート成功時
     *
     * @return void
     */
    public function dbUpdateSuccess(): void
    {
        Logger::debug('システム更新: DBの更新に成功しました');
    }

    /**
     * アップデート失敗時
     *
     * @param string $message
     * @return void
     */
    public function failure(string $message): void
    {
        Logger::error("システム更新: {$message}");
    }

    /**
     * アップデート終了時
     *
     * @return void
     */
    public function terminate(): void
    {
    }
}
