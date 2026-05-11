<?php

declare(strict_types=1);

namespace Acms\Services\Process\Handler;

use Acms\Services\Process\Contracts\ResponseHandlerInterface;
use Acms\Services\Process\ProcessResult;

/**
 * stdout の生文字列を返すシンプルなハンドラ。
 */
final class RawResponseHandler implements ResponseHandlerInterface
{
    public function handle(ProcessResult $result): string
    {
        return $result->getStdout();
    }
}
