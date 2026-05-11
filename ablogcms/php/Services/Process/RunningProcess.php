<?php

declare(strict_types=1);

namespace Acms\Services\Process;

/**
 * 実行中の子プロセスと I/O パイプを表すオブジェクト。
 *
 * ProcOpenRunner からのみ操作される前提。
 *
 * @internal
 */
final class RunningProcess
{
    /** @var resource */
    private $process;

    /** @var array<int,resource|null> */
    private array $pipes;

    private ProcessTask $task;

    private float $startedAt;

    private string $stdoutBuffer = '';

    private string $stderrBuffer = '';

    private bool $finished = false;

    private ?int $exitCode = null;

    private bool $timedOut = false;

    /**
     * proc_get_status で初回に取得した終了コード（PHP 8.3 未満で proc_close が -1 を返す対策）
     */
    private ?int $exitCodeFromProcStatus = null;

    /**
     * @param resource $process
     * @param array<int,resource|null> $pipes
     */
    public function __construct($process, array $pipes, ProcessTask $task, float $startedAt)
    {
        $this->process = $process;
        $this->pipes = $pipes;
        $this->task = $task;
        $this->startedAt = $startedAt;
    }

    /**
     * @return resource
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @return array<int,resource|null>
     */
    public function getPipes(): array
    {
        return $this->pipes;
    }

    /**
     * @param int $index
     * @param resource|null $pipe
     * @return void
     */
    public function setPipe(int $index, $pipe): void
    {
        $this->pipes[$index] = $pipe;
    }

    public function getTask(): ProcessTask
    {
        return $this->task;
    }

    public function getStartedAt(): float
    {
        return $this->startedAt;
    }

    public function appendStdout(string $data): void
    {
        $this->stdoutBuffer .= $data;
    }

    public function appendStderr(string $data): void
    {
        $this->stderrBuffer .= $data;
    }

    public function getStdoutBuffer(): string
    {
        return $this->stdoutBuffer;
    }

    public function getStderrBuffer(): string
    {
        return $this->stderrBuffer;
    }

    public function markFinished(int $exitCode, bool $timedOut): void
    {
        $this->finished = true;
        $this->exitCode = $exitCode;
        $this->timedOut = $timedOut;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * proc_get_status で取得した終了コードを保存（初回のみ。PHP 8.3 未満の proc_close 対策）
     */
    public function setExitCodeFromProcStatus(int $code): void
    {
        if ($this->exitCodeFromProcStatus === null) {
            $this->exitCodeFromProcStatus = $code;
        }
    }

    public function getExitCodeFromProcStatus(): ?int
    {
        return $this->exitCodeFromProcStatus;
    }

    public function isTimedOut(): bool
    {
        return $this->timedOut;
    }
}
