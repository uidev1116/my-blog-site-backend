<?php

declare(strict_types=1);

namespace Acms\Services\Process;

/**
 * 子プロセス実行の結果を表す値オブジェクト。
 */
final class ProcessResult
{
    public function __construct(
        private ProcessTask $task,
        private string $stdout,
        private string $stderr,
        private int $exitCode,
        private bool $timedOut,
    ) {
    }

    public function getTask(): ProcessTask
    {
        return $this->task;
    }

    public function getStdout(): string
    {
        return $this->stdout;
    }

    public function getStderr(): string
    {
        return $this->stderr;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }

    public function isSuccessful(): bool
    {
        return !$this->timedOut && $this->exitCode === 0;
    }
}
