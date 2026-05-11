<?php

declare(strict_types=1);

namespace Acms\Services\Process\Handler;

use Acms\Services\Process\Contracts\TaskStartHandlerInterface;
use Acms\Services\Process\ProcessTask;

/**
 * プロセス起動前に呼ばれるハンドラのインターフェース。
 *  false を返すとそのタスクは起動されずスキップされる。
 */
final class TaskStartHandler implements TaskStartHandlerInterface
{
    public function handle(ProcessTask $task): bool
    {
        return true;
    }
}
