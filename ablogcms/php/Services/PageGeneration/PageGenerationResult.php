<?php

declare(strict_types=1);

namespace Acms\Services\PageGeneration;

use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\Process\ProcessResult;

/**
 * ページ生成結果を表す値オブジェクト。
 */
final class PageGenerationResult
{
    public function __construct(
        private readonly bool $success,
        private readonly int $statusCode,
        private readonly Page $page,
        private readonly ?string $data,
        private readonly ProcessResult $processResult,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getPage(): Page
    {
        return $this->page;
    }

    /**
     * 取得したページ本文。run() を withData=false で呼び出した場合は null。
     */
    public function getData(): ?string
    {
        return $this->data;
    }

    public function getProcessResult(): ProcessResult
    {
        return $this->processResult;
    }
}
