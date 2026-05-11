<?php

declare(strict_types=1);

namespace Acms\Services\Process;

/**
 * 実行するコマンドとそのコンテキストを表す値オブジェクト。
 */
final class ProcessTask
{
    /**
     * @param string[] $command コマンドと引数（例: ['php', 'console.php', 'job', '123']）
     * @param array<string,string>|null $env 追加の環境変数
     * @param array<string,mixed> $context 任意のコンテキスト情報（entryId など）
     */
    public function __construct(
        private array $command,
        private ?string $cwd = null,
        private ?array $env = null,
        private ?float $timeoutSeconds = null,
        private ?string $stdin = null,
        private array $context = [],
    ) {
    }

    /**
     * @return string[]
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function getCwd(): ?string
    {
        return $this->cwd;
    }

    /**
     * @return array<string,string>|null
     */
    public function getEnv(): ?array
    {
        return $this->env;
    }

    public function getTimeoutSeconds(): ?float
    {
        return $this->timeoutSeconds;
    }

    public function getStdin(): ?string
    {
        return $this->stdin;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
