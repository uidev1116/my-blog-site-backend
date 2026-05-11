<?php

declare(strict_types=1);

namespace Acms\Services\Process\Contracts;

use Acms\Services\Process\ProcessTask;

/**
 * プロセス起動前に呼ばれるハンドラのインターフェース。
 *  false を返すとそのタスクは起動されずスキップされる。
 */
interface TaskStartHandlerInterface
{
    /**
     * タスクのプロセスを起動する直前に呼ばれる。
     *
     * @param ProcessTask $task これから起動するタスク
     * @return bool true の場合は起動する。false の場合は起動せずスキップする
     */
    public function handle(ProcessTask $task): bool;
}
