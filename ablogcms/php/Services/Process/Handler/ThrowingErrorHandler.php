<?php

declare(strict_types=1);

namespace Acms\Services\Process\Handler;

use Acms\Services\Process\Contracts\ErrorHandlerInterface;
use Acms\Services\Process\ProcessResult;
use Acms\Services\Process\ProcessTask;
use RuntimeException;

/**
 * 失敗時に例外を投げるエラーハンドラ。
 */
final class ThrowingErrorHandler implements ErrorHandlerInterface
{
    public function handle(ProcessTask $task, ProcessResult $result): never
    {
        $context = $task->getContext();
        $contextSummary = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $message = sprintf(
            'Process failed (exitCode=%d, timedOut=%s). stderr=%s context=%s',
            $result->getExitCode(),
            $result->isTimedOut() ? 'true' : 'false',
            substr($result->getStderr(), 0, 500),
            $contextSummary !== false ? $contextSummary : 'null'
        );

        throw new RuntimeException($message);
    }
}
