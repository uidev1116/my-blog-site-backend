<?php

namespace Acms\Services\Template\Acms;

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Http;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Facades\Vite;
use Exception;
use Field;
use RuntimeException;

class Engine
{
    /**
     * @var Resolver
     */
    protected $resolver;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(Resolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * テンプレート内の外部ファイル参照（#include, @include, @extends）を解決する
     *
     * @param string $txt テンプレート文字列
     * @param string $theme テーマ名
     * @param int $bid ブログID
     * @param boolean $comment デバック用のコメントを出力するかどうか
     * @param boolean $tplCache テンプレートキャッシュを利用しているかどうか
     * @return string
     */
    public function spreadTemplate(string $txt, string $theme, int $bid, bool $comment = true, bool $tplCache = false): string
    {
        $txt = $this->protectVerbatim($txt);
        $res = '';
        $stack = [];
        $root = true;

        do {
            $tokens = preg_split('@(<!--#include file=")([^"]+)" vars="(.*?)("-->)@', $txt, -1, PREG_SPLIT_DELIM_CAPTURE);

            while (null !== ($token = array_pop($tokens))) {
                array_unshift($stack, $token);
            }
            while (null !== ($token = array_shift($stack))) {
                if ('<!--#include file="' == $token) {
                    $path = array_shift($stack);
                    $vars = array_shift($stack);
                    array_shift($stack);

                    if ('http://' === substr($path, 0, 7) || 'https://' === substr($path, 0, 7)) {
                        // HTTPリクエストによる外部コンテンツのインクルード
                        $txt = $this->getTemplateFromUrl($path);
                        if ($comment === true) {
                            $txt = $this->insertIncludeComment($path, $txt);
                        }
                        $token = $txt;
                    } elseif ('acms://' == substr($path, 0, 7)) {
                        // acmsパスによるインクルード
                        $txt = $this->getTemplateFromAcmsPath($path);
                        if ($comment === true && preg_match('/tpl/', $path)) {
                            $txt = $this->insertIncludeComment($path, $txt);
                        }
                        $token = $txt;
                    } else {
                        // ローカルファイルのインクルード
                        $token = '';
                        $txt = $this->getTemplateFromStorage($path, $theme, $bid, $vars, $tplCache);
                        if ($comment === true && !$root) {
                            $txt = $this->insertIncludeComment($path, $txt);
                        }
                        if ($txt) {
                            $root = false;
                            break;
                        }
                    }
                }
                $res .= $token;
            }
        } while (!empty($stack));
        $res = $this->extendTemplate($res, $theme, $bid, $tplCache);
        $res = $this->resolveVite($res, $theme, $bid);

        return $res;
    }

    /**
     * インクルード文を整形
     *
     * @param string $tpl
     * @param bool $withTwig
     * @return string
     */
    public function formatIncludeCode(string $tpl, bool $withTwig = false): string
    {
        $tpl = $this->protectVerbatim($tpl);
        $tpl = (string) preg_replace(['/\{\{[^\{\}]+default\s*\([\'"]([^\)]+)[\'"]\)\s*\}\}/'], ['$1'], $tpl);
        $tpl = (string) preg_replace_callback([
            '@<!--[\t 　]*#[\t 　]*[include]{6,8}[\t 　]*(?:file|virtual)[\t 　]*=[\t 　"\']*([^"\'\n]+)[\t 　"\']*-->@',
            '/@include[\t\s]*\([\t\s"\']*([^"\'\n]+)[\t\s"\']*[\t\s]*,?[\t\s]*(\{[^\)]+\})?[\t\s]*\)/u',
        ], function ($matches) {
            $var2 = '';
            if (isset($matches[2])) {
                $var2 = str_replace(["\r\n", "\r", "\n"], '', $matches[2]);
            }
            return '<!--#include file="' . $matches[1] . '" vars="' . $var2 . '"-->';
        }, $tpl);
        $tpl = (string) preg_replace(
            '/<!--[\s\t\n]*<\!--#include file="([^"\'\n]+)" vars="(\{[^\}]+\})?"-->[\s\t\n]*-->/u',
            '<!--include file="$1" vars="$2"-->',
            $tpl
        ); // コメント対応

        // 未解決の 「{{変数名}}」 を 「@begin_acms_vars@変数名@end_acms_vars@」 に一時的に変換（SetTemplate, SetRenderd で使用）
        if (!$withTwig) {
            $tpl = preg_replace_callback('/\{\{([^\{\}]*)\}\}/', function ($m) {
                return '@begin_acms_vars@' . $m[1] . '@end_acms_vars@';
            }, $tpl);
        }
        return $tpl ?? '';
    }

    /**
     * テンプレートをレンダリング
     *
     * @param string $string テンプレート文字列
     * @param \Field_Validation $post フォームデータ
     * @param bool $noBuildIF IFブロックを解決するかどうか
     * @return string
     */
    public function render(string $string, \Field_Validation $post, bool $noBuildIF = false): string
    {
        $output = build($string, $post, $noBuildIF);
        $output = $this->restoreVerbatim($output);
        return $output;
    }

    /**
     * include のデバッグコメント（start）を取得
     *
     * @param string $path
     * @return string
     */
    public function includeCommentBegin(string $path): string
    {
        $res = '';
        if (strpos($path, '/include/layout.html') === false) {
            $res = "<!-- Start of include : source=$path -->" . PHP_EOL;
        }
        return $res;
    }

    /**
     * include のデバッグコメント（end）を取得
     * @param string $path
     * @return string
     */
    public function includeCommentEnd(string $path): string
    {
        if (strpos($path, '/include/layout.html') === false) {
            return PHP_EOL . "<!-- End of include : source=$path -->" . PHP_EOL;
        }
        return '';
    }

    /**
     * 変数ブロックのコメントを設定
     *
     * @param string $txt
     * @param string $path
     * @return string
     */
    public function setTemplateComment($txt, $path)
    {
        return preg_replace('/<!-- BEGIN_Set(Template)(.*)-->/', "<!-- BEGIN_Set$1$2 source=\"$path\" -->", $txt);
    }

    /**
     * ローカルのファイルからテンプレートを取得
     *
     * @param string $path
     * @param string $theme
     * @param int $bid
     * @param string $jsonStr
     * @param boolean $tplCache テンプレートキャッシュを利用しているかどうか
     * @return string
     * @throws Exception
     */
    protected function getTemplateFromStorage(string $path, string $theme, int $bid, string $jsonStr, bool $tplCache = false): string
    {
        $path = ltrim($path, '/');
        $path = explode('?', $path)[0];
        $txt = '';

        try {
            /** @var string $txt */
            $txt = LocalStorage::get(DOCUMENT_ROOT . $path);
        } catch (Exception $e) {
        }

        if ($txt && !LocalStorage::isDirectory(DOCUMENT_ROOT . $path)) {
            $charset = mb_detect_encoding($txt, 'UTF-8, EUC-JP, SJIS-win, SJIS');
            if ($charset && 'UTF-8' != $charset) {
                $txt = mb_convert_encoding($txt, 'UTF-8', $charset);
            }
            if ($txt === false) {
                throw new RuntimeException('Failed to read template file: ' . $path);
            }
            $txt = $this->expandIncludeVars($txt, $jsonStr);
            $txt = $this->formatIncludeCode($txt);

            if ($tplCache) {
                $txt = $this->resolver->rewritePaths(setGlobalVarsForInclude($txt), $theme, $path, $bid);
            } else {
                $txt = $this->resolver->rewritePaths(setGlobalVars($txt), $theme, $path, $bid);
            }
            if (strpos($txt, '@extends') !== false) {
                $txt = $this->extendTemplate($txt, $theme, $bid, $tplCache);
            }
        }
        return $txt;
    }

    /**
     * インクルードの引数を変数に展開
     *
     * @param string $txt
     * @param string $jsonStr
     * @return string
     */
    protected function expandIncludeVars(string $txt, string $jsonStr): string
    {
        $vars = json_decode($jsonStr, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($vars)) {
            $arg_keys = ['dummy_abc123'];
            foreach ($vars as $key => $item) {
                $arg_keys[] = preg_quote($key);
            }
            $txt = (string) preg_replace_callback('/\{\{[^\{\}]*?(' . implode('|', $arg_keys) . ')[\s\|]*(default\s*\([\'"]([^\)]+)[\'"]\)\s*)?.*?\}\}/', function ($matches) use ($vars) {
                $argText = preg_replace('/[\s\{\}]/', '', $matches[0]);
                if ($argText === null) {
                    // preg_replace に失敗した場合は、そのまま返す
                    return $matches[0];
                }
                $matchKey = explode('|', $argText);
                foreach ($matchKey as $key) {
                    if (isset($vars[$key]) && $vars[$key] !== '') {
                        return $vars[$key];
                    }
                }
                return $matches[0];
            }, $txt);
        }
        return $txt;
    }

    /**
     * acmsパスからテンプレートを取得
     *
     * @param string $path
     * @return string
     */
    protected function getTemplateFromAcmsPath(string $path): string
    {
        $trimmedPath = substr($path, 7);
        $Q = Field::singleton('query');

        $redirectRoot = rtrim(DIR_OFFSET, '/') . '/';
        if (!REWRITE_ENABLE) {
            $redirectRoot .= SCRIPT_FILENAME . '?/';
        }
        if ($domain = $Q->get('domain')) {
            $redirectRoot .= '/' . DOMAIN_SEGMENT . '/' . $domain . '/';
        }
        try {
            $req = Http::init(BASE_URL . $redirectRoot . $trimmedPath, 'GET');
            $req->setRequestHeaders([
                'User-Agent: ' . 'acms-include ' . UA,
                'Accept-Language: ' . HTTP_ACCEPT_LANGUAGE,
            ]);
            $response = $req->send();
            if (strpos(Http::getResponseHeader('http_code'), '200') === false) {
                throw new RuntimeException(Http::getResponseHeader('http_code'));
            }
            $txt = (string) $response->getResponseBody();
            if (empty($txt)) {
                throw new RuntimeException('empty file.');
            }
            $charset = mb_detect_encoding($txt, 'UTF-8, EUC-JP, SJIS-win, SJIS');
            if ($charset && 'UTF-8' != $charset) {
                $txt = mb_convert_encoding($txt, 'UTF-8', $charset);
            }
            if ($txt === false) {
                return '';
            }
            return $txt;
        } catch (Exception $e) {
            AcmsLogger::warning('テンプレートを取得できませんでした', Common::exceptionArray($e, ['path' => $trimmedPath]));
        }
        return '';
    }

    /**
     * URLからテンプレートを取得
     *
     * @param string $url
     * @return string
     */
    protected function getTemplateFromUrl(string $url): string
    {
        $txt = '';
        try {
            $txt = file_get_contents($url);
        } catch (Exception $e) {
            return '';
        }
        if ($txt === false) {
            return '';
        }
        $charset = mb_detect_encoding($txt, 'UTF-8, EUC-JP, SJIS-win, SJIS');
        if ($charset && $charset !== 'UTF-8') {
            $txt = mb_convert_encoding($txt, 'UTF-8', $charset);
        }
        return $txt;
    }

    /**
     * インクルード文にコメントを追加
     *
     * @param string $path
     * @param string $txt
     * @return string
     */
    protected function insertIncludeComment(string $path, string $txt): string
    {
        $path = ltrim($path, '/');
        $paths = explode('.', $path);
        if (isDebugMode() && $txt && in_array(end($paths), ['html', 'htm'], true) && !is_int(strpos(MIME_TYPE, 'xml'))) {
            $txt = $this->includeCommentBegin($path) . $txt . $this->includeCommentEnd($path);
            $txt = $this->setTemplateComment($txt, $path);
        }
        return $txt;
    }

    /**
     * テンプレートの継承を解決
     * 速度が求められる所なので、拡張性、汎用性は考えず関数で解決
     *
     * @param string $string
     * @param string $theme
     * @param int $bid
     * @param bool $tplCache
     * @return string
     */
    protected function extendTemplate(string $string, string $theme, int $bid, bool $tplCache = false): string
    {
        global $extend_section_stack;

        $string = preg_replace(
            [
                "/@extends?[\t\s　]*\([\t'\"\s　]*([^\)\n'\"]*?)[\t'\"\n\s　]*\);?/u",
                "/@section[\t\s　]*\([\t'\"\s　]*([^\)\n'\"]*?)[\t'\"\n\s　]*\);?/u",
            ],
            [
                "@extends($1)",
                "@section($1)",
            ],
            $string
        );

        // stack section
        $aryToken = preg_split("/(@section|@endsection)(\([^\)]+\))?/", $string, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($aryToken === false) {
            throw new RuntimeException('Failed to split template string for section parsing.');
        }
        $i = count($aryToken) - 1;
        $hierarchy = 0;
        $endsection = 0;
        for (; $i >= 0; $i--) {
            $token = $aryToken[$i];
            if ($token === '@section') {
                $id = trim($aryToken[$i + 1], '()');
                $j = $i + 3;
                for (; isset($aryToken[$j]); $j++) {
                    $token = $aryToken[$j];
                    if ($token === '@section') {
                        $hierarchy++;
                    }
                    if ($token === '@endsection') {
                        if ($hierarchy === $endsection) {
                            $tpl = '';
                            for ($k = $i + 2; $k < $j; $k++) {
                                $tpl .= $aryToken[$k];
                            }
                            $extend_section_stack[$id][] = $tpl;
                            break;
                        }
                        $endsection++;
                    }
                }
            }
        }

        // extends file
        if (preg_match('/@extends\(([^)]+)\)/', $string, $matches)) {
            $extend_path = $matches[1];

            $extendPathTpl = $this->resolver->resolvePath($extend_path, $theme, '/');
            $extendPathTpl = '<!--#include file="' . $extendPathTpl . '" vars=""-->';
            if (!$extend_string = $this->spreadTemplate($extendPathTpl, $theme, $bid, true, $tplCache)) {
                return preg_replace('/@(extends|section|endsection)\([^\)]+\)/', '', $string);
            } else {
                return $extend_string;
            }
        }
        return $this->resolveSection($string);
    }

    /**
     * テンプレートの継承（@section）を解決
     *
     * @param string $string
     * @return string
     */
    protected function resolveSection(string $string): string
    {
        global $extend_section_stack;

        $aryToken = preg_split("/(@section|@endsection)(\([^\)]+\))?/u", $string, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($aryToken === false) {
            throw new RuntimeException('Failed to split template string for section parsing.');
        }
        $i = count($aryToken) - 1;
        for (; $i >= 0; $i--) {
            $token = $aryToken[$i];
            if ($token === '@section') {
                $id = trim($aryToken[$i + 1], '()');
                // @parent
                if (isset($extend_section_stack[$id]) && is_array($extend_section_stack[$id])) {
                    $s = count($extend_section_stack[$id]) - 1;
                    for (; $s >= 0; $s--) {
                        $section = $extend_section_stack[$id][$s];

                        if (preg_match_all('/(@+)parent/', $section, $matches, PREG_SET_ORDER)) {
                            foreach ($matches as $parent) {
                                $num = strlen($parent[1]);
                                $index = $num + $s;
                                if (isset($extend_section_stack[$id][$index])) {
                                    $parent_tpl = $extend_section_stack[$id][$index];
                                } else {
                                    $parent_tpl = '';
                                }
                                $pattern = '/[^@]@{' . $num . '}parent/u';
                                $section = preg_replace($pattern, $parent_tpl, $section);
                            }
                            $extend_section_stack[$id][$s] = $section;
                        }
                    }
                }
                // @section
                $j = $i + 3;
                for (; isset($aryToken[$j]); $j++) {
                    $token = $aryToken[$j];
                    if ($token === '@endsection') {
                        $aryToken[$i] = '';
                        $aryToken[$i + 1] = '';
                        $aryToken[$j] = '';
                        for ($k = $i + 2; $k < $j; $k++) {
                            $aryToken[$k] = '';
                        }
                        if (isset($extend_section_stack[$id][0])) {
                            $aryToken[$i + 2] = $this->resolveSection($extend_section_stack[$id][0]);
                        }
                        break;
                    }
                }
            }
        }
        return join('', array_clean($aryToken));
    }

    /**
     * @viteを解決
     *
     * @param string $string テンプレート文字列
     * @param string $theme テーマ名
     * @param int $bid ブログID
     * @return string
     */
    private function resolveVite(string $string, string $theme, int $bid): string
    {
        $string = preg_replace(
            "/@viteReactRefresh/",
            Vite::generateReactRefreshHtml(),
            $string
        ) ?? $string;
        $string = preg_replace(
            "/@vite?[\t\s　]*\([\t\s　]*([^\)\n'\"]*?)[\t\n\s　]*\);?/u",
            "@vite($1)",
            $string
        ) ?? $string;
        $regex = '/@vite\(\s*(\[(?:"[^"]*"|\'[^\']*\')(?:\s*,\s*(?:"[^"]*"|\'[^\']*\'))*\]|(?:"[^"]*"|\'[^\']*\'))\s*(?:,\s*(\{[^\)]+\}))?\s*\)/u'; // phpcs:ignore

        return preg_replace_callback($regex, function ($matches) use ($theme, $bid) {
            // エントリーポイントの解析
            $entrypoints = $matches[1];
            if (substr($entrypoints, 0, 1) === '[') {
                // 配列形式の場合
                $entrypoints = trim($entrypoints, '[]');
                $entrypoints = array_map(
                    function ($entrypoint) {
                        return trim(trim($entrypoint), '\'"');
                    },
                    explode(',', $entrypoints)
                );
            } else {
                // 単一のエントリーポイントの場合
                $entrypoints = [trim($entrypoints, '\'"')];
            }
            // オプションの解析
            $options = isset($matches[2]) ? json_decode($matches[2], true) : [];
            $html = $this->resolver->rewritePaths(Vite::generateHtml($entrypoints, $options), $theme, '/', $bid);
            return $html;
        }, $string) ?? $string;
    }

    /**
     * verbatimブロックを保護（エスケープ）
     *
     * @param string $string
     * @return string
     */
    private function protectVerbatim(string $string): string
    {
        return verbatim($string, true);
    }

    /**
     * verbatimブロックを復元
     *
     * @param string $string
     * @return string
     */
    private function restoreVerbatim(string $string): string
    {
        return verbatim($string, false);
    }
}
