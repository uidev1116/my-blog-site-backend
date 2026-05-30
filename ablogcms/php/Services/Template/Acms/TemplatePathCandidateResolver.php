<?php

namespace Acms\Services\Template\Acms;

use Acms\Services\Template\Contracts\TemplatePathCandidateResolverInterface;

/**
 * 拡張子ありはそのパスのみを候補とする。
 * 拡張子なしは候補なし（補完責務は呼び出し側に置く）。
 * pathinfo で filename が取れないパスは候補なし。
 */
class TemplatePathCandidateResolver implements TemplatePathCandidateResolverInterface
{
    /**
     * @return list<string>
     */
    public function getCandidates(string $path): array
    {
        $normalized = preg_replace('#/{2,}#', '/', str_replace('\\', '/', trim($path)));
        if (!is_string($normalized) || $normalized === '') {
            return [];
        }

        $path = $normalized;

        $info = pathinfo($path);
        $filename = $info['filename'];
        $extension = $info['extension'] ?? '';

        if ($filename === '' || $extension === '') {
            return [];
        }

        return [$path];
    }
}
