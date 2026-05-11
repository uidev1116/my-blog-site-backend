<?php

declare(strict_types=1);

namespace Acms\Services\Process\Contracts;

use Acms\Services\Process\ProcessResult;

/**
 * 成功時の結果処理を定義するインターフェース。
 */
interface ResponseHandlerInterface
{
    /**
     * @return mixed 呼び出し側が受け取りたい任意の型
     */
    public function handle(ProcessResult $result): mixed;
}
