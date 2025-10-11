<?php

declare(strict_types=1);

namespace Acms\Services\Template\Twig;

use Acms\Services\Facades\Vite;

class ViteAsset
{
    /**
     * twigテンプレートから「vite」関数で呼び出し
     *
     * @param string|string[] $entrypoints
     * @param array $options
     * @return string
     */
    public function viteFunction($entrypoints, array $options = []): string
    {
        $html = Vite::generateHtml($entrypoints, $options);
        return $html;
    }

    /**
     * twigテンプレートから「viteReactRefresh」関数で呼び出し
     *
     * @return string
     */
    public function viteReactRefreshFunction(): string
    {
        $html = Vite::generateReactRefreshHtml();
        return $html;
    }
}
