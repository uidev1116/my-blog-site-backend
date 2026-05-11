<?php

declare(strict_types=1);

namespace Acms\Services\Process;

use RuntimeException;

/**
 * proc_open による子プロセス起動と I/O をカプセル化するクラス。
 */
final class ProcOpenRunner
{
    /**
     * プロセスを起動し、RunningProcess を返す。
     *
     * @param ProcessTask $task
     * @return RunningProcess
     * @throws RuntimeException
     */
    public function start(ProcessTask $task): RunningProcess
    {
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $command = $task->getCommand();
        if ($command === []) {
            throw new RuntimeException('Empty command for ProcessTask');
        }

        $pipes = [];
        $process = proc_open(
            array_values($command),
            $descriptorspec,
            $pipes,
            $task->getCwd() ?? null,
            $task->getEnv() ?? null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start process via proc_open');
        }

        // パイプを非ブロッキングに設定
        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        // stdin がある場合は一度だけ書き込んで閉じる
        $stdin = $task->getStdin();
        if ($stdin !== null && $stdin !== '') {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $pipes[0] = null;

        return new RunningProcess($process, $pipes, $task, microtime(true));
    }

    /**
     * 非ブロッキングで stdout / stderr を読み取る。
     *
     * @param RunningProcess $running
     * @return void
     */
    public function poll(RunningProcess $running): void
    {
        $pipes = $running->getPipes();
        $stdout = $pipes[1] ?? null;
        $stderr = $pipes[2] ?? null;

        $read = [];
        if (is_resource($stdout)) {
            $read[] = $stdout;
        }
        if (is_resource($stderr)) {
            $read[] = $stderr;
        }

        if ($read === []) {
            return;
        }

        $write = null;
        $except = null;
        // 0秒タイムアウトで即時戻る
        if (stream_select($read, $write, $except, 0, 0) === false) {
            return;
        }

        foreach ($read as $r) {
            $data = stream_get_contents($r);
            if ($data === false || $data === '') {
                continue;
            }

            if ($r === $stdout) {
                $running->appendStdout($data);
            } elseif ($r === $stderr) {
                $running->appendStderr($data);
            }
        }
    }

    /**
     * タイムアウトをチェックし、必要ならプロセスを terminate する。
     *
     * @param RunningProcess $running
     * @param float $now
     * @return void
     */
    public function checkTimeoutAndTerminate(RunningProcess $running, float $now): void
    {
        $task = $running->getTask();
        $timeout = $task->getTimeoutSeconds();
        if ($timeout === null || $running->isFinished()) {
            return;
        }

        if (($now - $running->getStartedAt()) <= $timeout) {
            return;
        }

        $process = $running->getProcess();
        proc_terminate($process);

        $running->markFinished($running->getExitCode() ?? 1, true);
    }

    /**
     * プロセス終了処理と結果オブジェクトの生成。
     *
     * @param RunningProcess $running
     * @return ProcessResult
     */
    public function finish(RunningProcess $running): ProcessResult
    {
        // 残りの出力を読み切る
        $pipes = $running->getPipes();
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            $data = stream_get_contents($pipes[1]);
            if ($data !== false && $data !== '') {
                $running->appendStdout($data);
            }
            fclose($pipes[1]);
            $running->setPipe(1, null);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            $data = stream_get_contents($pipes[2]);
            if ($data !== false && $data !== '') {
                $running->appendStderr($data);
            }
            fclose($pipes[2]);
            $running->setPipe(2, null);
        }

        // PHP 8.3 未満: proc_get_status を先に呼んでいると proc_close が -1 を返すため、保存済みの終了コードを優先する
        $exitCode = $running->getExitCode() ?? $running->getExitCodeFromProcStatus();
        if ($exitCode === null) {
            $exitCode = proc_close($running->getProcess());
        } else {
            proc_close($running->getProcess());
        }

        return new ProcessResult(
            $running->getTask(),
            $running->getStdoutBuffer(),
            $running->getStderrBuffer(),
            $exitCode,
            $running->isTimedOut()
        );
    }

    /**
     * プロセスがすでに終了しているか判定するためのヘルパー。
     *
     * @param RunningProcess $running
     * @return bool
     */
    public function isProcessTerminated(RunningProcess $running): bool
    {
        $status = proc_get_status($running->getProcess());
        if ($status['running'] === false) {
            // PHP 8.3 未満では proc_get_status を呼ぶと proc_close が -1 を返すため、初回の終了コードを保存する
            $running->setExitCodeFromProcStatus($status['exitcode']);
            return true;
        }
        return false;
    }
}
