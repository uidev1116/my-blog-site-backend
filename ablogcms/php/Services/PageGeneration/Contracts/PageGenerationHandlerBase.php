<?php

declare(strict_types=1);

namespace Acms\Services\PageGeneration\Contracts;

use Acms\Services\PageGeneration\Contracts\PageGenerationListenerInterface;

abstract class PageGenerationHandlerBase
{
    public function __construct(
        protected readonly PageGenerationListenerInterface $listener,
    ) {
    }

    /**
     * stderr 文字列からHTTPステータスコードを抽出する
     * - 末尾にある「数字だけの行」を優先
     * - 見つからなければ 200 を返す
     *
     * @param string $stderr
     * @return int
     */
    public function extractStatusCodeFromStderr(string $stderr): int
    {
        $stderr = trim($stderr);
        if ($stderr === '') {
            return 200;
        }
        $split = preg_split("/\r\n|\n|\r/", $stderr);
        $lines = $split !== false ? $split : [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line !== '' && preg_match('/^\d{3}$/', $line)) {
                return (int) $line;
            }
        }
        if (preg_match('/^\d{3}$/', $stderr)) {
            return (int) $stderr;
        }
        return 200;
    }
}
