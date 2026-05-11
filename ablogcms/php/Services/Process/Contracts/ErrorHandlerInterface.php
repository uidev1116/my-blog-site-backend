<?php

declare(strict_types=1);

namespace Acms\Services\Process\Contracts;

use Acms\Services\Process\ProcessResult;
use Acms\Services\Process\ProcessTask;

/**
 * 失敗時のエラー処理を定義するインターフェース。
 */
interface ErrorHandlerInterface
{
    /**
     * @return mixed 例外を投げる・配列を返すなど自由
     */
    public function handle(ProcessTask $task, ProcessResult $result): mixed;
}
