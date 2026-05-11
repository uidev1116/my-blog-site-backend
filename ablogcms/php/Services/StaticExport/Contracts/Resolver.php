<?php

namespace Acms\Services\StaticExport\Contracts;

use Uri\WhatWg\Url as WhatWgUrl;

abstract class Resolver
{
    /**
     * @param string $html
     * @param string $document_root
     * @param string $offset_dir
     * @param string $domain
     * @param string $blog_code
     *
     * @return string
     */
    abstract public function resolve($html, $document_root, $offset_dir, $domain, $blog_code);

    /**
     * URLからポート番号を削除する
     *
     * WHATWG URL パーサーを使うため、日本語パスを含む URL も安全に処理できる。
     * 相対パス（/path など）は parse() が null を返すためそのまま返す。
     *
     * @param string $url
     * @return string
     */
    protected function removePortFromUrl(string $url): string
    {
        $parsed = WhatWgUrl::parse($url);
        if ($parsed === null) {
            return $url;
        }
        return $parsed->withPort(null)->toAsciiString();
    }
}
