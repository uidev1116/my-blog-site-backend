<?php

namespace Acms\Services\StaticExport\Compiler;

use Acms\Services\StaticExport\Contracts\Resolver;
use ACMS_RAM;

class SourceResolver extends Resolver
{
    /**
     * @var string
     */
    protected $destinationOffsetDir;

    /**
     * @var string
     */
    protected $destinationPath;

    /**
     * @var string
     */
    protected $destinationDomain;

    /**
     * @var string
     */
    protected $destinationBlogCode;

    /**
     * @var int
     */
    protected $offset;

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
        $this->destinationOffsetDir = $offset_dir;
        $this->destinationPath = $document_root . $offset_dir;
        $this->destinationDomain = $domain;
        $this->destinationBlogCode = $blog_code;

        $blogPath = ACMS_RAM::blogDomain(BID) ?? '';
        if (DIR_OFFSET) {
            $blogPath .= '/' . DIR_OFFSET;
        }
        $blogPath = preg_quote($blogPath, '@');

        $regex = $this->getRegex();
        $this->offset = 0;
        while (preg_match($regex, $html, $match, PREG_OFFSET_CAPTURE, $this->offset)) {
            // 置き換え対象文字列の$html全体からみたときのオフセット文字数を取得
            $this->offset = $match[0][1] + strlen($match[0][0]);

            // マッチ箇所を1文字列チャンクあたり，5回まで検出する
            // マッチポイントが検出されたらbreakして，$mptはつぎのwhileループに持ち越す
            for ($mpt = 1; $mpt <= 6; $mpt++) {
                if (!empty($match[$mpt][0])) {
                    break;
                }
            }
            $path = trim($match[$mpt][0], '\'"'); // @phpstan-ignore-line
            $path = preg_replace('@(https?)?://' . $blogPath . '(?::\d+)?/?@', '/', $path);
            $this->replacer($path, $html, $match, $mpt); // @phpstan-ignore-line
        }
        $regex = '@<\s*(?:img|input|script|frame|iframe)(?:"[^"]*"|\'[^\']*\'|[^\'">])*data-src\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>@';
        $this->offset = 0;
        while (preg_match($regex, $html, $match, PREG_OFFSET_CAPTURE, $this->offset)) {
            $this->offset = $match[0][1] + strlen($match[0][0]);
            $path = trim($match[1][0], '\'"');
            $path = preg_replace('@(https?)?://' . $blogPath . '(?::\d+)?/?@', '/', $path);
            $this->replacer($path, $html, $match, 1);
        }
        return $html;
    }

    /**
     * @return string
     */
    protected function getRegex()
    {
        $extension = '(?:acms)';
        if (defined('REWRITE_PATH_EXTENSION')) {
            $extension = '(?:acms|' . REWRITE_PATH_EXTENSION . ')';
        }
        $regex = '@' .
            // src属性をもつHTML要素
            '<\s*(?:img|input|script|frame|iframe)(?:"[^"]*"|\'[^\']*\'|[^\'">])*[\s]+src\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // link要素（href属性）
            '<\s*link(?:"[^"]*"|\'[^\']*\'|[^\'">])*href\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // object, applet要素（arcvhie属性）
            '<\s*(?:object|applet)(?:"[^"]*"|\'[^\']*\'|[^\'">])*archives\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // background属性
            '<\s*\w+(?:"[^"]*"|\'[^\']*\'|[^\'">])*background\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // css url属性
            '\s*url\s*\(("[^"]+"|\'[^\']+\'|[^\'"\)]+)\)|' .
            // a要素
            '<\s*a(?:"[^"]*"|\'[^\']*\'|[^\'">])*href\s*=\s*("[^"]+\.' . $extension . '"|\'[^\']+\.' . $extension . '\'|[^\'"\s>]\.' . $extension . '+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>' .
            '@i';

        return $regex;
    }

    /**
     * パスの書き換え
     *
     * @param string $path 置き換え対象パス
     * @param string $html 全体html
     * @param array $match
     * @param int $mpt
     */
    protected function replacer($path, &$html, $match, $mpt)
    {
        $path = $this->removePortFromUrl($path);
        $path = trim($path);
        $_path = explode('?', $path);
        $_path = $_path[0];

        // 何らかのスキーマ http:// 等から始まっていたら次のマッチポイントへ
        if (is_int(strpos($path, '://')) || substr($path, 0, 2) === '//') {
            return;
        }

        // / をなくしたときに，何も残らなければ ただのルートパス指定とみなして次のマッチポイントへ
        if (!str_replace('/', '', $_path)) {
            return;
        }

        if ('/' == substr($path, 0, 1)) {
            $path = substr($path, 1);
            $_path = explode('?', $path);
            $_path = $_path[0];

            if ($this->acmsJsReplace($path, $_path, $html, $match, $mpt)) {
                return;
            }
            if ($this->matchingPath($path, $_path, $html, $match, $mpt)) {
                return;
            }
        }
    }

    /**
     * acms.jsのクエリのドメイン書き換え処理
     *
     * @param string $path
     * @param string $format_path
     * @param string $html
     * @param array $match
     * @param int $mpt
     * @return boolean
     */
    protected function acmsJsReplace($path, $format_path, &$html, $match, $mpt)
    {
        if ($format_path === 'acms.js') {
            $offset = strlen($match[$mpt][0]);
            $path = preg_replace('/domains=([^&]+)/', 'domains=' . $this->destinationDomain, $path);
            if ($this->destinationOffsetDir) {
                $path = preg_replace('/scriptRoot=([^&]+)/', 'scriptRoot=/' . $this->destinationOffsetDir, $path);
                if (strpos($path, 'offset=') === false) {
                    $path .= '&offset=' . $this->destinationOffsetDir;
                } else {
                    $path = preg_replace('/offset=([^&]+)/', 'offset=' . $this->destinationOffsetDir, $path);
                }
            }
            $html = substr_replace($html, '"/' . $this->destinationOffsetDir . $path . '"', $match[$mpt][1], $offset);
            $this->offset -= ($offset - strlen($path));
            return true;
        }
        return false;
    }

    /**
     * パスの書き換え
     *
     * @param string $path
     * @param string $format_path
     * @param string $html
     * @param array $match
     * @param int $mpt
     * @return boolean
     */
    protected function matchingPath($path, $format_path, &$html, $match, $mpt)
    {
        $_path = explode('?', $path);
        $query = isset($_path[1]) ? '?' . $_path[1] : '';
        $split_path = preg_split('@/@', $format_path, -1, PREG_SPLIT_NO_EMPTY);

        $rewrite = false;
        while (true) {
            $tmp = join('/', $split_path);

            // 書き出し先にあったら
            foreach (['',  $this->destinationBlogCode] as $bcd) {
                if (is_readable($this->destinationPath . $bcd . urldecode($tmp))) {
                    $offset = strlen($match[$mpt][0]);
                    if ($mpt === 5) {
                        $html = substr_replace($html, '/' . $this->destinationOffsetDir . $bcd . $tmp . $query . '', $match[$mpt][1], $offset);
                    } else {
                        $html = substr_replace($html, '"/' . $this->destinationOffsetDir . $bcd . $tmp . $query . '"', $match[$mpt][1], $offset);
                    }
                    $this->offset -= ($offset - strlen($tmp . $query));
                    $rewrite = true;
                    break 2;
                }
            }
            if (!(array_shift($split_path))) {
                break;
            }
        }
        if (!$rewrite) {
            // ToDo: 書き換え失敗のエラーハンドリングをする
        }

        return true;
    }
}
