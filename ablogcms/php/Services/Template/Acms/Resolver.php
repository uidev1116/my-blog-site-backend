<?php

namespace Acms\Services\Template\Acms;

use ACMS_RAM;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;

class Resolver
{
    /**
     * テンプレートのパスを解決して変換
     *
     * @param string $txt
     * @param string $theme
     * @param string $tplPath
     * @param int $bid
     * @return string
     */
    public function rewritePaths(string $txt, string $theme, string $tplPath, int $bid): string
    {
        if (!defined('RESOLVE_PATH') || !RESOLVE_PATH) {
            return $txt;
        }
        $txt = $this->resolveFilePath($txt, $theme, $tplPath); //ファイルパスの解決
        $txt = $this->resolveSrcSetAttribute($txt, $theme, $tplPath); // srcset属性のパス解決
        $txt = $this->resolveLinkAttribute($txt, $bid); // リンク属性のパス解決

        return $txt;
    }

    /**
     * 指定されたパスをテーマ、テンプレートパスを考慮したパスを変換
     *
     * @param string $path
     * @param string $theme
     * @param string $tplPath
     * @return ?string
     */
    public function resolvePath(string $path, string $theme, string $tplPath): ?string
    {
        if (is_int(strpos($path, '://'))) {
            return ''; // 何らかのスキーマ http:// 等から始まるパスは書き換えない
        }
        // ブレース「{}（変数）」が含まれる場合は，それ以降をサフィックスとして保存しておく（マッチングに邪魔）
        // 最後の書き換え時に $path の後ろに戻す
        $suffix = '';
        if (preg_match('@\{[^}]*\}@', $path, $_match, PREG_OFFSET_CAPTURE)) {
            $suffix = substr($path, $_match[0][1]);
            $path = substr($path, 0, $_match[0][1]);
        }
        if (!str_replace('/', '', $path)) {
            return ''; // 「/」を削除すると何も残らなければ ただのルートパス指定とみなしパスは書き換えない
        }
        if ('/' == substr($path, 0, 1)) {
            // ルートから始まっていたら素直な探索を試みる
            $path = substr($path, 1); // 先頭のスラッシュを除去
            $cleanedPath = explode('?', $path)[0]; // クエリを除去
            if (LocalStorage::isReadable(DOCUMENT_ROOT . $cleanedPath)) {
                return ''; // ドキュメントルートからのパスで存在すればパスは書き換えない
            }
            if (LocalStorage::isReadable(SCRIPT_DIR . $cleanedPath)) {
                // スクリプトディレクトリからのパスで存在すれば書き換えない
                return '/' . cacheBusting(DIR_OFFSET . $path . $suffix, SCRIPT_DIR . $cleanedPath);
            }
        } else {
            // 相対パスの場合
            if ('./' === substr($path, 0, 2)) {
                $path = substr($path, 2); // 「./」で始まる場合は「./」を除去
            }
            if ('/' !== $tplPath) {
                $relativePath  = preg_replace('@[^/]+$@', $path, $tplPath) ?? $tplPath; // 指定されたテンプレートからの相対パス
                $cleanedPath = explode('?', $relativePath)[0]; // クエリを除去
                if (LocalStorage::isReadable(DOCUMENT_ROOT . $cleanedPath)) {
                     // ドキュメントルートからのパスを返却
                    return '/' . cacheBusting($relativePath . $suffix, DOCUMENT_ROOT . $cleanedPath);
                }
            }
            $pos = 0;
            if (strlen($tplPath) > strlen(DIR_OFFSET . THEMES_DIR)) {
                $pos = intval(strpos($tplPath, '/', strlen(DIR_OFFSET . THEMES_DIR)));
            }
            $aryDir = preg_split('@/@', preg_replace('@[^/]+$@', '', substr($tplPath, $pos)), -1, PREG_SPLIT_NO_EMPTY);
            if ($aryDir === false) {
                $clv = 0;
            } else {
                $clv = count($aryDir);
            }

            while ('../' === substr($path, 0, 3)) {
                $clv--;
                $path = substr($path, 3);
            }
            for (; $clv > 0; $clv--) {
                $path = $aryDir[$clv - 1] . '/' . $path;
            }
        }
        // どれにも当たらなければ、継承テーマの探索を始める
        $themes = Common::getInheritedThemes($theme);
        foreach ($themes as $inheritedTheme) {
            $realPath = THEMES_DIR . $inheritedTheme . '/' . $path;
            $cleanedPath = explode('?', $realPath)[0];
            if (LocalStorage::isReadable($cleanedPath)) {
                return '/' . cacheBusting(DIR_OFFSET . $realPath . $suffix, $cleanedPath);
            }
        }
        return null;
    }

    /**
     * ブログを考慮したリンクに変換
     *
     * @param string $link
     * @param int $bid
     * @return string|null
     */
    public function resolveLink(string $link, int $bid = 0): ?string
    {
        if (
            empty($link) ||
            '//' === substr($link, 0, 2) || // 「//」から始まるパスは書き換えない
            '#' === substr($link, 0, 1) || // 「#」から始まるパスは書き換えない
            '/' !== substr($link, 0, 1) || // 「/」から始まらないパスは書き換えない（相対パスは書き換えない）
            is_int(strpos($link, '://')) // // 「://」から始まるパスは書き換えない
        ) {
            return null;
        }

        $root = '/' . DIR_OFFSET;
        if (!REWRITE_ENABLE) {
            $root .= SCRIPT_FILENAME . '/'; // 使用していない？
        }
        $bid = $bid ?: BID;
        if ($bcd = ACMS_RAM::blogCode($bid)) {
            $root .= ($bcd . '/'); // 指定されたブログのルートパス
        }

        if (!!DIR_OFFSET && strpos($link, $root) === 0) {
            return null; // [CMS-1060] DIR_OFFSETが存在し、このパスがすでにDIR_OFFSETから始まっていれば編集しない
        }
        if (defined('REWRITE_PATH_EXTENSION')) {
            $extensionRegex  = '/\.(?:acms|' . REWRITE_PATH_EXTENSION . ')/';
            if (preg_match($extensionRegex, $link)) {
                return null; // ファイルリンクだった場合は書き換えない
            }
        }
        if (!(empty($bcd) && '/' === $link)) {
            $link = $root . ltrim($link, '/');
        }
        return $link;
    }

    /**
     * ファイルパスの解決
     *
     * @param string $txt
     * @param string $theme
     * @param string $tplPath
     * @return string
     */
    protected function resolveFilePath(string $txt, string $theme, string $tplPath): string
    {
        // パス類を検出するための正規表現
        $extension  = '(?:acms)';
        if (defined('REWRITE_PATH_EXTENSION')) {
            $extension  = '(?:acms|' . REWRITE_PATH_EXTENSION . ')';
        }
        $regex = '@' .
            // include表記
            '<!--#include file=("[^"]+") vars=".*?"-->|' .
            // src属性をもつHTML要素
            '<\s*(?:img|input|script|frame|iframe)(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+src\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // link要素（href属性）
            '<\s*link(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+href\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // object, applet要素（arcvhie属性）
            '<\s*(?:object|applet)(?:"[^"]*"|\'[^\']*\'|[^\'">])*archives\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // background属性
            '<\s*\w+(?:"[^"]*"|\'[^\']*\'|[^\'">])*background\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // a要素
            '<\s*a(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+href\s*=\s*("[^"]+\.' . $extension . '"|\'[^\']+\.' . $extension . '\'|[^\'"\s>]\.' . $extension . '+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>' .
            '@i';

        // 正規表現マッチと、マッチしたパス文字列の解決
        // 毎回同じマッチングをしながら，マッチポイントを読み進めている
        $offset = 0;
        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            // 置き換え対象文字列の$str全体からみたときのオフセット文字数を取得
            $offset = $match[0][1] + strlen($match[0][0]);

            // マッチ箇所を1文字列チャンクあたり，6回まで検出する
            // マッチポイントが検出されたらbreakして，$mptはつぎのwhileループに持ち越す
            $found = 0;
            for ($mpt = 1; $mpt <= 6; $mpt++) {
                if (!empty($match[$mpt][0])) {
                    $found = $mpt;
                    break;
                }
            }
            $path = trim($match[$found][0], '\'"');
            if ($newPath = $this->resolvePath($path, $theme, $tplPath)) {
                $txt = substr_replace($txt, '"' . $newPath . '"', $match[$found][1], strlen($match[$found][0]));
            }
        }
        return $txt;
    }

    /**
     * srcset属性のパス解決
     *
     * @param string $txt
     * @param string $theme
     * @param string $tplPath
     * @return string
     */
    protected function resolveSrcSetAttribute(string $txt, string $theme, string $tplPath): string
    {
        $regex = '/<\s*(img|source)[^\>]*[^\>\S]+srcset\s*=\s*[\'"]([^"\']+?)["\']/u';
        $offset = 0;

        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $match[0][1] + strlen($match[0][0]); // 次の検索位置を設定
            $srcset = $match[2][0]; // srcset 属性の値を取得
            $srcsetAry = explode(',', $srcset); // カンマ区切りで分割
            $successAry = [];

            foreach ($srcsetAry as $srcsetPathSource) {
                // 画像パス部分を抽出（解像度やサイズ指定がなくても対応）
                if (preg_match('/([^\s,]+)(?:\s+\d+[wx])?/u', $srcsetPathSource, $srcsetPathMatch, PREG_OFFSET_CAPTURE)) {
                    if ($newPath = $this->resolvePath($srcsetPathMatch[1][0], $theme, $tplPath)) {
                        // パスを書き換え
                        $srcsetPathSource = substr_replace(
                            $srcsetPathSource,
                            '"' . $newPath . '"',
                            $srcsetPathMatch[1][1],
                            strlen($srcsetPathMatch[1][0])
                        );
                        // 成功した書き換えを収集
                        $successAry[] = str_replace(['\'', '"'], '', $srcsetPathSource);
                    }
                }
            }

            if (!empty($successAry)) {
                // srcset 属性の内容を書き換え
                $txt = substr_replace($txt, implode(',', $successAry), $match[2][1], strlen($match[2][0]));
            }
        }

        return $txt;
    }

    /**
     * リンク属性のパス解決
     *
     * @param string $txt
     * @param int $bid
     * @return string
     */
    protected function resolveLinkAttribute(string $txt, int $bid): string
    {
        $regex  = '@' .
            // a要素のhref属性
            '<\s*a(?:"[^"]*"|\'[^\']*\'|[^\'">])*href\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // form要素のaction属性
            '<\s*form(?:"[^"]*"|\'[^\']*\'|[^\'">])*action\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>' .
            '@';
        $offset = 0;
        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $elm = $match[0][0];
            for ($mpt = 1; $mpt <= 2; $mpt++) {
                if (!empty($match[$mpt][0])) {
                    break;
                }
            }
            if (strpos($elm, ACMS_NO_REWRITE) !== false) {
                continue;
            }
            $path = trim($match[$mpt][0], '\'"'); // @phpstan-ignore-line
            if ($path = $this->resolveLink($path, $bid)) {
                $txt = substr_replace($txt, '"' . $path . '"', $match[$mpt][1], strlen($match[$mpt][0])); // @phpstan-ignore-line
            }
        }
        return $txt;
    }
}
