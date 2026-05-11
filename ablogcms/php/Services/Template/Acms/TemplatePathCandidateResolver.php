<?php

namespace Acms\Services\Template\Acms;

use Acms\Services\Template\Contracts\TemplatePathCandidateResolverInterface;

/**
 * 拡張子ありはそのパスのみ、拡張子なしは .html を付与したパス。
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
        $rawDirname = $info['dirname'];
        $dirname = ($rawDirname !== '.' && $rawDirname !== '')
            ? $rawDirname . '/'
            : '';
        $filename = $info['filename'];
        $extension = $info['extension'] ?? '';

        if ($filename === '') {
            return [];
        }

        if ($extension !== '') {
            return [$path];
        }

        return [$dirname . $filename . '.html'];
    }
}
