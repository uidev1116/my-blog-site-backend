<?php

namespace Acms\Services\StaticExport\Contracts;

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
     * @param string $url
     * @return string
     */
    protected function removePortFromUrl(string $url): string
    {
        // parse_url は相対パスでもエラーにはならない
        $parts = parse_url($url);

        // スキームもホストもない場合（相対URLや絶対パス）はそのまま返す
        if ($parts === false || (!isset($parts['host']) && !isset($parts['scheme']))) {
            return $url;
        }
        // port を削除
        unset($parts['port']);

        // 再構築
        $newUrl = '';
        if (isset($parts['scheme'])) {
            $newUrl .= $parts['scheme'] . '://';
        }
        if (isset($parts['user'])) {
            $newUrl .= $parts['user'];
            if (isset($parts['pass'])) {
                $newUrl .= ':' . $parts['pass'];
            }
            $newUrl .= '@';
        }
        if (isset($parts['host'])) {
            $newUrl .= $parts['host'];
        }
        if (isset($parts['path'])) {
            $newUrl .= $parts['path'];
        }
        if (isset($parts['query'])) {
            $newUrl .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $newUrl .= '#' . $parts['fragment'];
        }
        return $newUrl;
    }
}
