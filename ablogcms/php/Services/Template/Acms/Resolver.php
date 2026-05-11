<?php

namespace Acms\Services\Template\Acms;

use ACMS_RAM;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;

/**
 * テンプレート内のHTML要素のパス解決を行うクラス
 * a-blog cms 公式ドキュメントに記載の通り、テンプレート内のパスを解決する。
 * @see https://developer.a-blogcms.jp/document/template/entry-1443.html
 *
 * Viteを参考に、以下の要素・属性の相対パスをテーマディレクトリ基準で解決する。
 * @see https://ja.vite.dev/guide/features#html
 *
 * 対応要素・属性:
 * - img: src, srcset
 * - input, script, frame, iframe: src
 * - audio, video, embed, track: src
 * - video: poster
 * - source: src, srcset
 * - link: href, imagesrcset
 * - object: data, archives
 * - applet: archives
 * - 汎用: background
 * - styleタグ・インラインstyle: url()（background, background-image等のCSSプロパティ内）
 * - a: href（ブログコード付きURLに変換）
 * - form: action（ブログコード付きURLに変換）
 * - meta: content（og:image, og:audio, og:video, twitter:image, msapplication-* 等）
 * - SVG image, use: href, xlink:href（#で始まるシンボル参照は除く）
 */
class Resolver
{
    /**
     * テンプレートのパスを解決して変換
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @param int $bid ブログID
     * @return string
     */
    public function rewritePaths(string $txt, string $theme, string $tplPath, int $bid): string
    {
        if (!defined('RESOLVE_PATH') || !RESOLVE_PATH) {
            return $txt;
        }
        $txt = $this->resolveFilePath($txt, $theme, $tplPath); //ファイルパスの解決
        $txt = $this->resolveObjectArchives($txt, $theme, $tplPath); // object/applet要素のarchives属性
        $txt = $this->resolveVideoPoster($txt, $theme, $tplPath); // video要素のposter属性（srcと同一タグの場合）
        $txt = $this->resolveCssUrl($txt, $theme, $tplPath); // styleタグ・インラインstyle内のurl()
        $txt = $this->resolveMetaAttribute($txt, $theme, $tplPath); // meta要素のcontent属性（OGP/Twitter Card等）
        $txt = $this->resolveSvgAttribute($txt, $theme, $tplPath); // SVG要素（image, use）のhref/xlink:href
        $txt = $this->resolveSrcSetAttribute($txt, $theme, $tplPath); // srcset属性のパス解決
        $txt = $this->resolveLinkAttribute($txt, $bid); // リンク属性のパス解決

        return $txt;
    }

    /**
     * 指定されたパスをテーマ、テンプレートパスを考慮したパスを変換
     *
     * @param string $path 変換対象のパス
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス・../ 解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    public function resolvePath(string $path, string $theme, string $tplPath): string
    {
        $originalPath = $path;
        if (is_int(strpos($path, '://'))) {
            return $originalPath; // 何らかのスキーマ http:// 等から始まるパスは書き換えない
        }
        // ブレース「{}（変数）」が含まれる場合は，それ以降をサフィックスとして保存しておく（マッチングに邪魔）
        // 最後の書き換え時に $path の後ろに戻す
        $suffix = '';
        if (preg_match('@\{[^}]*\}@', $path, $_match, PREG_OFFSET_CAPTURE)) {
            $suffix = substr($path, $_match[0][1]);
            $path = substr($path, 0, $_match[0][1]);
        }
        if (!str_replace('/', '', $path)) {
            return $originalPath; // 「/」を削除すると何も残らなければ ただのルートパス指定とみなしパスは書き換えない
        }
        if ('/' == substr($path, 0, 1)) {
            // ルートから始まっていたら素直な探索を試みる
            $path = substr($path, 1); // 先頭のスラッシュを除去
            $cleanedPath = explode('?', $path)[0]; // クエリを除去
            if (LocalStorage::isReadable(DOCUMENT_ROOT . $cleanedPath)) {
                return $originalPath; // ドキュメントルートからのパスで存在すればパスは書き換えない
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
        return $originalPath; // ファイルが見つからない場合は元のパスを返す
    }

    /**
     * substr_replace後の次の検索開始オフセットを計算する
     *
     * 正規表現マッチのフルマッチ末尾を基準に、置換による文字列長の変化を加味して次の検索開始位置を返す。
     *
     * @param array<int, array{string, int}> $match PREG_OFFSET_CAPTURE のマッチ結果
     * @param int $lengthDelta 置換による文字列長の変化量（strlen(replacement) - strlen(original)）。置換なしの場合は0。
     * @return int
     */
    private function nextSearchOffset(array $match, int $lengthDelta = 0): int
    {
        return $match[0][1] + strlen($match[0][0]) + $lengthDelta;
    }

    /**
     * ブログを考慮したリンクに変換
     *
     * @param string $link 変換対象のリンクURL
     * @param int $bid ブログID
     * @return string|null
     */
    private function resolveLink(string $link, int $bid = 0): ?string
    {
        if (
            empty($link) ||
            '//' === substr($link, 0, 2) || // 「//」から始まるパスは書き換えない
            '#' === substr($link, 0, 1) || // 「#」から始まるパスは書き換えない
            '/' !== substr($link, 0, 1) || // 「/」から始まらないパスは書き換えない（相対パスは書き換えない）
            str_contains($link, '://') // 「://」を含むパスは書き換えない
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

        if (!!DIR_OFFSET && str_starts_with($link, $root)) {
            return null; // [CMS-1060] DIR_OFFSETが存在し、このパスがすでにDIR_OFFSET+ブログコードから始まっていれば編集しない
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
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    private function resolveFilePath(string $txt, string $theme, string $tplPath): string
    {
        // パス類を検出するための正規表現
        $extension  = '(?:acms)';
        if (defined('REWRITE_PATH_EXTENSION')) {
            $extension  = '(?:acms|' . REWRITE_PATH_EXTENSION . ')';
        }
        $regex = '@' .
            // include表記
            '<!--#include file=("[^"]+") vars=".*?"-->|' .
            // src属性をもつHTML要素（img, input, script, frame, iframe, audio, video, embed, track）
            '<\s*(?:img|input|script|frame|iframe|audio|video|embed|track)(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+src\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // link要素（href属性）
            '<\s*link(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+href\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // object要素（data属性）
            '<\s*object(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+data\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // source要素（src属性）
            '<\s*source(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+src\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // video要素（poster属性）
            '<\s*video(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+poster\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // background属性
            '<\s*\w+(?:"[^"]*"|\'[^\']*\'|[^\'">])*background\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // a要素
            '<\s*a(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+href\s*=\s*("[^"]+\.' . $extension . '"|\'[^\']+\.' . $extension . '\'|[^\'"\s>]+\.' . $extension . '+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>' .
            '@i';

        // 正規表現マッチと、マッチしたパス文字列の解決
        // 毎回同じマッチングをしながら，マッチポイントを読み進めている
        $offset = 0;
        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            // マッチ箇所を1文字列チャンクあたり，8回まで検出する
            // マッチポイントが検出されたらbreakして，$mptはつぎのwhileループに持ち越す
            $found = 0;
            for ($mpt = 1; $mpt <= 8; $mpt++) {
                if (!empty($match[$mpt][0])) {
                    $found = $mpt;
                    break;
                }
            }
            $path = trim($match[$found][0], '\'"');
            $newPath = $this->resolvePath($path, $theme, $tplPath);
            if ($newPath !== $path) {
                $replacement = '"' . $newPath . '"';
                $txt = substr_replace($txt, $replacement, $match[$found][1], strlen($match[$found][0]));
                $offset = $this->nextSearchOffset($match, strlen($replacement) - strlen($match[$found][0]));
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }
        return $txt;
    }

    /**
     * object/applet要素のarchives属性のパス解決
     *
     * resolveFilePathの正規表現は1マッチで1属性しか処理できないため、
     * data属性と同一タグに存在するarchives属性は別パスで解決する。
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    private function resolveObjectArchives(string $txt, string $theme, string $tplPath): string
    {
        $regex = '@<\s*(?:object|applet)(?:"[^"]*"|\'[^\']*\'|[^\'">])*archives\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>@i';
        $offset = 0;

        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $path = trim($match[1][0], '\'"');
            $newPath = $this->resolvePath($path, $theme, $tplPath);
            if ($newPath !== $path) {
                $replacement = '"' . $newPath . '"';
                $txt = substr_replace($txt, $replacement, $match[1][1], strlen($match[1][0]));
                $offset = $this->nextSearchOffset($match, strlen($replacement) - strlen($match[1][0]));
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        return $txt;
    }

    /**
     * video要素のposter属性のパス解決
     *
     * resolveFilePathの正規表現は1マッチで1属性しか処理できないため、
     * src属性と同一タグに存在するposter属性は別パスで解決する。
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    private function resolveVideoPoster(string $txt, string $theme, string $tplPath): string
    {
        $regex = '@<\s*video(?:"[^"]*"|\'[^\']*\'|[^\'">])*poster\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>@i';
        $offset = 0;

        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $path = trim($match[1][0], '\'"');
            $newPath = $this->resolvePath($path, $theme, $tplPath);
            if ($newPath !== $path) {
                $replacement = '"' . $newPath . '"';
                $txt = substr_replace($txt, $replacement, $match[1][1], strlen($match[1][0]));
                $offset = $this->nextSearchOffset($match, strlen($replacement) - strlen($match[1][0]));
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        return $txt;
    }

    /**
     * styleタグ・インラインstyle属性内のCSS url()のパス解決
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    private function resolveCssUrl(string $txt, string $theme, string $tplPath): string
    {
        // 1. <style>タグ内のCSS url()を処理
        $offset = 0;
        while (preg_match('/<style[^>]*>(.*?)<\/style>/is', $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $content = $match[1][0];
            $newContent = $this->resolveCssUrlInText($content, $theme, $tplPath);
            if ($newContent !== $content) {
                $lengthDelta = strlen($newContent) - strlen($content);
                $txt = substr_replace($txt, $newContent, $match[1][1], strlen($content));
                $offset = $this->nextSearchOffset($match, $lengthDelta);
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        // 2. style属性内のCSS url()を処理
        $offset = 0;
        while (preg_match('/\bstyle\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            if ($match[2][1] !== -1) {
                $contentOffset = $match[2][1];
                $content = $match[2][0];
            } else {
                $contentOffset = $match[3][1];
                $content = $match[3][0];
            }
            $newContent = $this->resolveCssUrlInText($content, $theme, $tplPath);
            if ($newContent !== $content) {
                $lengthDelta = strlen($newContent) - strlen($content);
                $txt = substr_replace($txt, $newContent, $contentOffset, strlen($content));
                $offset = $this->nextSearchOffset($match, $lengthDelta);
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        return $txt;
    }

    /**
     * テキスト内のCSS url()のパス解決（style タグ・style 属性の中身専用）
     *
     * @param string $txt CSSテキスト
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス
     * @return string
     */
    private function resolveCssUrlInText(string $txt, string $theme, string $tplPath): string
    {
        // url("path"), url('path'), url(path) を検出（data:, #, http等はresolvePathで処理）
        $regex = '/url\s*\(\s*(["\']?)([^"\'()]+)\1\s*\)/';
        $offset = 0;

        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $path = trim($match[2][0]);
            $newPath = $this->resolvePath($path, $theme, $tplPath);
            if ($newPath !== $path) {
                $quote = $match[1][0]; // 元のクォートをそのまま使う（クォートなしなら空文字）
                $replacement = 'url(' . $quote . $newPath . $quote . ')';
                $originalLen = strlen($match[0][0]);
                $txt = substr_replace($txt, $replacement, $match[0][1], $originalLen);
                $offset = $this->nextSearchOffset($match, strlen($replacement) - $originalLen);
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        return $txt;
    }

    /**
     * meta要素のcontent属性のパス解決（OGP/Twitter Card/Windows Tile画像等）
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    private function resolveMetaAttribute(string $txt, string $theme, string $tplPath): string
    {
        $metaNames = 'og:image|og:image:secure_url|og:audio|og:audio:secure_url|og:video|og:video:secure_url|' .
            'twitter:image|msapplication-tileimage|msapplication-square\d+x\d+logo|' .
            'msapplication-wide\d+x\d+logo|msapplication-config';
        $nameAttr = '(?:property|name)\s*=\s*["\']?(?:' . $metaNames . ')["\']?';
        $contentValue = '("[^"]+"|\'[^\']+\'|[^\'"\s>]+)';
        // property/name → content の順序と content → property/name の順序の両方に対応
        $regex = '@<\s*meta\s+(?:' .
            $nameAttr . '\s+content\s*=\s*' . $contentValue . '|' .
            'content\s*=\s*' . $contentValue . '\s+' . $nameAttr .
            ')@i';
        $offset = 0;

        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $contentGroup = ($match[1][1] !== -1) ? 1 : 2;
            $path = trim($match[$contentGroup][0], '\'"');
            $newPath = $this->resolvePath($path, $theme, $tplPath);
            if ($newPath !== $path) {
                $replacement = '"' . $newPath . '"';
                $txt = substr_replace($txt, $replacement, $match[$contentGroup][1], strlen($match[$contentGroup][0]));
                $offset = $this->nextSearchOffset($match, strlen($replacement) - strlen($match[$contentGroup][0]));
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        return $txt;
    }

    /**
     * SVG要素（image, use）のhref/xlink:href属性のパス解決
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    private function resolveSvgAttribute(string $txt, string $theme, string $tplPath): string
    {
        $pathAttr = '(?:href|xlink\s*:\s*href)';
        $pathValue = '("[^"]+"|\'[^\']+\'|[^\'"\s>]+)';
        $regex = '@<\s*(?:image|use)(?:"[^"]*"|\'[^\']*\'|[^\'">])*\s+' . $pathAttr . '\s*=\s*' . $pathValue . '(?:"[^"]*"|\'[^\']*\'|[^\'">])*>@i';
        $offset = 0;

        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $path = trim($match[1][0], '\'"');
            // #で始まる場合はSVG内のシンボル参照なので書き換えない
            if (str_starts_with($path, '#')) {
                $offset = $this->nextSearchOffset($match);
                continue;
            }
            $newPath = $this->resolvePath($path, $theme, $tplPath);
            if ($newPath !== $path) {
                $replacement = '"' . $newPath . '"';
                $txt = substr_replace($txt, $replacement, $match[1][1], strlen($match[1][0]));
                $offset = $this->nextSearchOffset($match, strlen($replacement) - strlen($match[1][0]));
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        return $txt;
    }

    /**
     * srcset属性のパス解決
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param string $theme テーマ名
     * @param string $tplPath パスが記述されているテンプレートファイルのパス。相対パス解決の基準となる。ルート等の場合は '/'
     * @return string
     */
    private function resolveSrcSetAttribute(string $txt, string $theme, string $tplPath): string
    {
        // img, source要素のsrcset属性、link要素のimagesrcset属性
        $regex = '/<\s*(img|source|link)[^\>]*[^\>\S]+(?:srcset|imagesrcset)\s*=\s*[\'"]([^"\']+?)["\']/u';
        $offset = 0;

        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
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
                $newSrcset = implode(',', $successAry);
                $originalLen = strlen($match[2][0]);
                $txt = substr_replace($txt, $newSrcset, $match[2][1], $originalLen);
                $offset = $this->nextSearchOffset($match, strlen($newSrcset) - $originalLen);
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }

        return $txt;
    }

    /**
     * リンク属性のパス解決
     *
     * @param string $txt パスを含むテンプレート文字列
     * @param int $bid ブログID
     * @return string
     */
    private function resolveLinkAttribute(string $txt, int $bid): string
    {
        $regex  = '@' .
            // a要素のhref属性
            '<\s*a(?:"[^"]*"|\'[^\']*\'|[^\'">])*href\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            // form要素のaction属性
            '<\s*form(?:"[^"]*"|\'[^\']*\'|[^\'">])*action\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>' .
            '@';
        $offset = 0;
        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $elm = $match[0][0];
            for ($mpt = 1; $mpt <= 2; $mpt++) {
                if (!empty($match[$mpt][0])) {
                    break;
                }
            }
            if (strpos($elm, ACMS_NO_REWRITE) !== false) {
                $offset = $this->nextSearchOffset($match);
                continue;
            }
            $path = trim($match[$mpt][0], '\'"'); // @phpstan-ignore-line
            if ($newPath = $this->resolveLink($path, $bid)) {
                $replacement = '"' . $newPath . '"';
                $originalLen = strlen($match[$mpt][0]); // @phpstan-ignore-line
                $txt = substr_replace($txt, $replacement, $match[$mpt][1], $originalLen); // @phpstan-ignore-line
                $offset = $this->nextSearchOffset($match, strlen($replacement) - $originalLen);
            } else {
                $offset = $this->nextSearchOffset($match);
            }
        }
        return $txt;
    }
}
