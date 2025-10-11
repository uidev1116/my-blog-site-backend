<?php

namespace Acms\Services\HtmlPurifier;

use HTMLPurifier;
use HTMLPurifier_HTML5Config;
use HTMLPurifier_HTMLDefinition;
use Acms\Services\Facades\LocalStorage;

class Helper
{
    /**
     * キャッシュディレクトリのパス
     *
     * @var string
     */
    protected $cacheDir = '';

    /**
     * HTMLPurifierのインスタンス
     *
     * @var HTMLPurifier
     */
    protected $htmlPurifier;

    public function __construct()
    {
        $this->cacheDir = CACHE_DIR . 'html-purifier/';
        LocalStorage::makeDirectory($this->cacheDir);

        $dengerousTags = configArray('dangerous_tags');
        $dangerousTags = $dengerousTags ? $dengerousTags : ['script', 'iframe'];
        $this->htmlPurifier = $this->loadPurifier($dangerousTags);
    }

    /**
     * HTMLをクリーンアップする
     *
     * @param string|array $html
     * @return string
     */
    public function clean(string|array $html): string
    {
        if (is_array($html)) {
            $html = implode($html);
        }
        if (!$html) {
            return strval($html);
        }
        $html = $this->codeEscape($html); // コード表示のエスケープ
        $unescaped = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->htmlPurifier->purify($unescaped);
    }

    /**
     * HTMLPurifierのキャッシュをクリアする
     *
     * @return void
     */
    public function clearCache(): void
    {
        LocalStorage::removeDirectory($this->cacheDir);
    }

    /**
     * HTMLPurifierのインスタンスをロードする
     *
     * @param array $notAllowedTags
     * @return HTMLPurifier
     */
    protected function loadPurifier(array $notAllowedTags = ['script', 'iframe', 'form']): HTMLPurifier
    {
        $config = HTMLPurifier_HTML5Config::createDefault();
        $config->set('HTML.Doctype', 'HTML5');
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Attr.EnableID', true); // id属性を許可する
        $config->set('Attr.AllowedRel', ['noopener', 'noreferrer', 'alternate', 'author', 'bookmark', 'canonical', 'external', 'help', 'icon', 'license', 'manifest', 'me', 'next', 'nofollow', 'opener', 'preconnect', 'prefetch', 'preload', 'prerender', 'prev', 'privacy-policy', 'search', 'stylesheet', 'tag', 'terms-of-service']); // rel属性を許可する
        $config->set('Attr.DefaultImageAlt', ''); // 自動でaltが入る機能をオフ
        $config->set('Attr.ID.HTML5', true); // クラス命名規則を緩和
        // $config->set('AutoFormat.Linkify', true); // URLを自動でリンク化
        $config->set('Attr.AllowedFrameTargets', ['_blank', '_self']); // target属性を緩和
        $config->set('CSS.AllowImportant', true); // CSSのimportantを許可
        $config->set('CSS.AllowTricky', true); // トリッキーなCSSを許可（display:noneなど）
        $config->set('CSS.MaxImgLength', '3000px'); // 画像の最大サイズを指定（HTML.MaxImgLength も同時に指定）
        $config->set('CSS.Trusted', true); // 利用できるCSSを緩和
        $config->set('Core.AllowHostnameUnderscore', true); // ホスト名にアンダースコアを許容
        $config->set('Core.DisableExcludes', true);
        $config->set('Core.EscapeInvalidTags', true); // 無効なタグを削除ではなく、エスケープして出力
        $config->set('HTML.Attr.Name.UseCDATA', true); // name属性の命名規則の緩和
        $config->set('HTML.MaxImgLength', 3000); // 画像の最大サイズを指定（CSS.MaxImgLength も同時に指定）
        $config->set('HTML.Trusted', true); // 利用できるHTMLを緩和
        $config->set('HTML.ForbiddenElements', $notAllowedTags); // 禁止にするタグ
        $config->set('Output.FixInnerHTML', false); // http://htmlpurifier.org/live/configdoc/plain.html#Output.FixInnerHTML
        $config->set('Cache.SerializerPath', $this->cacheDir); // キャッシュディレクトリの指定

        // ブロックエディター対応
        if (($def = $config->getDefinition('HTML', true, true)) instanceof HTMLPurifier_HTMLDefinition) {
            $def->addAttribute('div', 'data-type', 'CDATA');
            $def->addAttribute('div', 'data-media-type', 'CDATA');
            $def->addAttribute('div', 'data-display-type', 'CDATA');
            $def->addAttribute('div', 'data-icon', 'CDATA');
            $def->addAttribute('div', 'data-icon-width', 'CDATA');
            $def->addAttribute('div', 'data-icon-height', 'CDATA');
            $def->addAttribute('div', 'data-alt', 'CDATA');
            $def->addAttribute('div', 'data-caption', 'CDATA');
            $def->addAttribute('div', 'data-layout', 'CDATA');
            $def->addAttribute('div', 'data-position', 'CDATA');
            $def->addAttribute('div', 'data-align', 'CDATA');
            $def->addAttribute('div', 'data-extension', 'CDATA');
            $def->addAttribute('div', 'data-file-size', 'CDATA');
            $def->addAttribute('div', 'data-mid', 'CDATA');
            $def->addAttribute('div', 'data-eid', 'CDATA');
            $def->addAttribute('div', 'data-width', 'CDATA');
            $def->addAttribute('div', 'data-link', 'CDATA');
            $def->addAttribute('div', 'data-no-lightbox', 'CDATA');
            $def->addAttribute('span', 'data-font-size', 'CDATA');
            $def->addAttribute('span', 'data-font-family', 'CDATA');
            $def->addAttribute('span', 'data-font-color', 'CDATA');

            $def->addAttribute('a', 'data-rel', 'CDATA');
            $def->addAttribute('a', 'data-group', 'CDATA');
            $def->addAttribute('a', 'data-type', 'CDATA');
            $def->addAttribute('img', 'loading', 'CDATA');
            $def->addAttribute('img', 'data-mid', 'CDATA');
            $def->addAttribute('table', 'data-scrollable', 'CDATA');
            $def->addAttribute('th', 'colwidth', 'CDATA');
            $def->addAttribute('td', 'colwidth', 'CDATA');
        }
        return new HTMLPurifier($config);
    }

    /**
     * HTMLの<pre><code>内のコードをエスケープする
     *
     * @param string $html
     * @return string
     */
    protected function codeEscape(string $html): string
    {
        $html = preg_replace_callback(
            '#<pre><code>(.*?)</code></pre>#is',
            function ($matches) {
                // HTMLをエスケープ
                $escaped = htmlspecialchars($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $escaped = str_replace(['{', '}'], ['&#123;', '&#125;'], $escaped);
                return "<pre><code>{$escaped}</code></pre>";
            },
            $html
        );
        if ($html) {
            // インライン code の中身をエスケープ（ただし pre > code には二重でかからないように）
            $html = preg_replace_callback(
                '#(?<!<pre>)<code>(.*?)</code>#is',
                function ($matches) {
                    $escaped = htmlspecialchars($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $escaped = str_replace(['{', '}'], ['&#123;', '&#125;'], $escaped);
                    return "<code>{$escaped}</code>";
                },
                $html
            );
        }
        return $html ?? '';
    }
}
