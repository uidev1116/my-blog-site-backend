<?php

declare(strict_types=1);

namespace Acms\Services\PageGeneration\Handler;

use Acms\Services\PageGeneration\Contracts\PageGenerationHandlerBase;
use Acms\Services\Process\Contracts\ErrorHandlerInterface;
use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\PageGeneration\PageGenerationResult;
use Acms\Services\Process\ProcessResult;
use Acms\Services\Process\ProcessTask;

class PageGenerationErrorHandler extends PageGenerationHandlerBase implements ErrorHandlerInterface
{
    public function handle(ProcessTask $task, ProcessResult $result): PageGenerationResult
    {
        $context = $task->getContext();
        $page = $context['page'] ?? null;
        $statusCode = $this->extractStatusCodeFromStderr($result->getStderr());

        if (!$page instanceof Page) {
            throw new \RuntimeException('Page is missing from task context.');
        }
        $this->listener->onPageGenerationError($page, $statusCode, $result);

        return new PageGenerationResult(
            success: false,
            statusCode: $statusCode,
            page: $page,
            data: null,
            processResult: $result,
        );
    }
}
