<?php

namespace Acms\Services\StaticExport\Compiler;

use Acms\Services\StaticExport\Contracts\Resolver;
use ACMS_RAM;
use Media;

class LinkResolver extends Resolver
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
    public function resolve($html, $document_root, $offset_dir, $domain, $blog_code)
    {
        $regex = $this->getRegex();
        $offset = 0;

        $blogPath = ACMS_RAM::blogDomain(BID) ?? '';
        if (DIR_OFFSET) {
            $blogPath .= '/' . DIR_OFFSET;
        }
        $blogPath = preg_quote($blogPath, '@');

        while (preg_match($regex, $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $match[0][1] + strlen($match[0][0]);
            for ($mpt = 1; $mpt <= 2; $mpt++) {
                if (!empty($match[$mpt][0])) {
                    break;
                }
            }
            $path = trim($match[$mpt][0], '\'"'); // @phpstan-ignore-line
            $path = $this->removePortFromUrl($path);
            $path = preg_replace('@(https?)?://' . $blogPath . '/?@', '/', $path);

            if (empty($path)) {
                continue;
            }
            if ('//' === substr($path, 0, 2)) {
                continue;
            }
            if (is_int(strpos($path, '://'))) {
                continue;
            }
            if ('#' === substr($path, 0, 1)) {
                continue;
            }
            if ('/' !== substr($path, 0, 1)) {
                continue;
            }
            if (defined('REWRITE_PATH_EXTENSION')) {
                $extensionRegex  = '/\.(?:acms|' . REWRITE_PATH_EXTENSION . ')/';
                if (preg_match($extensionRegex, $path)) {
                    continue; // ファイルリンクだった場合は書き換えない
                }
            }
            $mediaDownloadRegex = '/\/' . MEDIA_FILE_SEGMENT . '\/(\d+)\//';
            if (preg_match($mediaDownloadRegex, $path, $mediaMatchs)) {
                $mid = $mediaMatchs[1];
                $media = Media::getMedia($mid);
                if (isset($media['path']) && $media['path']) {
                    $path = '/' . MEDIA_STORAGE_DIR . $media['path'];
                }
            }
            $path = substr($path, 1);
            $path = '/' . $offset_dir . $path;
            $path = preg_replace('/page\/([\d]+)\/?/', 'page$1.html', $path);

            $html = substr_replace($html, '"' . $path . '"', $match[$mpt][1], strlen($match[$mpt][0])); // @phpstan-ignore-line
            $offset -= (strlen($match[$mpt][0]) - strlen($path)); // @phpstan-ignore-line
        }
        return $html;
    }

    /**
     * @return string
     */
    protected function getRegex()
    {
        $regex  = '@' .
            // a要素のhref属性
            '<\s*a(?:"[^"]*"|\'[^\']*\'|[^\'">])*href\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // form要素のaction属性
            '<\s*form(?:"[^"]*"|\'[^\']*\'|[^\'">])*action\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>' .
            '@';
        return $regex;
    }
}
