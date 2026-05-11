<?php

declare(strict_types=1);

namespace Acms\Services\Process;

use Acms\Services\Process\Contracts\ErrorHandlerInterface;
use Acms\Services\Process\Contracts\ResponseHandlerInterface;
use Acms\Services\Process\Contracts\TaskStartHandlerInterface;
use InvalidArgumentException;
use SplQueue;

/**
 * 並列数を制御しつつ、複数の ProcessTask を実行するプール。
 * 停止要求時は新規タスクを起動せず、実行中プロセスを強制終了して安全に終了する。
 */
final class ProcessPool
{
    /** @var SplQueue<ProcessTask> */
    private SplQueue $queue;

    /** @var array<int,RunningProcess> */
    private array $running = [];

    private int $maxParallel;

    private ProcOpenRunner $runner;

    private ResponseHandlerInterface $responseHandler;

    private ErrorHandlerInterface $errorHandler;

    private TaskStartHandlerInterface $taskStartHandler;

    private bool $stopRequested = false;

    public function __construct(
        int $maxParallel,
        ProcOpenRunner $runner,
        ResponseHandlerInterface $responseHandler,
        ErrorHandlerInterface $errorHandler,
        TaskStartHandlerInterface $taskStartHandler
    ) {
        if ($maxParallel <= 0) {
            throw new InvalidArgumentException('maxParallel must be >= 1');
        }
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('proc_open is not available.');
        }
        $this->maxParallel = $maxParallel;
        $this->runner = $runner;
        $this->responseHandler = $responseHandler;
        $this->errorHandler = $errorHandler;
        $this->taskStartHandler = $taskStartHandler;
        $this->queue = new SplQueue();
        $this->stopRequested = false;
    }

    public function addTask(ProcessTask $task): void
    {
        $this->queue->enqueue($task);
    }

    /**
     * 停止を要求する。
     * 呼び出し後、新規タスクは起動されず、実行中プロセスは強制終了され、
     * run() は残り結果を返して終了する。
     */
    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    /**
     * 停止が要求されているかどうか。
     */
    public function isStopRequested(): bool
    {
        return $this->stopRequested;
    }

    /**
     * キューに残っている未実行タスクの数。
     */
    public function getQueuedTaskCount(): int
    {
        return $this->queue->count();
    }

    /**
     * 全タスクを実行し、ハンドラの戻り値を配列で返す。
     * requestStop() により停止した場合は、強制終了されたプロセスは errorHandler で処理される。
     *
     * @return list<mixed>
     */
    public function run(): array
    {
        $results = [];

        while ((!$this->stopRequested && !$this->queue->isEmpty()) || $this->running !== []) {
            // Todo: ページネーションの生成で、先の404ページが来ると正常ページまでプロセスを終了してしまうので、一旦実行中プロセスについてはキャンセルしない方向で進める
            // 停止要求時は実行中プロセスを即時強制終了
            // if ($this->stopRequested && $this->running !== []) {
            //     foreach ($this->running as $running) {
            //         $process = $running->getProcess();
            //         if (is_resource($process) && $this->runner->isProcessTerminated($running) === false) {
            //             proc_terminate($process);
            //             $running->markFinished($running->getExitCode() ?? 143, true);
            //         }
            //     }
            // }

            // スロットが空いていて停止未要求ならキューから起動
            while (
                !$this->stopRequested
                && count($this->running) < $this->maxParallel
                && !$this->queue->isEmpty()
            ) {
                /** @var ProcessTask $task */
                $task = $this->queue->dequeue();
                $shouldStart = $this->taskStartHandler->handle($task);
                if (!$shouldStart) {
                    continue;
                }
                $running = $this->runner->start($task);
                $this->running[spl_object_id($running)] = $running;
            }

            $now = microtime(true);

            // 実行中プロセスをポーリング
            foreach ($this->running as $id => $running) {
                $this->runner->poll($running);
                $this->runner->checkTimeoutAndTerminate($running, $now);

                if ($this->runner->isProcessTerminated($running) || $running->isTimedOut()) {
                    $processResult = $this->runner->finish($running);
                    unset($this->running[$id]);

                    if ($processResult->isSuccessful()) {
                        $results[] = $this->responseHandler->handle($processResult);
                    } else {
                        $results[] = $this->errorHandler->handle(
                            $processResult->getTask(),
                            $processResult
                        );
                    }
                }
            }

            // CPU を焼かないため軽くスリープ
            if ($this->running !== []) {
                usleep(10000); // 10ms
            }
        }

        return $results;
    }
}
