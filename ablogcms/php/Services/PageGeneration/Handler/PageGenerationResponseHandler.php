<?php

declare(strict_types=1);

namespace Acms\Services\PageGeneration\Handler;

use Acms\Services\PageGeneration\Contracts\PageGenerationHandlerBase;
use Acms\Services\Process\Contracts\ResponseHandlerInterface;
use Acms\Services\PageGeneration\Contracts\PageGenerationListenerInterface;
use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\PageGeneration\PageGenerationResult;
use Acms\Services\Process\ProcessResult;

class PageGenerationResponseHandler extends PageGenerationHandlerBase implements ResponseHandlerInterface
{
    public function __construct(
        PageGenerationListenerInterface $listener,
        protected readonly bool $withData = false,
    ) {
        parent::__construct($listener);
    }

    public function handle(ProcessResult $result): PageGenerationResult
    {
        $context = $result->getTask()->getContext();
        $page = $context['page'] ?? null;
        $statusCode = $this->extractStatusCodeFromStderr($result->getStderr());

        if (!$page instanceof Page) {
            throw new \RuntimeException('Page is missing from task context.');
        }
        $this->listener->onPageGenerationSuccess($page, $result->getStdout(), $statusCode, $result);

        return new PageGenerationResult(
            success: true,
            statusCode: $statusCode,
            page: $page,
            data: $this->withData ? $result->getStdout() : null,
            processResult: $result,
        );
    }
}
