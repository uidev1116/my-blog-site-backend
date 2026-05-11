<?php

declare(strict_types=1);

namespace Acms\Services\PageGeneration\Handler;

use Acms\Services\PageGeneration\Contracts\PageGenerationHandlerBase;
use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\Process\ProcessTask;
use Acms\Services\Process\Contracts\TaskStartHandlerInterface;

class PageGenerationTaskStartHandler extends PageGenerationHandlerBase implements TaskStartHandlerInterface
{
    public function handle(ProcessTask $task): bool
    {
        $context = $task->getContext();
        $page = $context['page'] ?? null;

        if (!$page instanceof Page) {
            throw new \RuntimeException('Page is missing from task context.');
        }
        return $this->listener->onPageGenerationStart($page);
    }
}
