<?php

declare(strict_types=1);

namespace Acms\Services\PageGeneration\Contracts;

use Acms\Services\PageGeneration\Entities\Page;
use Acms\Services\Process\ProcessResult;

interface PageGenerationListenerInterface
{
    public function onPageGenerationSuccess(Page $page, string $stdout, int $statusCode, ProcessResult $result): void;

    public function onPageGenerationError(Page $page, int $statusCode, ProcessResult $result): void;

    public function onPageGenerationStart(Page $page): bool;
}
