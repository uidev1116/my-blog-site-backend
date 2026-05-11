<?php

declare(strict_types=1);

namespace Acms\Services\Process\Handler;

use Acms\Services\Process\Contracts\ErrorHandlerInterface;
use Acms\Services\Process\ProcessResult;
use Acms\Services\Process\ProcessTask;

/**
 * 失敗時に配列を返すエラーハンドラ。
 */
final class ArrayErrorHandler implements ErrorHandlerInterface
{
    /**
     * @return array<string,mixed>
     */
    public function handle(ProcessTask $task, ProcessResult $result): array
    {
        return [
            'ok' => false,
            'exitCode' => $result->getExitCode(),
            'timedOut' => $result->isTimedOut(),
            'stderr' => $result->getStderr(),
            'stdout' => $result->getStdout(),
            'context' => $task->getContext(),
        ];
    }
}
