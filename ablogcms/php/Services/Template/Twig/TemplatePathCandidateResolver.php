<?php

namespace Acms\Services\Template\Twig;

use Acms\Services\Template\Contracts\TemplatePathCandidateResolverInterface;

/**
 * Twig テンプレートの探索候補を返す。
 *
 * 拡張子が html・twig（html コンテキスト）の場合:
 *   {base}.twig → {base}.html.twig → {base}.html
 *   .twig 指定は .html と同等に扱う。
 *
 * それ以外の拡張子（例: .xml）の場合:
 *   {base}.{ext}.twig → {base}.{ext}
 *   汎用の {base}.twig は含めない。xml 等を html テンプレートとして
 *   誤って解決しないよう、元の拡張子に紐づく候補のみに絞る。
 *
 * 拡張子なしのパスは候補なし（補完責務は呼び出し側に置く）。
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

        if ($filename === '' || $extension === '') {
            return [];
        }

        $base = $dirname . $filename;

        // html・twig は「html コンテキスト」として同じ候補列を返す。
        // それ以外（xml 等）は元の拡張子を使った候補のみとし、{base}.twig は含めない。
        $isHtmlContext = $extension === 'html' || $extension === 'twig';

        if ($isHtmlContext) {
            $candidates = [
                $base . '.twig',
                $base . '.html.twig',
                $base . '.html',
            ];
        } else {
            $candidates = [
                $base . '.' . $extension . '.twig',
                $base . '.' . $extension,
            ];
        }

        return array_values(array_unique($candidates));
    }
}
