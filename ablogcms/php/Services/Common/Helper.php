<?php

namespace Acms\Services\Common;

use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\JQuery;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PrivateStorage;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Image;
use Acms\Services\Facades\Cache;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Facades\RichEditor;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Session;
use Acms\Services\Facades\Template as Tpl;
use Acms\Services\Facades\Login;
use Acms\Services\Common\MimeTypeValidator;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\Random;
use cebe\markdown\MarkdownExtra;
use SQL;
use Field;
use Field_Search;
use Field_Validation;
use Template;
use ACMS_Http;
use ACMS_Corrector;
use ACMS_POST_Image;
use ACMS_RAM;
use ACMS_Hook;
use Exception;
use RuntimeException;
use DOMDocument;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

class Helper
{
    /**
     * @var \Field
     */
    protected $Post;

    /**
     * @var \Field
     */
    protected $Get;

    /**
     * @var \Field
     */
    protected $Q;

    /**
     * extract()後の削除フィールドを一時保存
     *
     * @var \Field
     */
    protected $deleteField;

    /**
     * @var \Acms\Services\Cache\Contracts\AdapterInterface
     */
    protected $cacheField;

    /**
     * 現在のソルト
     *
     * @var string|null
     */
    private $currentSalt = null;

    /**
     * 1つ前のソルト
     *
     * @var string|null
     */
    private $previousSalt = null;

    /**
     * アプリの固定ソルト
     *
     * @var string|null
     */
    private $appSalt = null;

    /**
     * V2モジュールかどうか判定用のフラグ
     *
     * @var bool
     */
    private $isV2Module = false;

    /**
     * 強制的にV1ビルドを行うかどうかのフラグ
     *
     * @var bool
     */
    private $isForceV1Build = false;

    /**
     * メディアの配信URL
     *
     * @var string
     */
    private $mediaDeliveryUrl = '';

    /**
     * 管理ドメインリスト（キャッシュ）
     *
     * @var array<string>|null
     */
    private ?array $managedDomainsCache = null;

    /**
     * MIMEタイプ検証クラス
     *
     * @var MimeTypeValidator
     */
    private MimeTypeValidator $mimeTypeValidator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $app = \App::getInstance();
        assert($app instanceof \Acms\Application);
        $this->Q =& $app->getQueryParameter();
        $this->Get =& $app->getGetParameter();
        $this->Post =& $app->getPostParameter();
        $this->cacheField = Cache::field();
        $this->mimeTypeValidator = new MimeTypeValidator();

        $mediaDeliveryUrl = env('ASSETS_DELIVERY_URL', '');
        if (!!$mediaDeliveryUrl) {
            $this->mediaDeliveryUrl = rtrim($mediaDeliveryUrl, '/');
        }
    }

    /**
     * V2モジュールとして実行中か判定
     *
     * @return boolean
     */
    public function isV2Module(): bool
    {
        return $this->isV2Module;
    }

    /**
     * 強制的にV1ビルドを行うか判定
     *
     *
     * @return boolean
     */
    public function isForceV1Build(): bool
    {
        return $this->isForceV1Build;
    }

    /**
     * V2モジュールとして実行中か設定
     *
     * @param boolean $isV2Module
     * @return void
     */
    public function setV2Module(bool $isV2Module): void
    {
        $this->isV2Module = $isV2Module;
    }

    /**
     * 強制的にV1ビルドを行うか設定
     *
     * @param boolean $isForceV1Build
     * @return void
     */
    public function setForceV1Build(bool $isForceV1Build): void
    {
        $this->isForceV1Build = $isForceV1Build;
    }

    /**
     * 管理ドメインのリストを取得（キャッシュあり）
     *
     * @param array<string> $additionalDomains 追加で許可するドメインのリスト
     * @return array<string>
     */
    protected function getManagedDomains(array $additionalDomains = []): array
    {
        if ($this->managedDomainsCache === null) {
            $domains = [];

            $sql = SQL::newSelect('blog');
            $sql->setSelect('blog_domain', null, null, 'DISTINCT');
            $domains = array_merge($domains, DB::query($sql->get(dsn()), 'list'));

            $sql = SQL::newSelect('alias');
            $sql->setSelect('alias_domain', null, null, 'DISTINCT');
            $sql->addWhereOpr('alias_status', 'open');
            $domains = array_merge($domains, DB::query($sql->get(dsn()), 'list'));

            $this->managedDomainsCache = array_unique($domains);
        }

        $domains = $this->managedDomainsCache;
        if (count($additionalDomains) > 0) {
            $domains = array_merge($domains, $additionalDomains);
            $domains = array_unique($domains);
        }

        return $domains;
    }

    /**
     * 指定されたドメインが管理ドメインかどうかを判定
     *
     * @param string $domain チェックするドメイン
     * @param array<string> $additionalDomains 追加で許可するドメインのリスト
     * @return bool
     */
    public function isManagedDomain(string $domain, array $additionalDomains = []): bool
    {
        if ($domain === '') {
            return false;
        }
        return in_array($domain, $this->getManagedDomains($additionalDomains), true);
    }

    /**
     * 指定URLが管理ドメインからのものかどうかを判定
     *
     * @param string $url
     * @return bool
     */
    protected function isUrlFromManagedDomain(string $url): bool
    {
        // WHATWG パーサーを使うことで日本語パスを含む絶対URLも正しくホスト抽出できる。
        // 相対URLは parse() が null を返すため、自ドメインとみなす既存の動作が維持される。
        $host = WhatWgUrl::parse($url)?->getAsciiHost();
        if ($host === null) {
            return true; // 相対URLは自ドメインとみなす
        }
        // HTTP_HOSTを含めてチェック（相対URLの場合は自ドメインとみなすため）
        return in_array($host, array_map('strtolower', $this->getManagedDomains([HTTP_HOST])), true);
    }

    /**
     * メディアの配信先URLを書き換え
     *
     * @param string $url
     * @return string
     */
    public function replaceDeliveryUrl(string $url): string
    {
        if (!$this->mediaDeliveryUrl) {
            return $url;
        }
        // 管理ドメイン以外は置換しない
        if (!$this->isUrlFromManagedDomain($url)) {
            return $url;
        }
        $mediaDeliveryUrl = rtrim($this->mediaDeliveryUrl, '/');
        // 安全にディレクトリ名を正規表現化（前後の / を吸収）
        $dirs = implode('|', array_map(
            fn($d) => preg_quote(trim($d, '/'), '~'),
            [MEDIA_LIBRARY_DIR, ARCHIVES_DIR]
        ));
        // 例: https://mydomain.com/hoge/media/... や /hoge/media/... もOK
        // 末尾の ?query や #hash も保持
        // 先行する任意のサブディレクトリは「ターゲットDIRではない」ことを保証してスキップ
        // その後で "/(media|archives)/..." を $1 にキャプチャ。?query と #hash は $2
        $pattern = '~(?:https?://[^/]+)?(?:(?:/(?!' . $dirs . ')(?:[^/?#]+))*)'  // 前置きパス（ただし target dir ではない）
            . '(/(?:' . $dirs . ')/[^?#\s"\']*)' // ← ここからを置換対象として $1
            . '([?#][^\s"\']*)?' // クエリ/ハッシュ（任意）
            . '~iu';
        $replacement = $mediaDeliveryUrl . '$1$2';

        return preg_replace($pattern, $replacement, $url) ?? $url;
    }

    /**
     * メディアの配信先URLを書き換え（全て）
     *
     * @param string $html
     * @return string
     */
    public function replaceDeliveryUrlAll(string $html): string
    {
        if (!$this->mediaDeliveryUrl) {
            return $html;
        }

        // 1) 単一URL属性（href/src/poster/data-src/data-original）
        $attrPattern = '~\b(href|content|src|poster|data-src|data-original)\s*=\s*(["\'])(.*?)\2~i';
        $html = preg_replace_callback($attrPattern, function ($m) {
            [$full, $attr, $q, $val] = $m;

            // javascript:, mailto:, data: はスキップ
            if (preg_match('~^(?:javascript:|mailto:|data:)~iu', $val)) {
                return $full;
            }
            $new = $this->replaceDeliveryUrl($val);
            return $attr . '=' . $q . $new . $q;
        }, $html) ?? $html;

        // 2) srcset（複数URL: "url size, url size, ..."）
        $srcsetPattern = '~\bsrcset\s*=\s*(["\'])(.*?)\1~iu';
        $html = preg_replace_callback($srcsetPattern, function ($m) {
            $q = $m[1];
            $list = $m[2];

            $items = array_map('trim', explode(',', $list));
            $items = array_map(function ($item) {
                // "URL [descriptor]" に分解（descriptor は省略可）
                // 先頭の1トークンをURLとみなす
                if ($item === '') {
                    return $item;
                }
                $parts = preg_split('/\s+/', $item, 2);
                $url = $parts[0];
                $desc = $parts[1] ?? '';
                $url = $this->replaceDeliveryUrl($url);
                return trim($url . ' ' . $desc);
            }, $items);

            return 'srcset=' . $q . implode(', ', $items) . $q;
        }, $html) ?? $html;

        // 3) style属性の url(...)
        $styleAttrPattern = '~\bstyle\s*=\s*(["\'])(.*?)\1~is';
        $html = preg_replace_callback($styleAttrPattern, function ($m) {
            $q = $m[1];
            $css = $m[2];
            $css = preg_replace_callback('~url\(\s*(["\']?)([^)\'"]+)\1\s*\)~iu', function ($n) {
                $u = $n[2];
                // data: はスキップ
                if (preg_match('~^data:~i', $u)) {
                    return $n[0];
                }
                $u = $this->replaceDeliveryUrl($u);
                return 'url(' . $u . ')';
            }, $css) ?? $css;
            return 'style=' . $q . $css . $q;
        }, $html) ?? $html;

        return $html;
    }

    /**
     * URL を完全な absolute URL（scheme + host + path）に変換する。
     *
     * グローバル状態に依存しない pure 関数（$baseUrl を省略した場合のみ BASE_URL を読む）。
     *
     * - 既に scheme 付き / protocol-relative の場合はそのまま返す。
     * - ルート相対 `/foo` は base の origin（scheme + host + port）と結合する。
     *   base のサブパス（`/site/`）は無視される。
     * - ドキュメント相対 `foo` は base 全体（origin + path）と結合する。
     *
     * @param string $path 入力パス
     * @param string $offset URL の前置オフセット（DIR_OFFSET 相当）
     * @param ?string $baseUrl 解決の基準とする base URL。null の場合は BASE_URL を使用
     */
    public function toAbsoluteUrl(
        string $path,
        string $offset = '',
        ?string $baseUrl = null,
    ): string {
        if ($path === '') {
            return '';
        }
        if ($this->isAbsoluteUri($path)) {
            return $path;
        }
        $baseUrl ??= BASE_URL;
        $offset = trim($offset, '/');
        $baseUri = Rfc3986Uri::parse($baseUrl);

        if (str_starts_with($path, '/')) {
            // ルート相対 → base の origin のみ
            $resolvedBase = $this->extractOrigin($baseUri);
        } else {
            // ドキュメント相対 → base の origin + path（query は RFC 3986 §5.2.2 に従い破棄）
            $resolvedBase = $this->extractOrigin($baseUri) . rtrim($baseUri?->getPath() ?? '', '/');
        }
        $path = ltrim($path, '/');

        return $offset !== ''
            ? "{$resolvedBase}/{$offset}/{$path}"
            : "{$resolvedBase}/{$path}";
    }

    /**
     * URL を root-relative URL（`/path` 形式、scheme/host を含まない）に変換する。
     *
     * V1 / V2 の HTML テンプレート出力で「ブラウザが現在のホストで解決する」前提の
     * URL を生成するときに使う。グローバル状態に依存しない pure 関数（$baseUrl を
     * 省略した場合のみ BASE_URL を読む）。
     *
     * - 既に scheme 付き / protocol-relative の場合はそのまま返す。
     * - ルート相対 `/foo` はそのまま返す（既に root-relative なので変換不要）。
     * - ドキュメント相対 `foo` は base の path+query 部分のみと結合する。
     *
     * @param string $path 入力パス
     * @param string $offset URL の前置オフセット（DIR_OFFSET 相当）
     * @param ?string $baseUrl 解決の基準とする base URL。null の場合は BASE_URL を使用
     */
    public function toRootRelativeUrl(
        string $path,
        string $offset = '',
        ?string $baseUrl = null,
    ): string {
        if ($path === '') {
            return '';
        }
        if ($this->isAbsoluteUri($path)) {
            return $path;
        }
        if (str_starts_with($path, '/')) {
            // 既にルート相対なのでそのまま返す
            return $path;
        }
        $baseUrl ??= BASE_URL;
        $offset = trim($offset, '/');
        $path = ltrim($path, '/');

        // base の path 部分のみ取り出す（query は RFC 3986 §5.2.2 に従い破棄）
        $baseUri = Rfc3986Uri::parse($baseUrl);
        $resolvedBase = rtrim($baseUri?->getPath() ?? '', '/');

        return $offset !== ''
            ? "{$resolvedBase}/{$offset}/{$path}"
            : "{$resolvedBase}/{$path}";
    }

    /**
     * URI から origin（scheme + host + port）を抽出する。
     */
    private function extractOrigin(?Rfc3986Uri $uri): string
    {
        if ($uri === null) {
            return '';
        }
        $origin = ($uri->getScheme() ?? '') . '://' . ($uri->getHost() ?? '');
        if ($uri->getPort() !== null) {
            $origin .= ':' . $uri->getPort();
        }
        return $origin;
    }

    /**
     * V2モジュール、V2APIビルド時に、URLを絶対URLに変換
     * それ以外はそのままのURLを返す
     *
     * @param string $path
     * @param string $offset
     * @return string
     */
    public function resolveUrl($path, $offset = ''): string
    {
        if (isApiBuildOrV2Module()) {
            $encoded = Media::urlencode($path);
            // API ビルド時は完全絶対URL、V2 モジュール内のみ（API 非ビルド）は root-relative
            $newPath = isApiBuild()
                ? $this->toAbsoluteUrl($encoded, $offset)
                : $this->toRootRelativeUrl($encoded, $offset);
            return $this->replaceDeliveryUrl($newPath);
        }
        return Media::urlencode($this->replaceDeliveryUrl($path));
    }

    /**
     * 単一の相対URLを絶対URL（フルURL）に変換する
     *
     * - 空文字、`http(s)://`、プロトコル相対 (`//`)、`data:` 始まりの URL はそのまま返す
     * - `/` 始まりのルート相対パスは baseUrl のスキーム + ホスト (+ ポート) を前置する
     * - それ以外の相対パスは baseUrl 全体を前置する
     *
     * @param string $url
     * @param string $baseUrl
     * @return string
     */
    public function convertRelativeUrlToAbsolute(string $url, string $baseUrl): string
    {
        if ($url === '') {
            return '';
        }
        if ($this->isAbsoluteUri($url)) {
            return $url;
        }
        $baseUri = Rfc3986Uri::parse($baseUrl);
        $base = ($baseUri?->getScheme() ?? 'https') . '://' . ($baseUri?->getHost() ?? '');
        if ($baseUri?->getPort() !== null) {
            $base .= ':' . $baseUri->getPort();
        }
        if ($url[0] === '/') {
            return $base . $url;
        }
        return rtrim($baseUrl, '/') . '/' . $url;
    }

    /**
     * HTML内のアセット参照（画像・動画・スタイルシート等）を絶対URLに変換する。
     *
     * APIビルド／V2モジュールなどヘッドレス出力向けの正規化。クロスドメインで配信
     * される API レスポンスでは、フロント（Next.js 等）が別ドメインから取得する都合上、
     * 画像・動画等は CMS ドメインの絶対URLになっている必要がある。
     *
     * リンク（<a>/<area>）は意図的に**一切変換しない**。これはフロント側のルーター
     * （Next.js の <Link> など）の解釈に任せるため、および `<a href="section/">` の
     * ようなドキュメント相対が CMS のサブパスを引きずって `/blog/section/` のように
     * 出力されるのを避けるため。
     *
     * - 対象: <img>/<video>/<source>/<audio>/<track>/<link>（および <img>/<source> の srcset）
     * - 非対象（一切触らない）: <a>/<area>
     * - 非対象（CSRF・実行コンテキストへの影響回避）: <form>/<script>/<iframe>
     * - スキーム付き URL（mailto:, tel:, javascript:, data:, blob: など）、
     *   プロトコル相対（//host/...）、フラグメントのみ（#hash）、空文字は変換しない。
     * - base が origin（scheme + host）を含む場合のみ絶対URL化し、path のみの
     *   base のときはルート相対のまま保持する。
     */
    public function convertAssetUrlsToAbsolute(string $html, string $baseUrl): string
    {
        $assetTags = [
            ['tag' => 'img', 'attr' => 'src'],
            ['tag' => 'video', 'attr' => 'src'],
            ['tag' => 'video', 'attr' => 'poster'],
            ['tag' => 'source', 'attr' => 'src'],
            ['tag' => 'audio', 'attr' => 'src'],
            ['tag' => 'track', 'attr' => 'src'],
            ['tag' => 'link', 'attr' => 'href'],
        ];
        $assetSrcsetTags = [
            ['tag' => 'img', 'attr' => 'srcset'],
            ['tag' => 'source', 'attr' => 'srcset'],
        ];

        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($assetTags as $entry) {
            $elements = $doc->getElementsByTagName($entry['tag']);
            foreach ($elements as $element) {
                $attrValue = $element->getAttribute($entry['attr']);
                $resolved = $this->resolveAgainstBase($attrValue, $baseUrl);
                if ($resolved !== $attrValue) {
                    $element->setAttribute($entry['attr'], $resolved);
                }
            }
        }
        foreach ($assetSrcsetTags as $entry) {
            $elements = $doc->getElementsByTagName($entry['tag']);
            foreach ($elements as $element) {
                if (!$element->hasAttribute($entry['attr'])) {
                    continue;
                }
                $attrValue = $element->getAttribute($entry['attr']);
                $resolved = $this->resolveSrcset($attrValue, $baseUrl);
                if ($resolved !== $attrValue) {
                    $element->setAttribute($entry['attr'], $resolved);
                }
            }
        }

        $innerHTML = '';
        foreach ($doc->childNodes as $node) {
            $innerHTML .= $doc->saveHTML($node);
        }
        // 不要なXML宣言を除去
        $innerHTML = str_ireplace('<?xml encoding="UTF-8">', '', $innerHTML);
        libxml_clear_errors();

        return $innerHTML;
    }

    /**
     * URL を base URL に対して RFC 3986 §5（Reference Resolution）に従って解決する。
     *
     * 解決ロジック自体は league/uri-polyfill の WHATWG パーサーに委譲する
     * （PHP 8.5+ ではネイティブ実装が使われる）。
     *
     * - 入力が空文字／フラグメントのみ（`#hash`）／プロトコル相対（`//host/...`）／
     *   スキーム付き（`mailto:`, `tel:`, `data:`, `blob:`, `http(s):` など）のときは
     *   そのまま返す（HTML 出力としては変換せずブラウザに任せる）。
     * - base に origin（scheme + authority）がある場合は absolute URL に解決する。
     * - base が path のみ（origin 無し）の場合は仮の origin を被せて RFC §5 を適用し、
     *   結果から仮 origin を取り除いて path 部分のみを返す。これにより `../foo` を
     *   `/blog/` に対して `/foo` のように正規化できる。
     *
     * @param string $url     解決したい URL。絶対・相対・ルート相対いずれでも可。
     *                        例: `../images/foo.jpg` / `/css/style.css` / `https://example.com/`
     * @param string $baseUrl 基準となる URL。絶対URL（`https://example.com/blog/`）か
     *                        ルート相対パス（`/blog/`）を想定する。
     *                        絶対URLの場合は WHATWG パーサーで直接解決する。
     *                        ルート相対の場合は仮 origin を付与してから解決し、後で除去する。
     * @return string         解決済みの URL。解決できない場合は $url をそのまま返す。
     */
    /**
     * `srcset` 属性値内の各 URL を base URL に対して解決する。
     *
     * HTML Living Standard §4.8.4.3.2 "parse a srcset attribute" のアルゴリズムに準拠する。
     * URL は「ASCII whitespace でない連続」として読み取り、カンマでは分割しないため、
     * データURL内のカンマも誤分割されずに URL の一部として扱われる。
     *
     * @param string $srcset  srcset 属性値（例: "/a.jpg 1x, /b.jpg 2x"）
     * @param string $baseUrl 解決の基準となる URL
     * @return string         各 URL を解決した srcset 文字列
     */
    private function resolveSrcset(string $srcset, string $baseUrl): string
    {
        if (trim($srcset) === '') {
            return $srcset;
        }
        $position = 0;
        $length = strlen($srcset);
        $candidates = [];

        while ($position < $length) {
            // §4.1: 候補境界では ASCII whitespace と U+002C COMMA をまとめてスキップ
            while ($position < $length && (ctype_space($srcset[$position]) || $srcset[$position] === ',')) {
                $position++;
            }
            if ($position >= $length) {
                break;
            }
            // §4.3: 空白でない連続を URL として読む（カンマも非空白なので URL の一部）
            $urlStart = $position;
            while ($position < $length && !ctype_space($srcset[$position])) {
                $position++;
            }
            $url = substr($srcset, $urlStart, $position - $urlStart);
            $descriptor = '';

            if (str_ends_with($url, ',')) {
                // §4.5: URL 末尾がカンマなら剥がして、記述子なしの候補として確定
                $url = rtrim($url, ',');
                if ($url === '') {
                    continue;
                }
            } else {
                // §4.6: 次のカンマまでを記述子として読む
                while ($position < $length && ctype_space($srcset[$position])) {
                    $position++;
                }
                $descStart = $position;
                while ($position < $length && $srcset[$position] !== ',') {
                    $position++;
                }
                $descriptor = trim(substr($srcset, $descStart, $position - $descStart));
                if ($position < $length) {
                    $position++; // カンマを消費
                }
            }
            $resolvedUrl = $this->resolveAgainstBase($url, $baseUrl);
            $candidates[] = $descriptor === '' ? $resolvedUrl : $resolvedUrl . ' ' . $descriptor;
        }

        return implode(', ', $candidates);
    }

    private function resolveAgainstBase(string $url, string $baseUrl): string
    {
        // 空文字・フラグメント（#anchor）・プロトコル相対URL（//host/...）は
        // base に対して解決する必要がないのでそのまま返す
        if ($url === '' || $url[0] === '#' || str_starts_with($url, '//')) {
            return $url;
        }

        // RFC 3986 §3.1 に基づく scheme の検出。
        // `^[a-z][a-z0-9+.\-]*:` は「英字で始まり、英数字・+・.・- が続いたあとに : が来る」パターンで、
        // http: / https: / mailto: / tel: / javascript: / data: / blob: / ftp: などすべての
        // scheme 付き URI にマッチする。マッチした場合は既に絶対参照なので変換不要。
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) === 1) {
            return $url;
        }

        // ── ケース①: base が絶対URL（http:// または https:// で始まる）──────────────
        // WhatWgUrl::parse($url, $base) はブラウザと同じ URL 解決アルゴリズム（WHATWG URL Standard）で動作する。
        // 第2引数に base を渡すことで、$url が相対パス（例: ../images/foo.jpg）でも
        // base を起点に正しく絶対化してくれる。
        // 旧実装で使っていた Rfc3986Uri::resolve() は日本語などの非 ASCII 文字を含む
        // URL で null を返す問題があったため、WHATWG パーサーに切り替えている。
        $baseWhatWg = WhatWgUrl::parse($baseUrl);
        if ($baseWhatWg !== null) {
            $resolved = WhatWgUrl::parse($url, $baseWhatWg);
            return $resolved !== null ? $resolved->toAsciiString() : $url;
        }

        // ── ケース②: base がルート相対パス（/blog/ など、host を持たない）──────────
        // WHATWG パーサーは base に host が必須なので、/blog/ をそのまま渡してもパースできない。
        // そこで仮の origin「http://acms.internal」を先頭に付けて
        //   /blog/  →  http://acms.internal/blog/
        // という形で一時的に絶対 URL に変換してから解決し、
        // 解決後に仮 origin 部分を取り除いてパスだけを返す。
        // 例: $url = '../images/foo.jpg', $baseUrl = '/blog/entry/'
        //     → 仮 base: http://acms.internal/blog/entry/
        //     → 解決結果: http://acms.internal/blog/images/foo.jpg
        //     → 仮 origin 除去後: /blog/images/foo.jpg
        $synthOrigin = 'http://acms.internal';
        $synthBaseStr = $synthOrigin . (str_starts_with($baseUrl, '/') ? $baseUrl : '/' . $baseUrl);
        $synthBase = WhatWgUrl::parse($synthBaseStr);
        if ($synthBase === null) {
            return $url;
        }
        $resolved = WhatWgUrl::parse($url, $synthBase);
        if ($resolved === null) {
            return $url;
        }
        $resolvedStr = $resolved->toAsciiString();
        // 仮 origin を取り除いてパス部分だけ返す
        if (str_starts_with($resolvedStr, $synthOrigin)) {
            return substr($resolvedStr, strlen($synthOrigin));
        }
        return $resolvedStr;
    }

    /**
     * URL が "absolute URI" として自己解決可能かを判定する。
     *
     * 以下のいずれかなら true：
     * - scheme 付き URI（`http(s):`, `mailto:`, `tel:`, `data:`, `blob:`,
     *   `javascript:`, `ftp:` など）
     * - protocol-relative（`//host/...`）
     *
     * `/foo` `foo` `../foo` `#anchor` `?q=1` `''` のような **base に対して
     * 解決が必要な参照** は false。「ルート相対 `/foo` も解決済み扱いにしたい」
     * のような V1 互換の判定が必要な場合は、呼び出し側で
     * `str_starts_with($path, '/')` を別途チェックする。
     */
    private function isAbsoluteUri(string $url): bool
    {
        // protocol-relative（`//host/...`）は absolute 扱い
        if (str_starts_with($url, '//')) {
            return true;
        }
        // RFC 3986 に従って scheme を抽出。何らかの scheme があれば absolute
        $uri = Rfc3986Uri::parse($url);
        if ($uri !== null) {
            return $uri->getScheme() !== null;
        }
        // 日本語パスを含む絶対URLへのフォールバック: scheme はASCIIのみなので正規表現で判定
        return preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url) === 1;
    }

    /**
     * 暗号化キーを取得
     *
     * @return string
     */
    private function getEncryptKey(): string
    {
        // 必ず16/24/32バイトに揃える（ここでは32バイトに統一）
        return hash('sha256', $this->getAppSalt(), true);
    }

    /**
     * @return string
     */
    public function getEncryptIv()
    {
        $cipher = new AES('cbc');
        $cipher->setKey($this->getEncryptKey());

        return Random::string(($cipher->getBlockLength() >> 3));
    }

    /**
     * @param string $string
     * @param string $iv
     * @return string
     */
    public function encrypt($string, $iv)
    {
        $cipher = new AES('cbc');
        $cipher->setKey($this->getEncryptKey());
        $cipher->setIV($iv);

        return base64_encode($cipher->encrypt($string));
    }

    /**
     * @param string $cipherText
     * @param string $iv
     * @return string
     * @throws \LengthException IVの長さが不正な場合、または暗号文の長さがブロックサイズの倍数でない場合
     */
    public function decrypt($cipherText, $iv)
    {
        $cipher = new AES('cbc');
        $cipher->setKey($this->getEncryptKey());
        $cipher->setIV($iv);

        return $cipher->decrypt(base64_decode($cipherText)); // @phpstan-ignore-line
    }

    /**
     * アプリ全体で使用するSaltを更新・設定
     *
     * @return void
     */
    public function setAppSalt(): void
    {
        $sql = SQL::newSelect('sequence');
        $item = DB::query($sql->get(dsn()), 'row');

        if (!$item || !array_key_exists('sequence_current_salt', $item) || !array_key_exists('sequence_previous_salt', $item) || !array_key_exists('sequence_app_salt', $item)) {
            $this->currentSalt = PASSWORD_SALT_1;
            $this->previousSalt = PASSWORD_SALT_2;
            $this->appSalt = PASSWORD_SALT_1;
            AcmsLogger::error('データベースがアップデートされていません。管理画面の更新メニューからDBをアップデートください。');
            return;
        }
        $currentSalt = $item['sequence_current_salt'] ?? null;
        $previousSalt = $item['sequence_previous_salt'] ?? null;
        $appSalt = $item['sequence_app_salt'] ?? null;
        $updatedAt = strtotime($item['sequence_salt_updated_at'] ?? '2000-01-01 00:00:00');

        if ($appSalt === null) {
            $appSalt = "base64:" . base64_encode(random_bytes(32));
            $sql = SQL::newUpdate('sequence');
            $sql->addUpdate('sequence_app_salt', $appSalt);
            DB::query($sql->get(dsn()), 'exec');
        }
        if ($currentSalt === null || $previousSalt === null) {
            $currentSalt = "base64:" . base64_encode(random_bytes(32));
            $previousSalt = "base64:" . base64_encode(random_bytes(32));
            $sql = SQL::newUpdate('sequence');
            $sql->addUpdate('sequence_current_salt', $currentSalt);
            $sql->addUpdate('sequence_previous_salt', $previousSalt);
            $sql->addUpdate('sequence_app_salt', $appSalt);
            $sql->addUpdate('sequence_salt_updated_at', date('Y-m-d H:i:s', REQUEST_TIME));
            DB::query($sql->get(dsn()), 'exec');
        } elseif ((REQUEST_TIME - $updatedAt) > (60 * 60 * 24)) {
            $previousSalt = $currentSalt;
            $currentSalt = "base64:" . base64_encode(random_bytes(32));
            $sql = SQL::newUpdate('sequence');
            $sql->addUpdate('sequence_current_salt', $currentSalt);
            $sql->addUpdate('sequence_previous_salt', $previousSalt);
            $sql->addUpdate('sequence_salt_updated_at', date('Y-m-d H:i:s', REQUEST_TIME));
            DB::query($sql->get(dsn()), 'exec');
        }
        $this->currentSalt = $currentSalt;
        $this->previousSalt = $previousSalt;
        $this->appSalt = $appSalt;
    }

    /**
     * 現在のソルトを取得
     *
     * @return string
     */
    public function getCurrentSalt(): string
    {
        return $this->currentSalt ?? base64_encode(random_bytes(32));
    }

    /**
     * 現在のソルトを設定
     *
     * @param string $salt
     * @return void
     */
    public function setCurrentSalt(string $salt): void
    {
        $this->currentSalt = $salt;
    }

    /**
     * 1つ前のソルトを取得
     *
     * @return string
     */
    public function getPreviousSalt(): string
    {
        return $this->previousSalt ?? base64_encode(random_bytes(32));
    }

    /**
     * 1つ前のソルトを設定
     *
     * @param string $salt
     * @return void
     */
    public function setPreviousSalt(string $salt): void
    {
        $this->previousSalt = $salt;
    }

    /**
     * アプリの固定ソルトを取得
     *
     * @return string
     */
    public function getAppSalt(): string
    {
        return $this->appSalt ?? base64_encode(random_bytes(32));
    }

    /**
     * マークダウン文字列を解析する
     * @param string $txt
     * @return string
     */
    public function parseMarkdown($txt)
    {
        static $parser = null;
        if ($parser === null) {
            $parser = new MarkdownExtra();
        }
        return $parser->parse($txt);
    }

    /**
     * すぐにリダイレクトし、同一プロセスのバックグラウンドで処理を実行
     *
     * @param string $url
     */
    public function backgroundRedirect($url)
    {
        ignore_user_abort(true);
        set_time_limit(0);
        session_write_close(); // セッションロックを解除する

        $out = '';
        while (ob_get_level()) {
            ob_end_clean();
        }
        for ($i = 0; $i < 99999; $i++) {
            $out .= ' ';
        }

        header("Location: " . $url, true, 301);
        header("Content-Length: " . strlen($out));
        header("Connection: close");
        $this->addSecurityHeader();
        $this->clientCacheHeader(true);

        // 新しいバッファを開始
        if (ob_get_level() === 0) {
            ob_start();
        }

        // コンテンツ出力
        echo $out;

        sleep(2);

        // 環境に応じた終了処理
        if (function_exists('fastcgi_finish_request')) {
            // PHP-FPM環境
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            // LiteSpeed環境
            litespeed_finish_request();
        } else {
            // その他の環境
            ob_flush();
            flush();
            ob_end_flush();
        }
    }

    /**
     * セキュリティヘッダー
     */
    public function addSecurityHeader()
    {
        // クリックジャッキング対策
        if (config('x_frame_options') !== 'off') {
            if (config('x_frame_options') === 'DENY') {
                header('X-FRAME-OPTIONS: DENY');
            } else {
                header('X-FRAME-OPTIONS: SAMEORIGIN');
            }
        }
        // X-XSS-Protection
        if (config('x_xss_protection') !== 'off') {
            header('X-XSS-Protection: 1; mode=block');
        }
        // X-Content-Type-Options
        if (config('x_content_type_options') !== 'off') {
            header('X-Content-Type-Options: nosniff');
        }
        // Strict-Transport-Security(HSTS)
        if (SSL_ENABLE && FULLTIME_SSL_ENABLE && config('strict_transport_security') !== 'off') {
            header('Strict-Transport-Security: ' . config('strict_transport_security', 'max-age=86400; includeSubDomains'));
        }
        // Content-Security-Policy
        $csp = config('content_security_policy');
        if (!empty($csp) && $csp !== 'off') {
            header('Content-Security-Policy: ' . $csp);
        }
        // Referrer-Policy
        $referrerPolicy = config('referrer_policy', 'strict-origin-when-cross-origin');
        if (
            in_array(
                $referrerPolicy,
                [
                    'no-referrer',
                    'no-referrer-when-downgrade',
                    'origin',
                    'origin-when-cross-origin',
                    'same-origin',
                    'strict-origin',
                    'strict-origin-when-cross-origin',
                    'unsafe-url'
                ],
                true
            )
        ) {
            header('Referrer-Policy: ' . $referrerPolicy);
        }
    }

    /**
     * キャッシュ無効で安全なレスポンスヘッダーを組み立てます。
     *
     * @return void
     */
    public function setSafeHeadersWithoutCache(int $code = 200, string $mime = 'text/html'): void
    {
        http_response_code($code);
        header("Content-type: {$mime}; charset=" . config('charset', 'UTF-8'));
        $this->addSecurityHeader();
        $this->clientCacheHeader(true);
    }

    /**
     * CSRFトークンを生成
     *
     * @return string
     */
    public function createCsrfToken(): string
    {
        $session = Session::handle();
        if ($session->get('formTokenExpireAt') && $session->get('formTokenExpireAt') < REQUEST_TIME) {
            $session->delete('formToken'); // 更新期限がきれたCSRFトークンを削除
        }
        $token = $session->get('formToken');
        if (empty($token)) {
            $session->regenerate();
            $token = uniqueString();
            $session->set('formToken', $token);

            // 同時ログイン判定のための、クライアント情報を更新
            if (Login::isLoggedIn()) {
                /** @var int|null $sessionUserId */
                $sessionUserId = SUID;
                assert(is_int($sessionUserId)); // ログインしていることが保証されている
                Login::updateSessionClientInfo($sessionUserId);
            }
        }
        $session->set('formTokenExpireAt', (REQUEST_TIME + (60 * 60 * 6))); // CSRFトークンを更新間隔を6時間に設定
        $session->save();

        return $token;
    }

    /**
     * CSRFトークンをFromに付与
     *
     * @param string $tpl
     * @return string
     */
    public function addCsrfToken($tpl)
    {
        $tpl = preg_replace('@(<input\s+type="hidden"\s+name="formUniqueToken"\s+value="[^"]+">)@i', '', $tpl) ?? $tpl;
        $tpl = preg_replace('@(<input\s+type="hidden"\s+name="formToken"\s+value="[^"]+">)@i', '', $tpl) ?? $tpl;
        $tpl = preg_replace('@(<meta\\s+name="csrf-token"\s+content="[^"]+">)@i', '', $tpl) ?? $tpl;

        // ログアウト時 && POSTリクエストではない && ログインページでない && フォームじゃない && コメントフォームじゃない 時 は session start しない（Set-Cookie しない）CDNなどのキャッシュのため
        if (
            1
            && !ACMS_SID
            && !ACMS_POST
            && !IS_AUTH_SYSTEM_PAGE
            && !defined('IS_OTHER_LOGIN')
            && strpos($tpl, 'ACMS_POST_Form_') === false
            && strpos($tpl, 'ACMS_POST_Comment_') === false
            && strpos($tpl, 'ACMS_POST_Shop') === false
            && strpos($tpl, 'ACMS_POST_Download') === false
            && strpos($tpl, 'ACMS_POST_2GET_Ajax') === false
            && strpos($tpl, 'check-csrf-token') === false
            && strpos($tpl, 'hx-get') === false
            && strpos($tpl, 'hx-post') === false
            && ACMS_RAM::blogStatus(BID) !== 'secret'
            && (!CID || ACMS_RAM::categoryStatus(CID) !== 'secret')
        ) {
            $token = uniqueString();
        } else {
            $token = $this->createCsrfToken();
        }

        // form unique token の埋め込み
        $tpl = preg_replace('@(?=<\s*/\s*form[^\w]*>)@i', '<input type="hidden" name="formUniqueToken" value="' . uniqueString() . '">' . "\n", $tpl);
        // form に token の埋め込み
        $tpl = preg_replace('@(?=<\s*/\s*form[^\w]*>)@i', '<input type="hidden" name="formToken" value="' . $token . '">' . "\n", $tpl);
        // meta に token の埋め込み
        $tpl = preg_replace('@(?=<\s*/\s*head[^\w]*>)@i', '<meta name="csrf-token" content="' . $token . '">', $tpl);

        // htmx用 hx-push-url ヘッダーの埋め込み
        if ($tpl && strpos($tpl, config('htmx_ss_push_url_mark', 'data-acms-hx-push-url')) !== false) {
            $displayUrl = acmsLink([
                'tpl' => '',
            ], true, true, false, false);
            header("HX-Push-Url: {$displayUrl}");
        }

        return $tpl;
    }

    /**
     * CSRFトークンの存在チェック
     *
     * @return boolean
     */
    public function csrfTokenExists(): bool
    {
        $session = Session::handle();
        return !!$session->get('formToken');
    }

    /**
     * CSRFトークンのチェック
     *
     * @param string $token
     * @return boolean
     */
    public function checkCsrfToken(string $token): bool
    {
        $session = Session::handle();
        if (!!$session->get('formToken') && $session->get('formToken') === $token) {
            return true;
        }
        return false;
    }

    /**
     * ToDo: リファクタリング
     *
     * @param string $name
     * @return string
     */
    public function getHttpHeader(string $name): string
    {
        return $_SERVER[$name] ?? '';
    }

    /**
     * 許可されたajaxアクセスか判定（どのようなtpl指定であっても許可する）
     *
     * level-0: チェックをしない
     * level-1: RefererとAjaxリクエスト判定
     * level-2: CSRFトークンチェック
     *
     * @param int $level
     * @return bool
     */
    public function isAuthorizedAjaxRequest(int $level = 1): bool
    {
        try {
            if ($level === 0) {
                return true; // チェックを全くしない
            }
            if (!is_ajax()) {
                return false; // Ajaxアクセスでない場合
            }
            if (isCSRF()) {
                return false; // Refererが不正な場合
            }
            if ($level <= 1) {
                return true;
            }
            if (!$this->csrfTokenExists()) {
                return false; // CSRFトークンが存在しない
            }
            $token = $this->getHttpHeader('HTTP_X_CSRF_TOKEN');
            if (!$this->checkCsrfToken($token)) {
                return false; // CSRFトークンが一致しない
            }
            return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * 管理画面でテンプレート直で書かれているパスを、エイリアスを含んだURLに修正
     *
     * @param string $txt
     * @return string
     */
    public function fixAliasPath($txt)
    {
        $regex  = '@' .
            '<\s*a(?:"[^"]*"|\'[^\']*\'|[^\'">])*href\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|' .
            '<\s*form(?:"[^"]*"|\'[^\']*\'|[^\'">])*action\s*=\s*("[^"]+"|\'[^\']+\'|[^\'"\s>]+)(?:"[^"]*"|\'[^\']*\'|[^\'">])*>' .
            '@';
        $offset = 0;
        while (preg_match($regex, $txt, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $match[0][1] + strlen($match[0][0]);
            for ($mpt = 1; $mpt <= 2; $mpt++) {
                if (!empty($match[$mpt][0])) {
                    break;
                }
            }

            $path = trim($match[$mpt][0], '\'"'); // @phpstan-ignore-line
            if (preg_match('/^(?=.*bid\/\d+\/)(?!.*aid\/\d+\/).*$/', $path, $pathMatch)) {
                $path = preg_replace('/bid\/(\d+)\//', 'bid/$1/aid/' . AID . '/', $path);
                $txt = substr_replace($txt, '"' . $path . '"', $match[$mpt][1], strlen($match[$mpt][0])); // @phpstan-ignore-line
            }
        }
        return $txt;
    }

    /**
     * extract()後の削除フィールドを取得
     *
     * @return \Field
     */
    public function getDeleteField()
    {
        return $this->deleteField;
    }

    /**
     * メールテンプレートの解決
     *
     * @param string $path
     * @param Field $field
     * @param string|null $charset
     *
     * @return string
     */
    public function getMailTxt($path, $field, $charset = null)
    {
        if (!$path) {
            return '';
        }
        try {
            $tpl = LocalStorage::get($path, THEMES_DIR);
            if ($tpl === false) {
                return '';
            }
            if ($charset = detectEncode($tpl)) {
                $tpl = mb_convert_encoding($tpl, 'UTF-8', $charset);
            }
            if ($tpl === false) {
                return '';
            }
            return $this->getMailTxtFromTxt($tpl, $field);
        } catch (\Exception $e) {
            AcmsLogger::warning('メールテンプレートを取得できませんでした', [
                'detaile' => $e->getMessage(),
                'path' => $path,
            ]);
            return '';
        }
    }

    /**
     * @param string $txt
     * @param Field $field
     * @return string
     */
    public function getMailTxtFromTxt($txt, $field)
    {
        try {
            global $extend_section_stack;
            $extend_section_stack = [];
            $acmsTplEngine = Application::make('template.acms');
            assert($acmsTplEngine instanceof \Acms\Services\Template\Acms);
            $txt = buildVarBlocks($txt, true);

            $acmsTplEngine->loadFromString($txt, '/', config('theme'), BID);
            $post = Field_Validation::singleton('post');
            $acmsTplEngine->setPostData($post);
            $acmsTplEngine->setNoBuildIF(true);
            $tpl = $acmsTplEngine->render();
            $extend_section_stack = [];

            $Tpl = new Template($tpl, new ACMS_Corrector());
            $vars = Tpl::buildField($field, $Tpl);
            $Tpl->add(null, $vars);
            return buildVarBlocks(buildIF($Tpl->get()));
        } catch (\Exception $e) {
            AcmsLogger::warning('メールテンプレートを組み立てできませんでした', [
                'detaile' => $e->getMessage(),
                'text' => $txt,
            ]);
            return '';
        }
    }

    /**
     * メール設定の取得
     *
     * @param array{
     *   smtp-host?: string,
     *   smtp-port?: string,
     *   smtp-user?: string,
     *   smtp-pass?: string,
     *   smtp-verify-peer?: string,
     *   mail_from?: string,
     *   sendmail_path?: string,
     *   additional_headers?: string,
     *   smtp-google?: string,
     *   smtp-google-user?: string
     * } $argConfig
     *
     * @return non-empty-array<'additional_headers'|'mail_from'|'sendmail_path'|'smtp-google'|'smtp-google-user'|'smtp-host'|'smtp-pass'|'smtp-verify-peer'|'smtp-port'|'smtp-user',
     *   string
     * >
     */
    public function mailConfig($argConfig = [])
    {
        $config = [];

        foreach (
            [
                'mail_smtp-host' => 'smtp-host',
                'mail_smtp-port' => 'smtp-port',
                'mail_smtp-user' => 'smtp-user',
                'mail_smtp-pass' => 'smtp-pass',
                'mail_smtp-verify_peer' => 'smtp-verify-peer',
                'mail_from' => 'mail_from',
                'mail_sendmail_path' => 'sendmail_path',
                'mail_google_smtp' => 'smtp-google',
                'mail_google_smtp_adrress' => 'smtp-google-user',
            ] as $cmsConfigKey => $mailConfigKey
        ) {
            $config[$mailConfigKey] = config($cmsConfigKey, '');
        }
        if (defined('LICENSE_OPTION_OEM') && LICENSE_OPTION_OEM) {
            $config['additional_headers'] = 'X-Mailer: ' . LICENSE_OPTION_OEM;
        } else {
            $config['additional_headers'] = 'X-Mailer: a-blog cms';
        }
        $config['sendmail_path'] = (string)ini_get('sendmail_path');

        if (config('mail_additional_headers')) {
            $config['additional_headers']   .= "\x0D\x0A" . config('mail_additional_headers');
        }
        return $argConfig + $config;
    }

    /**
     * パスワードジェネレータ
     *
     * @param int $len パスワードの長さ
     *
     * @return string
     */
    public function genPass(int $len): string
    {
        if ($len < 3) {
            throw new RuntimeException('Length must be >= 3');
        }
        $byteLength = (int) ceil($len / 2); // 16進なので半分のバイト数
        assert($byteLength > 0); // PHPStan に 1以上と保証
        return substr(bin2hex(random_bytes($byteLength)), 0, $len);
    }

    /**
     * タグの配列化
     *
     * @param $string
     * @param bool $checkReserved
     * @return string[]
     */
    public function getTagsFromString($string, $checkReserved = true)
    {
        $tags = preg_split(TAG_SEPARATER, $string, -1, PREG_SPLIT_NO_EMPTY);
        $tags = array_map('trim', $tags);
        $tags = array_unique($tags);
        if ($checkReserved) {
            $tags = array_filter($tags, function ($tag) {
                return !isReserved($tag);
            });
        }
        return $tags;
    }

    /**
     * エントリーのフルテキストを取得
     *
     * @param int $eid
     *
     * @return string
     */
    public function loadEntryFulltext($eid)
    {
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);

        $text = $unitRepository->getUnitSearchText($eid);
        $entry = [
            'id' => $eid,
            'title' => ACMS_RAM::entryTitle($eid),
            'code' => ACMS_RAM::entryCode($eid),
        ];

        $sql = SQL::newSelect('field');
        $sql->addSelect('field_key');
        $sql->addSelect('field_value');
        $sql->addWhereOpr('field_search', 'on');
        $sql->addWhereOpr('field_eid', $eid);
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');

        $field = [];
        if ($statement && ($row = DB::next($statement))) {
            do {
                if (!isset($field[$row['field_key']])) {
                    $field[$row['field_key']] = [];
                }
                $field[$row['field_key']][] = $row['field_value'];
            } while ($row = DB::next($statement));
        }

        if (HOOK_ENABLE) {
            $hook = ACMS_Hook::singleton();
            $hook->call('filterEntryFulltext', [&$entry, &$field, $eid]);
        }

        $metaText = implode(
            ' ',
            [
                implode(' ', array_values($entry)),
                implode(' ', array_map(function (array $values) {
                    return implode(' ', $values);
                }, $field)),
            ]
        );

        $fulltext = preg_replace('/\s+/u', ' ', strip_tags($text))
        . "\x0d\x0a\x0a\x0d" . preg_replace('/\s+/u', ' ', strip_tags($metaText))
            ;
        return $fulltext;
    }

    /**
     * ユーザーのフルテキストを取得
     *
     * @param int $uid
     *
     * @return string
     */
    public function loadUserFulltext($uid)
    {
        $user = [
            'name' => ACMS_RAM::userName($uid),
            'code' => ACMS_RAM::userCode($uid),
            'mail' => ACMS_RAM::userMail($uid),
            'mail_mobile' => ACMS_RAM::userMailMobile($uid),
            'url' => ACMS_RAM::userUrl($uid),
        ];

        $SQL = SQL::newSelect('field');
        $SQL->addSelect('field_key');
        $SQL->addSelect('field_value');
        $SQL->addWhereOpr('field_search', 'on');
        $SQL->addWhereOpr('field_uid', $uid);
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');

        $field = [];
        if ($statement && ($row = DB::next($statement))) {
            do {
                if (!isset($field[$row['field_key']])) {
                    $field[$row['field_key']] = [];
                }
                $field[$row['field_key']][] = $row['field_value'];
            } while ($row = DB::next($statement));
        }

        if (HOOK_ENABLE) {
            $hook = ACMS_Hook::singleton();
            $hook->call('filterUserFulltext', [&$user, &$field, $uid]);
        }

        $userText = implode(' ', array_values($user));
        $metaText = implode(' ', array_map(function (array $values) {
            return implode(' ', $values);
        }, $field));

        $fulltext = preg_replace('/\s+/u', ' ', strip_tags($userText))
        . "\x0d\x0a\x0a\x0d" . preg_replace('/\s+/u', ' ', strip_tags($metaText))
            ;
        return $fulltext;
    }

    /**
     * カテゴリのフルテキストを取得
     *
     * @param int $cid
     *
     * @return string
     */
    public function loadCategoryFulltext($cid)
    {
        $category = [
            'name' => ACMS_RAM::categoryName($cid),
            'code' => ACMS_RAM::categoryCode($cid),
        ];

        $SQL = SQL::newSelect('field');
        $SQL->addSelect('field_key');
        $SQL->addSelect('field_value');
        $SQL->addWhereOpr('field_search', 'on');
        $SQL->addWhereOpr('field_cid', $cid);
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');

        $field = [];
        if ($statement && ($row = DB::next($statement))) {
            do {
                if (!isset($field[$row['field_key']])) {
                    $field[$row['field_key']] = [];
                }
                $field[$row['field_key']][] = $row['field_value'];
            } while ($row = DB::next($statement));
        }

        if (HOOK_ENABLE) {
            $hook = ACMS_Hook::singleton();
            $hook->call('filterCategoryFulltext', [&$category, &$field, $cid]);
        }

        $categoryText = implode(' ', array_values($category));
        $metaText = implode(' ', array_map(function (array $values) {
            return implode(' ', $values);
        }, $field));

        $fulltext = preg_replace('/\s+/u', ' ', strip_tags($categoryText))
        . "\x0d\x0a\x0a\x0d" . preg_replace('/\s+/u', ' ', strip_tags($metaText));
        return $fulltext;
    }

    /**
     * ブログのフルテキストを取得
     *
     * @param int $bid
     *
     * @return string
     */
    public function loadBlogFulltext($bid)
    {
        $blog = [
            'name' => ACMS_RAM::blogName($bid),
            'code' => ACMS_RAM::blogCode($bid),
            'domain' => ACMS_RAM::blogDomain($bid),
        ];

        $SQL = SQL::newSelect('field');
        $SQL->addSelect('field_key');
        $SQL->addSelect('field_value');
        $SQL->addWhereOpr('field_search', 'on');
        $SQL->addWhereOpr('field_bid', $bid);
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');

        $field = [];
        if ($statement && ($row = DB::next($statement))) {
            do {
                if (!isset($field[$row['field_key']])) {
                    $field[$row['field_key']] = [];
                }
                $field[$row['field_key']][] = $row['field_value'];
            } while ($row = DB::next($statement));
        }

        if (HOOK_ENABLE) {
            $hook = ACMS_Hook::singleton();
            $hook->call('filterBlogFulltext', [&$blog, &$field, $bid]);
        }

        $blogText = implode(' ', array_values($blog));
        $metaText = implode(' ', array_map(function (array $values) {
            return implode(' ', $values);
        }, $field));

        $fulltext = preg_replace('/\s+/u', ' ', strip_tags($blogText))
        . "\x0d\x0a\x0a\x0d" . preg_replace('/\s+/u', ' ', strip_tags($metaText));
        return $fulltext;
    }

    /**
     * フルテキストの保存
     *
     * @param string $type フルテキストのタイプ
     * @param int $id
     * @param string|null $fulltext
     * @param int $targetBid
     *
     * @return void
     */
    public function saveFulltext($type, $id, $fulltext = null, $targetBid = BID)
    {
        $SQL = SQL::newDelete('fulltext');
        $SQL->addWhereOpr('fulltext_' . $type, $id);
        DB::query($SQL->get(dsn()), 'exec');

        if ($fulltext !== null && $fulltext !== '') {
            $SQL    = SQL::newInsert('fulltext');
            $SQL->addInsert('fulltext_value', $fulltext);
            if (config('ngram')) {
                $SQL->addInsert(
                    'fulltext_ngram',
                    preg_replace('/(　|\s)+/u', ' ', join(' ', ngram(strip_tags($fulltext), config('ngram'))))
                );
            }
            $SQL->addInsert('fulltext_' . $type, $id);
            $SQL->addInsert('fulltext_blog_id', $targetBid);
            DB::query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * ファイルダウンロード
     *
     * @param string $path
     * @param string $fileName
     * @param string|false $extension // 指定すると、Content-Disposition: inline になります。
     * @param boolean $remove
     * @param \Acms\Services\Storage\Contracts\Filesystem $storage
     * @return never
     */
    public function download($path, $fileName, $extension = false, $remove = false, $storage = null)
    {
        $download = new Download();
        $download->handle($path, $fileName, $extension, $remove, $storage);
    }

    /**
     * カスタムフィールドキャッシュの削除
     *
     * @param 'bid'|'uid'|'cid'|'mid'|'eid'|'unit_id' $type
     * @param ($type is 'unit_id' ? string : int) $id
     * @param int|null $rvid
     */
    public function deleteFieldCache($type, $id, $rvid = null)
    {
        // キャッシュ削除
        if ($type) {
            $cacheBid = $type === 'bid' ? $id : '';
            $cacheUid = $type === 'uid' ? $id : '';
            $cacheCid = $type === 'cid' ? $id : '';
            $cacheMid = $type === 'mid' ? $id : '';
            $cacheEid = $type === 'eid' ? $id : '';
            $cacheUnitId = $type === 'unit_id' ? $id : '';
        }
        $cacheKey = "cache-field-bid_{$cacheBid}-uid_{$cacheUid}-cid_{$cacheCid}-mid_{$cacheMid}-eid_{$cacheEid}-unitId_{$cacheUnitId}-rvid_{$rvid}-";

        $this->cacheField->forget("{$cacheKey}0-v1");
        $this->cacheField->forget("{$cacheKey}1-v1");
        $this->cacheField->forget("{$cacheKey}0-v2");
        $this->cacheField->forget("{$cacheKey}1-v2");
    }

    public function flushCache()
    {
        $this->cacheField->flush();
    }

    /**
     * カスタムフィールドの削除
     *
     * @param 'bid'|'uid'|'cid'|'mid'|'eid'|'unit_id' $type
     * @param ($type is 'unit_id' ? string : int) $id
     * @param int|null $rvid
     * @param int|null $blogId
     * @return void
     */
    public function deleteField($type, $id, $rvid = null, $blogId = null)
    {
        $this->deleteFieldCache($type, $id, $rvid);

        if (in_array($type, ['eid', 'unit_id'], true) && $rvid) {
            $sql = SQL::newDelete('field_rev');
            $sql->addWhereOpr('field_eid', $id);
            $sql->addWhereOpr('field_rev_id', $rvid);
            if ($blogId !== null) {
                $sql->addWhereOpr('field_blog_id', $blogId);
            }
            DB::query($sql->get(dsn()), 'exec');
        } else {
            $sql = SQL::newDelete('field');
            $sql->addWhereOpr('field_' . $type, $id);
            if ($blogId !== null) {
                $sql->addWhereOpr('field_blog_id', $blogId);
            }
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    /**
     * ブログID, カテゴリーID, エントリーID，ユーザーIDの
     * いずれか指定されたカスタムフィールドをFieldオブジェクトで返す
     *
     * @param null|int $bid
     * @param null|int $uid
     * @param null|int $cid
     * @param null|int $mid
     * @param null|int $eid
     * @param null|string $unitId
     * @param null|int $rvid
     * @param bool $rewrite
     * @return Field
     */
    public function loadField($bid = null, $uid = null, $cid = null, $mid = null, $eid = null, $unitId = null, $rvid = null, $rewrite = false)
    {
        $cacheKey = "cache-field-bid_{$bid}-uid_{$uid}-cid_{$cid}-mid_{$mid}-eid_{$eid}-unitId_{$unitId}-rvid_{$rvid}-";
        $cacheKey .= ($rewrite ? '1' : '0');
        $cacheKey .= (isApiBuildOrV2Module() ? '-v2' : '-v1');

        $cacheItem = $this->cacheField->getItem($cacheKey);
        if ($cacheItem && $cacheItem->isHit()) {
            $cacheData = $cacheItem->get();
            if ($cacheData instanceof Field) {
                Tpl::injectMediaField($cacheData, force: true);
                return $cacheData;
            }
            $this->cacheField->forget($cacheKey);
        }
        $field = new Field();
        if (
            is_null($bid) &&
            is_null($uid) &&
            is_null($cid) &&
            is_null($eid) &&
            is_null($mid) &&
            is_null($unitId)
        ) {
            return $field;
        }
        if ($rvid && ($eid || $unitId)) {
            $SQL = SQL::newSelect('field_rev');
            $SQL->addWhereOpr('field_rev_id', $rvid);
        } else {
            $SQL = SQL::newSelect('field');
        }
        $SQL->addSelect('field_key');
        $SQL->addSelect('field_value');
        $SQL->addSelect('field_type');
        $SQL->addSelect('field_search');
        if (!is_null($bid)) {
            $SQL->addWhereOpr('field_bid', $bid);
        }
        if (!is_null($uid)) {
            $SQL->addWhereOpr('field_uid', $uid);
        }
        if (!is_null($cid)) {
            $SQL->addWhereOpr('field_cid', $cid);
        }
        if (!is_null($eid)) {
            $SQL->addWhereOpr('field_eid', $eid);
        }
        if (!is_null($mid)) {
            $SQL->addWhereOpr('field_mid', $mid);
        }
        if (!is_null($unitId)) {
            $SQL->addWhereOpr('field_unit_id', $unitId);
        }
        $SQL->setOrder('field_sort');
        $q  = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');

        while ($row = DB::next($statement)) {
            $fd = $row['field_key'];
            $field->addField($fd, $row['field_value']);
            $field->setMeta($fd, 'search', $row['field_search'] === 'on');
            $field->setMeta($fd, 'type', $row['field_type']);
        }
        $cacheItem->set($field);
        $this->cacheField->putItem($cacheItem);
        Tpl::injectMediaField($field, force: true);

        return $field;
    }

    /**
     * グループフィールド行削除で孤児化する @path のリストを収集する。
     *
     * saveField() の DB DELETE 前に呼び出す。DB に残っている @path 値のうち、
     * 今回保存しようとしている Field に含まれていないものを「削除された行」とみなし、
     * パスと image/file の種別をペアで返す。
     *
     * 実際の物理削除は DELETE → INSERT 完了後に removeUnreferencedFieldFiles() で行う。
     *
     * @param string $tableName 'field' | 'field_rev'
     * @param string $type 'bid'|'uid'|'cid'|'mid'|'eid'|'unit_id'
     * @param int|string $id
     * @param Field|null $field 保存しようとしている新 Field
     * @param int|null $rvid リビジョンID（field_rev テーブルの場合のみ使用）
     * @return list<array{path: string, type: 'image'|'file'}> 削除候補パスのリスト
     */
    private function collectOrphanedFieldPaths(string $tableName, string $type, $id, $field, ?int $rvid = null): array
    {
        // DB 側の既存値を field_key ごとに収集
        $SQL = SQL::newSelect($tableName);
        $SQL->addSelect('field_key');
        $SQL->addSelect('field_value');
        $SQL->addWhereOpr('field_' . $type, $id);
        if ($tableName === 'field_rev') {
            $SQL->addWhereOpr('field_rev_id', $rvid);
        }
        // updateField 指定時は該当キーのみ対象
        if ($field instanceof Field && $field->get('updateField') === 'on') {
            $fkey = [];
            foreach ($field->listFields() as $fd) {
                if ($fd === 'updateField') {
                    continue;
                }
                $fkey[] = $fd;
            }
            if ($fkey === []) {
                return [];
            }
            $SQL->addWhereIn('field_key', $fkey);
        }
        $stmt = DB::query($SQL->get(dsn()), 'exec');

        /** @var array<string, string[]> */
        $dbValuesByKey = [];
        while ($row = DB::next($stmt)) {
            $dbValuesByKey[$row['field_key']][] = (string)$row['field_value'];
        }

        $candidates = [];
        foreach ($dbValuesByKey as $fd => $dbValues) {
            if (!str_ends_with($fd, '@path')) {
                continue;
            }
            $postValues = $field instanceof Field ? array_map('strval', $field->getArray($fd, true)) : [];
            $removedPaths = GroupFieldDiffCalculator::calculateRemovedRows($dbValues, $postValues);
            if ($removedPaths === []) {
                continue;
            }

            // image フィールドか file フィールドかを判定
            // 同一 base の @largePath キーが DB にあれば image 扱い
            $base = substr($fd, 0, -strlen('@path'));
            $fieldType = array_key_exists($base . '@largePath', $dbValuesByKey) ? 'image' : 'file';

            foreach ($removedPaths as $relPath) {
                if ($relPath === '') {
                    continue;
                }
                $candidates[] = ['path' => $relPath, 'type' => $fieldType];
            }
        }
        return $candidates;
    }

    /**
     * 削除候補パスのうち、field / field_rev テーブルのどこからも参照されていないものを物理削除する。
     *
     * saveField() の DELETE → INSERT 完了後に呼び出す。
     * DB 更新後なので自分自身の除外条件は不要。
     *
     * @param list<array{path: string, type: 'image'|'file'}> $candidates collectOrphanedFieldPaths() と collectMarkedFieldFiles() をマージした候補リスト
     */
    private function removeUnreferencedFieldFiles(array $candidates): void
    {
        foreach ($candidates as $candidate) {
            $relPath = $candidate['path'];
            if ($this->isReferencedByField($relPath)) {
                continue;
            }
            $this->deletePhysicalFieldFile($relPath, $candidate['type']);
        }
    }

    /**
     * 削除チェック・差し替え時に旧ファイルを saveField() 後に物理削除するよう Field にマーキングする。
     *
     * extract() 内では即時削除せず、saveField() の DB 書き込み完了後に
     * removeMarkedFieldFiles() で参照チェックを通してから削除する。
     *
     * @param Field $field extract 中の Field
     * @param string $fieldKey image/file フィールドの key（例: 'photo'）
     * @param string $relPath archives ディレクトリからの相対パス
     * @param 'image'|'file' $type
     */
    private function markFieldFileForRemoval(Field $field, string $fieldKey, string $relPath, string $type): void
    {
        if ($relPath === '') {
            return;
        }
        $removeList = $field->getMeta($fieldKey, 'removeOld');
        if (!is_array($removeList)) {
            $removeList = [];
        }
        $removeList[] = ['path' => $relPath, 'type' => $type];
        $field->setMeta($fieldKey, 'removeOld', $removeList);
    }

    /**
     * Field のメタ情報 'removeOld' に積まれた削除予約を収集する。
     *
     * extract() 内で markFieldFileForRemoval() によって積まれた削除候補を
     * removeUnreferencedFieldFiles() と同じ形式のリストに変換して返す。
     *
     * @param Field|null $field
     * @return list<array{path: string, type: 'image'|'file'}>
     */
    private function collectMarkedFieldFiles($field): array
    {
        if (!$field instanceof Field) {
            return [];
        }
        $candidates = [];
        foreach ($field->listFields() as $fd) {
            $marks = $field->getMeta($fd, 'removeOld');
            if (!is_array($marks) || $marks === []) {
                continue;
            }
            foreach ($marks as $mark) {
                if (!is_array($mark)) {
                    continue;
                }
                $relPath = isset($mark['path']) ? (string)$mark['path'] : '';
                $type = $mark['type'] ?? null;
                if ($relPath === '' || ($type !== 'image' && $type !== 'file')) {
                    continue;
                }
                $candidates[] = ['path' => $relPath, 'type' => $type];
            }
        }
        return $candidates;
    }

    /**
     * カスタムフィールド image/file 型のファイルを物理削除する。
     *
     * @param string $relPath archives ディレクトリからの相対パス
     * @param 'image'|'file' $type
     */
    private function deletePhysicalFieldFile(string $relPath, string $type): void
    {
        if ($relPath === '') {
            return;
        }
        if ($type === 'image') {
            Image::deleteImageAllSize(ARCHIVES_DIR . normalSizeImagePath($relPath));
            return;
        }
        $target = ARCHIVES_DIR . $relPath;
        PublicStorage::remove($target);
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaDelete', $target);
        }
    }

    /**
     * 指定パスが field / field_rev テーブルのいずれかのレコードで使われているか判定する。
     *
     * @param string $path 検査するファイルパス（相対パス）
     * @return bool いずれかのレコードが参照していれば true
     */
    private function isReferencedByField(string $path): bool
    {
        $sql = SQL::newSelect('field');
        $sql->setSelect('field_value');
        $sql->addWhereOpr('field_value', $path);
        $sql->setLimit(1);
        if (DB::query($sql->get(dsn()), 'one')) {
            return true;
        }

        $sql = SQL::newSelect('field_rev');
        $sql->setSelect('field_value');
        $sql->addWhereOpr('field_value', $path);
        $sql->setLimit(1);
        if (DB::query($sql->get(dsn()), 'one')) {
            return true;
        }

        return false;
    }

    /**
     * カスタムフィールドの保存
     *
     * @param 'bid'|'uid'|'cid'|'mid'|'eid'|'unit_id' $type
     * @param ($type is 'unit_id' ? string : int) $id
     * @param Field|null $field
     * @param Field|null $deleteField
     * @param int|null $rvid
     * @param int $targetBid
     *
     * @return bool
     */
    public function saveField($type, $id, $field = null, $deleteField = null, $rvid = null, $targetBid = BID)
    {
        if ($id === '' || $id < 1) {
            AcmsLogger::warning('idが空で、フィールドを保存できませんでした', [
                'type' => $type,
                'bid' => $targetBid,
            ]);
            return false;
        }

        $this->deleteFieldCache($type, $id, $rvid);

        $ARCHIVES_DIR_TO = ARCHIVES_DIR;
        $tableName = 'field';
        $asNewVersion = false;

        if (
            1
            && enableRevision()
            && $rvid
            && in_array($type, ['eid', 'unit_id'], true)
        ) {
            $tableName = 'field_rev';
            if (Entry::isNewVersion()) {
                $asNewVersion = true;
            }
        }

        // DELETE 前に、このトランザクションで孤児化するファイルパスを収集する
        // 実際の物理削除は DELETE → INSERT 完了後に行う（参照チェックで自分自身の除外が不要になるため）
        $orphanedPaths = $this->collectOrphanedFieldPaths($tableName, $type, $id, $field, $rvid);

        $SQL = SQL::newDelete($tableName);
        $SQL->addWhereOpr('field_' . $type, $id);
        if ($tableName  === 'field_rev') {
            $SQL->addWhereOpr('field_rev_id', $rvid);
        }
        if ($field && $field->get('updateField') === 'on') {
            $fkey   = [];
            $field->delete('updateField');
            foreach ($field->listFields() as $fd) {
                $fkey[] = $fd;
            }
            $SQL->addWhereIn('field_key', $fkey);
        }
        DB::query($SQL->get(dsn()), 'exec');

        if ($field instanceof Field) {
            $sql = SQL::newBulkInsert($tableName);
            $sql->addColumn('field_key');
            $sql->addColumn('field_value');
            $sql->addColumn('field_type');
            $sql->addColumn('field_sort');
            $sql->addColumn('field_search');
            $sql->addColumn('field_' . $type);
            $sql->addColumn('field_blog_id');
            if ($tableName  === 'field_rev') {
                $sql->addColumn('field_rev_id');
            }
            foreach ($field->listFields() as $fd) {
                // copy revision
                if ($asNewVersion) {
                    if (strpos($fd, '@path')) {
                        $list         = $field->getArray($fd, true);
                        $base         = substr($fd, 0, (-1 * strlen('@path')));
                        $currentLarge = $field->getArray($base . '@largePath', true);
                        $currentTiny  = $field->getArray($base . '@tinyPath', true);
                        $currentSquare = $field->getArray($base . '@squarePath', true);
                        $newPaths        = [];
                        $newLargePaths   = [];
                        $newTinyPaths    = [];
                        $newSquarePaths  = [];
                        $needRebuild = false;
                        foreach ($list as $i => $val) {
                            $path = $val;
                            if (in_array($path, Entry::getUploadedFiles(), true)) {
                                // 今回のリクエストでアップロードされたファイルはコピー不要、そのまま維持
                                $newPaths[]       = $path;
                                $newLargePaths[]  = $currentLarge[$i] ?? '';
                                $newTinyPaths[]   = $currentTiny[$i] ?? '';
                                $newSquarePaths[] = $currentSquare[$i] ?? '';
                                continue;
                            }
                            $needRebuild = true;
                            if (PublicStorage::isFile(ARCHIVES_DIR . $path)) {
                                $info       = pathinfo($path);
                                $dirname    = ($info['dirname'] ?? '') === '' ? '' : $info['dirname'] . '/';
                                PublicStorage::makeDirectory($ARCHIVES_DIR_TO . $dirname);
                                $ext        = ($info['extension'] ?? '') === '' ? '' : '.' . $info['extension'];
                                $newPath    = $dirname . uniqueString() . $ext;

                                $path       = ARCHIVES_DIR . $path;
                                $largePath  = otherSizeImagePath($path, 'large');
                                $tinyPath   = otherSizeImagePath($path, 'tiny');
                                $squarePath = otherSizeImagePath($path, 'square');

                                $newLargePath   = otherSizeImagePath($newPath, 'large');
                                $newTinyPath    = otherSizeImagePath($newPath, 'tiny');
                                $newSquarePath  = otherSizeImagePath($newPath, 'square');

                                PublicStorage::copy($path, $ARCHIVES_DIR_TO . $newPath);
                                PublicStorage::copy($largePath, $ARCHIVES_DIR_TO . $newLargePath);
                                PublicStorage::copy($tinyPath, $ARCHIVES_DIR_TO . $newTinyPath);
                                PublicStorage::copy($squarePath, $ARCHIVES_DIR_TO . $newSquarePath);

                                if (!PublicStorage::isReadable($newLargePath)) {
                                    $newLargePath = '';
                                }
                                if (!PublicStorage::isReadable($newTinyPath)) {
                                    $newTinyPath = '';
                                }
                                if (!PublicStorage::isReadable($newSquarePath)) {
                                    $newSquarePath = '';
                                }
                                $newPaths[]       = $newPath;
                                $newLargePaths[]  = $newLargePath;
                                $newTinyPaths[]   = $newTinyPath;
                                $newSquarePaths[] = $newSquarePath;
                            } else {
                                $newPaths[]       = '';
                                $newLargePaths[]  = '';
                                $newTinyPaths[]   = '';
                                $newSquarePaths[] = '';
                            }
                        }
                        if ($needRebuild) {
                            // 既存パスをコピーしたときだけ、@path と関連バリエーションをまとめて差し替える
                            $field->delete($fd);
                            $field->delete($base . '@largePath');
                            $field->delete($base . '@tinyPath');
                            $field->delete($base . '@squarePath');
                            foreach ($newPaths as $v) {
                                $field->add($fd, $v);
                            }
                            foreach ($newLargePaths as $v) {
                                $field->add($base . '@largePath', $v);
                            }
                            foreach ($newTinyPaths as $v) {
                                $field->add($base . '@tinyPath', $v);
                            }
                            foreach ($newSquarePaths as $v) {
                                $field->add($base . '@squarePath', $v);
                            }
                        }
                    }
                }
                foreach ($field->getArray($fd, true) as $i => $val) {
                    $fieldTypeValue = null;
                    if (preg_match('/@(html|media|title)$/', $fd, $match)) {
                        $fieldTypeValue = $match[1];
                    }
                    if ($fieldType = $field->getMeta($fd, 'type')) {
                        $fieldTypeValue = $fieldType;
                    }
                    $data = [
                        'field_key' => $fd,
                        'field_value' => $val,
                        'field_type' => $fieldTypeValue,
                        'field_sort' => $i + 1,
                        'field_search' => $field->getMeta($fd, 'search') ? 'on' : 'off',
                        'field_' . $type => $id,
                        'field_blog_id' => $targetBid,
                    ];
                    if ($tableName  === 'field_rev') {
                        $data['field_rev_id'] = $rvid;
                    }
                    $sql->addInsert($data);
                }
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }

        // DELETE → INSERT 完了後に、どこからも参照されなくなったファイルを物理削除する
        // 行削除由来（collectOrphanedFieldPaths）と削除チェック・差し替え由来（collectMarkedFieldFiles）を合算して一括処理する
        $allCandidates = array_merge($orphanedPaths, $this->collectMarkedFieldFiles($field));
        if ($allCandidates !== []) {
            $this->removeUnreferencedFieldFiles($allCandidates);
        }

        return true;
    }

    /**
     * URIオブジェクトの取得
     *
     * @param \Field $Post
     *
     * @return \Field
     */
    public function getUriObject(Field $Post)
    {
        $Uri = new Field();

        if (!$aryFd = $Post->getArray('arg')) {
            // argがない場合は、fieldとqueryを除いた全てのフィールドをURIオブジェクトにセットする
            $aryFd = array_diff($Post->listFields(), $Post->getArray('field'), $Post->getArray('query'));
        }
        foreach ($aryFd as $fd) {
            if ($fd === 'field' && $Post->getArray('field')) {
                // field
                $fieldSearch = Field_Search::fromPost($Post);
                $Uri->addChild('field', $fieldSearch);
            } elseif ($fd === 'query' && $aryQuery = $Post->getArray('query')) {
                // query
                $Query  = new Field();
                foreach ($aryQuery as $query) {
                    $Query->set($query, $Post->getArray($query));
                }
                $Uri->addChild('query', $Query);
            } else {
                // その他のフィールド
                $Uri->set($fd, $Post->getArray($fd));
            }
        }
        return $Uri;
    }

    /**
     * POSTデータからデータの抜き出し
     *
     * image/file カスタムフィールドで旧ファイルを消す操作（削除チェック・差し替え）があった場合、
     * 物理削除は即時には行わず Field のメタ情報 'removeOld' に記録する。
     * 実際の物理削除はバリデーション通過後に呼ばれる saveField() 内で参照チェック
     * （field / field_rev の両テーブルを検索）の上で行う。
     * よって旧ファイルを残すべきかどうか（新バージョン保存・作業領域のみの保存）は
     * 呼び出し元が判定する必要はなく、参照チェックが自動的に判断する。
     *
     * @param string $scp
     * @param \ACMS_Validator|null $V
     * @param \Field|null $deleteField
     * @return \Field_Validation
     */
    public function extract($scp = 'field', $V = null, $deleteField = null)
    {
        $field = new Field_Validation();
        $this->deleteField = $deleteField;

        $ARCHIVES_DIR = ARCHIVES_DIR;

        if (!$this->deleteField) {
            $this->deleteField = new Field();
        }

        if ($takeover = $this->Post->get($scp . ':takeover')) {
            $takeoverField = acmsUnserialize($takeover);
            if ($takeoverField instanceof Field) {
                $field->overload($takeoverField);
            }
            $this->Post->delete($scp . ':takeover');
        }

        $field->overload($this->Post->dig($scp));
        $this->Post->addChild($scp, $field);

        // 許可ファイル拡張子をまとめておく
        $allow_file_extensions = array_merge(
            configArray('file_extension_document'),
            configArray('file_extension_archive'),
            configArray('file_extension_movie'),
            configArray('file_extension_audio')
        );

        //-------
        // child
        foreach ($field->listFields() as $fd) {
            if (!$this->Post->isExists($fd . ':field')) {
                continue;
            }
            $this->Post->set($fd, $field->getArray($fd));
            $field->delete($fd);
            $field->addChild($fd, $this->extract($fd));
        }

        // アップロード処理中の画像・ファイルを保存する変数
        // アップロード処理中のファイルが誤って削除されることを防ぐために利用
        $processingMediaFiles = [];
        foreach ($this->Post->listFields() as $metaFd) {
            //-----------
            // converter
            if (
                1
                and preg_match('@^(.+)(?:\:c|\:converter)$@', $metaFd, $match)
                and $field->isExists($match[1])
            ) {
                $fd = $match[1];
                $aryVal = [];
                foreach ($field->getArray($fd) as $val) {
                    $mode = $this->Post->get($metaFd);
                    if (preg_match('/^[rRnNaAsSkKhHcCV]+$/', $mode)) {
                        $aryVal[] = mb_convert_kana($val, $mode, 'UTF-8');
                    } else {
                        AcmsLogger::warning('converterのモードが不正です', [
                            'field' => $fd,
                            'value' => $val,
                            'mode' => $mode,
                        ]);
                        $aryVal[] = '';
                    }
                }
                $field->setField($fd, $aryVal);
                $this->Post->delete($metaFd);
                continue;
            }
            //-----------
            // extension
            if (
                1
                and preg_match('@^(.+):extension$@', $metaFd, $match)
                and $field->isExists($match[1])
            ) {
                $fd         = $match[1];
                $type       = $this->Post->get($fd . ':extension');
                $dataUrl    = false;
                $this->Post->delete($fd . ':extension');

                if ($type === 'media') {
                    foreach ($field->getArray($fd) as $mediaValue) {
                        $field->addField($fd . '@media', $mediaValue);
                    }
                } elseif ($type === 'block-editor') {
                    $field->setMeta($fd, 'type', 'block-editor');
                } elseif ($type === 'paper-editor' || $type === 'rich-editor') {
                    foreach ($field->getArray($fd) as $editorValue) {
                        $field->addField($fd . '@html', RichEditor::render($editorValue));
                        $field->addField($fd . '@title', RichEditor::renderTitle($editorValue));
                    }
                } elseif ($type === 'image' || $type === 'file') {
                    try {
                        $file = ACMS_Http::file($fd);
                        if ($type === 'file') {
                            if ($extensions = $this->Post->getArray($fd . '@extension')) {
                                if (!$this->mimeTypeValidator->validateAllowedByContent($file->getPath(), $extensions)) {
                                    throw new \RuntimeException('EXTENSION_IS_DIFFERENT');
                                }
                            }
                        }
                        $size = $file->getFileSize();
                        if (isset($field->_aryMethod[$fd])) {
                            $arg = $field->_aryMethod[$fd];
                            if (isset($arg['filesize'])) {
                                $maxsize = intval($arg['filesize']);
                                if ($size > ($maxsize * 1024)) {
                                    throw new \RuntimeException(UPLOAD_ERR_FORM_SIZE);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        if ($e->getMessage() == 'EXTENSION_IS_DIFFERENT') {
                            $field->setMethod($fd, 'extension', false);
                            continue;
                        }
                        if ($e->getMessage() == UPLOAD_ERR_INI_SIZE || $e->getMessage() == UPLOAD_ERR_FORM_SIZE) {
                            $field->setMethod($fd, 'filesize', false);
                            $field->set($fd, 'maxfilesize');
                            continue;
                        }
                    }
                }

                //-------
                // image
                if ('image' == $type) {
                    // data url
                    if (isset($_POST[$fd])) {
                        ACMS_POST_Image::base64DataToImage($_POST[$fd], $fd);
                        $field->delete($fd);
                        $dataUrl = true;
                    }

                    if (empty($_FILES[$fd])) {
                        foreach (
                            [
                                'path', 'x', 'y', 'alt', 'fileSize',
                                'largePath', 'largeX', 'largeY', 'largeAlt', 'largeFileSize',
                                'tinyPath', 'tinyX', 'tinyY', 'tinyAlt', 'tinyFileSize',
                                'squarePath', 'squareX', 'squareY', 'squareAlt', 'squareFileSize',
                                'secret'
                            ] as $key
                        ) {
                            $key    = $fd . '@' . $key;
                            $this->deleteField->set($key, []);
                            $field->deleteField($fd . '@' . $key);
                        }
                        continue;
                    }

                    $aryC   = [];
                    if (!is_array($_FILES[$fd]['tmp_name'])) {
                        $aryC[] = [
                            '_tmp_name' => $_FILES[$fd]['tmp_name'],
                            '_name'     => $_FILES[$fd]['name'],
                        ];
                    } else {
                        foreach ($_FILES[$fd]['tmp_name'] as $i => $tmp_name) {
                            $aryC[] = [
                                '_tmp_name' => $tmp_name,
                                '_name'     => $_FILES[$fd]['name'][$i],
                            ];
                        }
                    }

                    foreach (
                        [
                            'str'   => ['old', 'edit', 'alt', 'filename', 'extension', 'secret'],
                            'int'   => [
                                'width', 'height', 'size',
                                'tinyWidth', 'tinyHeight', 'tinySize',
                                'largeWidth', 'largeHeight', 'largeSize',
                                'squareWidth', 'squareHeight', 'squareSize',
                            ],
                        ] as $_type => $keys
                    ) {
                        foreach ($keys as $key) {
                            foreach ($aryC as $i => $c) {
                                $_field = $fd . '@' . $key;
                                $value  = $this->Post->isExists($_field, $i) ?
                                    $this->Post->get($_field, '', $i) : '';
                                $c[$key]    = ('int' == $_type) ? intval($value) : strval($value);
                                $aryC[$i]   = $c;
                            }
                            $this->Post->delete($fd . '@' . $key);
                        }
                    }

                    $aryData    = [];
                    foreach ($aryC as $c) {
                        $aryData[]  = [];
                    }
                    $cnt    = count($aryData);
                    for ($i = 0; $i < $cnt; $i++) {
                        $c          = $aryC[$i];
                        $data       =& $aryData[$i];

                        //-------------
                        // rawfilename
                        if (preg_match('/^@(.*)$/', $c['filename'], $match)) {
                            $c['filename']  = ('rawfilename' == $match[1]) ? date('Ym') . '/' . $c['_name'] : '';
                        }

                        //------------------------------------
                        // security check ( nullバイトチェック )
                        if ($c['old']      !== ltrim($c['old'])) {
                            continue;
                        }
                        if ($c['filename'] !== ltrim($c['filename'])) {
                            continue;
                        }

                        //-------------------------------------------------------------
                        // パスの半正規化 ( directory traversal対策・バイナリセーフ関数を使用 )
                        // この時点で //+ や ^/ は 混入する可能性はあるが無害とみなす
                        $c['old']      = preg_replace('/\.+\/+/', '', $c['old']);
                        $c['filename'] = preg_replace('/\.+\/+/', '', $c['filename']);

                        //---------------------------------------------
                        // 例外的無視ファイル
                        // pathの終端（ファイル名）が特定の場合にリジェクトする
                        if (!!preg_match('/\.htaccess$/', $c['filename'])) {
                            continue;
                        }

                        // アップロード処理中のファイルかどうか
                        $isProcessing = false;
                        foreach ($processingMediaFiles as $media) {
                            if ($media['path'] === $ARCHIVES_DIR . $c['old']) {
                                $isProcessing = true;
                                break;
                            }
                        }
                        //---------------------
                        // セキュリティチェック
                        // リクエストされた削除ファイル名が怪しい場合に削除と上書きをスキップ
                        // このチェックに引っかかった場合にもフィールドの情報は保持する(continueしない)
                        // 削除キーがDBに保存されていなかった場合などファイルが消せなくなるため
                        // 投稿者以上の権限を持っている場合にもチェックを行わない
                        // 暗号化は「フィールド名@パス」をmd5したもの
                        // 暗号化文字列の照合にDBは使えない
                        // 一回目にフォームを送信するときはDB上にデータがない
                        // アップロードが完了したにもかかわらず
                        // 他のエラーチェックで引っかかった時は
                        // DB上にデータは保存されないため比較できない
                        $secretCheck = ( 1
                            && !sessionWithSubscription()
                            && !empty($c['old'])
                            && ( 0
                                or 'delete' == $c['edit']
                                or !empty($c['_tmp_name'])
                            )
                        ) ? ($c['secret'] == md5($fd . '@' . $c['old'])) : true;

                        //----------------------------
                        // delete ( 指定削除 continue )
                        if (
                            1
                            && 'delete' == $c['edit']
                            && !empty($c['old'])
                            && $secretCheck
                            && !$isProcessing
                            && isExistsRuleModuleConfig()
                        ) {
                            $this->markFieldFileForRemoval($field, $fd, $c['old'], 'image');
                            continue;
                        }

                        //--------
                        // upload
                        if (!empty($c['_tmp_name']) and $secretCheck) {
                            $tmp_name   = $c['_tmp_name'];
                            if (!$dataUrl && !is_uploaded_file($tmp_name)) {
                                continue;
                            }
                            // getimagesizeが画像ファイルであるかの判定を兼用している
                            // @todo security:
                            // "GIF89a <SCRIPT>alert('xss');< /SCRIPT>のようなテキストファイルはgetimagesizeを通過する
                            // IE6, 7あたりはContent-Typeのほかにファイルの中身も評価してしまう
                            // 偽装テキストを読み込んだときに、HTML with JavaScriptとして実行されてしまう可能性がある
                            // 参考: http://www.tokumaru.org/d/20071210.html
                            if (!($xy = LocalStorage::getImageSize($tmp_name))) {
                                continue;
                            }

                            //---------------------------
                            // delete ( 古いファイルの削除 )
                            if (
                                !empty($c['old']) &&
                                !$isProcessing &&
                                isExistsRuleModuleConfig()
                            ) {
                                $this->markFieldFileForRemoval($field, $fd, $c['old'], 'image');
                            }

                            //------------------------------
                            // dirname, basename, extension
                            if (!empty($c['filename'])) {
                                if (!preg_match('@((?:[^/]*/)*)((?:[^.]*\.)*)(.*)$@', sprintf('%03d', BID) . '/' . $c['filename'], $match)) {
                                    throw new \RuntimeException('アップロードファイルのパス解析に失敗しました。');
                                }

                                $extension  = !empty($match[3]) ? $match[3]
                                                                : Image::detectImageExtenstion($xy['mime']);
                                $dirname    = $match[1];
                                $basename   = !empty($match[2]) ? $match[2] . $extension
                                                                : uniqueString() . '.' . $extension;
                            } else {
                                $extension = !empty($c['extension'])
                                    ? $c['extension'] : Image::detectImageExtenstion($xy['mime']);
                                $dirname    = PublicStorage::archivesDir();
                                $basename   = uniqueString() . '.' . $extension;
                            }

                            //-------
                            // angle
                            $angle  = 0;
                            if ('rotate' == substr($c['edit'], 0, 6)) {
                                $angle  = intval(substr($c['edit'], 6));
                            }

                            //--------
                            // normal
                            $normal     = $dirname . $basename;
                            $normalPath = $ARCHIVES_DIR . $normal;

                            // ファイル名が重複している場合はファイル名を変更する
                            $normalPath = PublicStorage::uniqueFilePath($normalPath);
                            $normal = mb_substr($normalPath, strlen($ARCHIVES_DIR));
                            $basename = PublicStorage::mbBasename($normalPath);

                            Image::copyImage($tmp_name, $normalPath, $c['width'], $c['height'], $c['size'], $angle);

                            if ($xy = PublicStorage::getImageSize($normalPath)) {
                                $data[$fd . '@path']  = $normal;
                                $data[$fd . '@x']     = $xy[0];
                                $data[$fd . '@y']     = $xy[1];
                                $data[$fd . '@alt']   = $c['alt'];
                                $data[$fd . '@fileSize'] = PublicStorage::getFileSize($normalPath);

                                $processingMediaFiles[] = [
                                    'path'  => $normalPath,
                                ];
                                Entry::addUploadedFiles($normal); // 新規バージョンとして作成する時にファイルをCOPYするかの判定に利用
                            }

                            //-------
                            // large
                            if (!empty($c['largeWidth']) or !empty($c['largeHeight']) or !empty($c['largeSize'])) {
                                $large     = $dirname . 'large-' . $basename;
                                $largePath = $ARCHIVES_DIR . $large;
                                if (!PublicStorage::exists($largePath)) {
                                    Image::copyImage($tmp_name, $largePath, $c['largeWidth'], $c['largeHeight'], $c['largeSize'], $angle);
                                }
                                if ($xy = PublicStorage::getImageSize($largePath)) {
                                    $data[$fd . '@largePath'] = $large;
                                    $data[$fd . '@largeX']    = $xy[0];
                                    $data[$fd . '@largeY']    = $xy[1];
                                    $data[$fd . '@largeAlt']  = $c['alt'];
                                    $data[$fd . '@largeFileSize']  = PublicStorage::getFileSize($largePath);

                                    $processingMediaFiles[] = [
                                        'path'  => $normalPath,
                                    ];
                                }
                            }

                            //------
                            // tiny
                            if (!empty($c['tinyWidth']) or !empty($c['tinyHeight']) or !empty($c['tinySize'])) {
                                $tiny     = $dirname . 'tiny-' . $basename;
                                $tinyPath = $ARCHIVES_DIR . $tiny;
                                if (!PublicStorage::exists($tinyPath)) {
                                    Image::copyImage($tmp_name, $tinyPath, $c['tinyWidth'], $c['tinyHeight'], $c['tinySize'], $angle);
                                }
                                if ($xy = PublicStorage::getImageSize($tinyPath)) {
                                    $data[$fd . '@tinyPath']  = $tiny;
                                    $data[$fd . '@tinyX']     = $xy[0];
                                    $data[$fd . '@tinyY']     = $xy[1];
                                    $data[$fd . '@tinyAlt']   = $c['alt'];
                                    $data[$fd . '@tinyFileSize']  = PublicStorage::getFileSize($tinyPath);

                                    $processingMediaFiles[] = [
                                        'path'  => $normalPath,
                                    ];
                                }
                            }

                            //---------
                            // square
                            if (!empty($c['squareWidth']) or !empty($c['squareHeight']) or !empty($c['squareSize'])) {
                                $square   = $dirname . 'square-' . $basename;
                                $squarePath = $ARCHIVES_DIR . $square;
                                $squareSize = 0;
                                if (!empty($c['squareWidth'])) {
                                    $squareSize = $c['squareWidth'];
                                } elseif (!empty($c['squareHeight'])) {
                                    $squareSize = $c['squareHeight'];
                                } elseif (!empty($c['squareSize'])) {
                                    $squareSize = $c['squareSize'];
                                }

                                if (!PublicStorage::exists($squarePath)) {
                                    Image::copyImage($tmp_name, $squarePath, $squareSize, $squareSize, $squareSize, $angle);
                                }
                                if ($xy = PublicStorage::getImageSize($squarePath)) {
                                    $data[$fd . '@squarePath']  = $square;
                                    $data[$fd . '@squareX']     = $xy[0];
                                    $data[$fd . '@squareY']     = $xy[1];
                                    $data[$fd . '@squareAlt']   = $c['alt'];
                                    $data[$fd . '@squareFileSize']  = PublicStorage::getFileSize($squarePath);

                                    $processingMediaFiles[] = [
                                        'path'  => $normalPath,
                                    ];
                                }
                            }

                            //--------
                            // secret
                            // 正しくファイルがアップロードされた場合のみ新しくキーを発行する
                            $data[$fd . '@secret'] = md5($fd . '@' . $normal);

                            continue;
                        }

                        //-----
                        // old
                        // 非編集アップデートの時
                        if (!empty($c['old'])) {
                            //--------
                            // normal
                            $normal = $c['old'];
                            $normalPath = $ARCHIVES_DIR . $normal;
                            if ($xy = PublicStorage::getImageSize($normalPath)) {
                                $data[$fd . '@path']  = $normal;
                                $data[$fd . '@x']     = $xy[0];
                                $data[$fd . '@y']     = $xy[1];
                                $data[$fd . '@alt']   = $c['alt'];
                                $data[$fd . '@fileSize'] = PublicStorage::getFileSize($normalPath);

                                if (!preg_match('@((?:[^/]*/)*)((?:[^.]*\.)*)(.*)$@', $normal, $match)) {
                                    throw new \RuntimeException('既存ファイルのパス解析に失敗しました。');
                                }
                                $extension  = $match[3];
                                $dirname    = $match[1];
                                $basename   = $match[2] . $extension;

                                //-------
                                // large
                                $large     = $dirname . 'large-' . $basename;
                                $largePath = $ARCHIVES_DIR . $large;
                                if ($xy = PublicStorage::getImageSize($largePath)) {
                                    $data[$fd . '@largePath'] = $large;
                                    $data[$fd . '@largeX']    = $xy[0];
                                    $data[$fd . '@largeY']    = $xy[1];
                                    $data[$fd . '@largeAlt']  = $c['alt'];
                                    $data[$fd . '@largeFileSize']  = PublicStorage::getFileSize($largePath);
                                }

                                //------
                                // tiny
                                $tiny     = $dirname . 'tiny-' . $basename;
                                $tinyPath = $ARCHIVES_DIR . $tiny;
                                if ($xy = PublicStorage::getImageSize($tinyPath)) {
                                    $data[$fd . '@tinyPath']  = $tiny;
                                    $data[$fd . '@tinyX']     = $xy[0];
                                    $data[$fd . '@tinyY']     = $xy[1];
                                    $data[$fd . '@tinyAlt']   = $c['alt'];
                                    $data[$fd . '@tinyFileSize']  = PublicStorage::getFileSize($tinyPath);
                                }

                                //------
                                // square
                                $square   = $dirname . 'square-' . $basename;
                                $squarePath = $ARCHIVES_DIR . $square;
                                if ($xy = PublicStorage::getImageSize($squarePath)) {
                                    $data[$fd . '@squarePath']  = $square;
                                    $data[$fd . '@squareX']     = $xy[0];
                                    $data[$fd . '@squareY']     = $xy[1];
                                    $data[$fd . '@squareAlt']   = $c['alt'];
                                    $data[$fd . '@squareFileSize']  = PublicStorage::getFileSize($squarePath);
                                }


                                //--------
                                // secret
                                // これはエラー時にフォームを再表示しなければならない場合に必要
                                $data[$fd . '@secret']  = $c['secret'];
                            }
                        }
                    }

                    //------------
                    // save field
                    $cnt        = count($aryData);
                    foreach (
                        [
                            'path', 'x', 'y', 'alt', 'fileSize',
                            'largePath', 'largeX', 'largeY', 'largeAlt', 'largeFileSize',
                            'tinyPath', 'tinyX', 'tinyY', 'tinyAlt', 'tinyFileSize',
                            'squarePath', 'squareX', 'squareY', 'squareAlt', 'squareFileSize',
                            'secret'
                        ] as $key
                    ) {
                        $key    = $fd . '@' . $key;
                        $value  = [];
                        for ($i = 0; $cnt > $i; $i++) {
                            $value[] = !empty($aryData[$i][$key]) ? $aryData[$i][$key] : ''; // @phpstan-ignore-line
                        }
                        $field->set($key, $value);

                        //------------
                        // validation
                        foreach ($this->Post->listFields() as $_fd) {
                            if (preg_match('/^' . $key . ':(?:v#|validator#)(.+)$/', $_fd, $match)) {
                                $method = $match[1];
                                $field->setMethod($key, $method, $this->Post->get($_fd));
                                $this->Post->delete($_fd);
                            }
                        }
                    }

                //------
                // file
                } elseif ('file' == $type) {
                    if (empty($_FILES[$fd])) {
                        $this->deleteField->setField($fd . '@path', []);
                        $this->deleteField->setField($fd . '@baseName', []);
                        $this->deleteField->setField($fd . '@fileSize', []);
                        $this->deleteField->setField($fd . '@secret', []);
                        $this->deleteField->setField($fd . '@downloadName', []);

                        $field->deleteField($fd . '@path');
                        $field->deleteField($fd . '@baseName');
                        $field->deleteField($fd . '@fileSize');
                        $field->deleteField($fd . '@secret');
                        $field->deleteField($fd . '@downloadName');

                        continue;
                    }

                    $aryC   = [];
                    if (!is_array($_FILES[$fd]['tmp_name'])) {
                        $aryC[] = [
                            '_tmp_name' => $_FILES[$fd]['tmp_name'],
                            '_name'     => $_FILES[$fd]['name'],
                        ];
                    } else {
                        foreach ($_FILES[$fd]['tmp_name'] as $i => $tmp_name) {
                            $aryC[] = [
                                '_tmp_name' => $tmp_name,
                                '_name'     => $_FILES[$fd]['name'][$i],
                            ];
                        }
                    }

                    //--------------------------
                    // field copy to local vars
                    foreach (['old', 'edit', 'extension', 'filename', 'secret', 'fileSize', 'downloadName', 'originalName', 'baseName'] as $key) {
                        foreach ($aryC as $i => $c) {
                            $_field = $fd . '@' . $key;
                            if ($key === 'extension') {
                                $c[$key] = $this->Post->isExists($_field, $i) ?
                                    $this->Post->getArray($_field) : '';
                            } else {
                                $c[$key] = $this->Post->isExists($_field, $i) ?
                                    $this->Post->get($_field, '', $i) : '';
                            }
                            $aryC[$i] = $c;
                        }
                        $this->Post->delete($fd . '@' . $key);
                    }

                    // 参照用の配列を作成して，ファイル数の分だけインデックスを初期化
                    $aryPath    = [];
                    $aryName    = [];
                    $aryOriginalName = [];
                    $aryDownloadName = [];
                    $arySize    = [];
                    $arySecret  = [];
                    foreach ($aryC as $c) {
                        $aryPath[] = $aryName[] = $aryOriginalName[] = $aryDownloadName[] = $arySize[] = $arySecret[] = '';
                    }

                    $cnt    = count($aryPath);

                    for ($i = 0; $i < $cnt; $i++) {
                        $c      = $aryC[$i];
                        // 各配列のインデックス位置を，ローカル変数に参照させる
                        $_path  =& $aryPath[$i];
                        $_name  =& $aryName[$i];
                        $_orginal_name =& $aryOriginalName[$i];
                        $_download_name =& $aryDownloadName[$i];
                        $_size  =& $arySize[$i];
                        $_secret=& $arySecret[$i];

                        //-------------
                        // rawfilename
                        if (preg_match('/^@(.*)$/', $c['filename'], $match)) {
                            $c['filename']  = ('rawfilename' == $match[1]) ? date('Ym') . '/' . $c['_name'] : '';
                        }

                        //------------------------------------
                        // security check ( nullバイトチェック )
                        if ($c['old']      !== ltrim($c['old'])) {
                            continue;
                        }
                        if ($c['filename'] !== ltrim($c['filename'])) {
                            continue;
                        }

                        //-------------------------------------------------------------
                        // パスの半正規化 ( directory traversal対策・バイナリセーフ関数を使用 )
                        // この時点で //+ や ^/ は 混入する可能性はあるが無害とみなす
                        $c['old']      = preg_replace('/\.+\/+/', '', $c['old']);
                        $c['filename'] = preg_replace('/\.+\/+/', '', $c['filename']);

                        //---------------------------------------------
                        // 例外的無視ファイル
                        // pathの終端（ファイル名）が特定の場合にリジェクトする
                        if (!!preg_match('/\.htaccess$/', $c['filename'])) {
                            continue;
                        }

                        // アップロード処理中のファイルかどうか
                        $isProcessing = false;
                        foreach ($processingMediaFiles as $media) {
                            if ($media['path'] === $ARCHIVES_DIR . $c['old']) {
                                $isProcessing = true;
                                break;
                            }
                        }

                        //---------------------
                        // シークレットチェック
                        $secretCheck = ( 1
                            && !sessionWithContribution()
                            && !empty($c['old'])
                            && ( 0
                                or 'delete' == $c['edit']
                                or !empty($c['_tmp_name'])
                            )
                        ) ? ($c['secret'] == md5($fd . '@' . $c['old'])) : true;

                        //----------------------------
                        // delete ( 指定削除 continue )
                        if ('delete' === $c['edit'] && !empty($c['old']) && $secretCheck && !$isProcessing) {
                            $this->markFieldFileForRemoval($field, $fd, $c['old'], 'file');
                            continue;
                        }

                        //--------
                        // upload
                        if (!empty($c['_tmp_name']) and $secretCheck) {
                            $tmp_name   = $c['_tmp_name'];
                            if (!is_uploaded_file($tmp_name)) {
                                continue;
                            }
                            // 拡張子がなければリジェクト
                            if (!preg_match('@\.([^.]+)$@', $c['_name'], $match)) {
                                continue;
                            }

                            // テキストファイル（=PHPなどのスクリプトファイル）判定
                            // ファイルの先頭1000行を取得
                            // 文字コードが判別不能な文字列をバイナリとみなす
                            if ('on' == config('file_prohibit_textfile')) {
                                $fp = fopen($c['_tmp_name'], 'rb');
                                if ($fp === false) {
                                    continue;
                                }
                                $readedLine = 0;
                                $sampleLine = 1000;
                                $sample = '';

                                while (($line = fgets($fp, 4096)) !== false) {
                                    if ($readedLine++ > $sampleLine) {
                                        break;
                                    }
                                    $sample .= $line;
                                }

                                fclose($fp);

                                // @todo security:
                                // mb_detect_encodingを利用しているが、これはUTF-16を判定できないため、バイナリファイルと見なしてしまう
                                // 冒頭をUTF-16、以後をUTF-8にすることで不正なテキストファイルをarchivesにアップロードできる可能性がある
                                // ただし、htaccessをいじられたりしない限りは基本的に問題にならない（通常はPHP等として実行できない）
                                if (false !== detectEncode($sample)) {
                                    continue;
                                }
                            }

                            //------------------------------
                            // dirname, basename, extension
                            // アップロードされた実ファイルの拡張子が実質的に利用される
                            // extensionオプションや、filenameオプションの制限は、
                            // 意図する拡張子のファイルがアップロードされているかのチェックのみに使われる

                            // 実ファイルの拡張子
                            $extension  = $match[1];

                            if (!empty($c['filename'])) {
                                if (!preg_match('@((?:[^/]*/)*)((?:[^.]*\.)*)(.*)$@', sprintf('%03d', BID) . '/' . $c['filename'], $match)) {
                                    throw new \RuntimeException('アップロードファイルのパス解析に失敗しました。');
                                }

                                // @filenameオプションの拡張子
                                $c['filename_extension']  = $match[3];

                                // @filenameオプションの指定内に拡張子がないと，ファイル名とファイル名の拡張子が同一になる | @todo issue: 先行する正規表現を改善する
                                // ディレクトリのみでファイル名は無指定の場合は、拡張子が空になる
                                //   =>  ファイル名拡張子でチェックする意図がないものとして、filename_extensionをunsetし、以降の拡張子チェックから除外する
                                if ($c['filename'] === $c['filename_extension'] || empty($c['filename_extension'])) {
                                    unset($c['filename_extension']);
                                }

                                $dirname    = $match[1];
                                $basename   = !empty($match[2]) ? $match[2] . $extension      // basenameは実ファイルの拡張子とする
                                                                : uniqueString() . '.' . $extension;
                            } else {
                                $dirname    = PublicStorage::archivesDir();
                                $basename   = uniqueString() . '.' . $extension;
                            }
                            if (
                                // mimeタイプから判定した実ファイルの種類がアップロード許可ファイルであること
                                $this->mimeTypeValidator->validateAllowedByContent($tmp_name, $allow_file_extensions) &&
                                // "実ファイルの拡張子" が "アップロード許可拡張子コンフィグ" に含まれていること
                                $this->mimeTypeValidator->validateAllowedExtension($extension, $allow_file_extensions) &&
                                // 拡張子指定オプションが空でなければ...
                                (!$c['extension'] ||
                                    // "拡張子指定オプション" が "アップロード許可拡張子コンフィグ" に含まれていること
                                    $this->mimeTypeValidator->validateAllowedExtension($c['extension'], $allow_file_extensions)
                                ) &&
                                // ファイル名オプションの拡張子が未定義でなければ...
                                (!isset($c['filename_extension']) ||
                                    // "ファイル名オプションの拡張子" が "アップロード許可拡張子コンフィグ" に含まれていること
                                    $this->mimeTypeValidator->validateAllowedExtension($c['filename_extension'], $allow_file_extensions)
                                ) &&
                                // 保存先ディレクトリの再帰的作成
                                PublicStorage::makeDirectory($ARCHIVES_DIR . $dirname)
                            ) {
                                //---------------------------
                                // delete ( 古いファイルの削除 )
                                if (!empty($c['old']) && !$isProcessing) {
                                    $this->markFieldFileForRemoval($field, $fd, $c['old'], 'file');
                                }

                                //------
                                // copy
                                $path     = $dirname . $basename;
                                $realpath = $ARCHIVES_DIR . $path;
                                Entry::addUploadedFiles($path); // 新規バージョンとして作成する時にファイルをCOPYするかの判定に利用

                                // 重複対応
                                $realpath = PublicStorage::uniqueFilePath($realpath);
                                $path = mb_substr($realpath, strlen($ARCHIVES_DIR));
                                if ($content = file_get_contents($tmp_name)) {
                                    PublicStorage::put($realpath, $content);
                                }

                                $processingMediaFiles[] = [
                                    'path'  => $realpath,
                                ];

                                if (HOOK_ENABLE) {
                                    $Hook = ACMS_Hook::singleton();
                                    $Hook->call('mediaCreate', $realpath);
                                }

                                //-----
                                // set
                                $_path  = $path;
                                $_name  = PublicStorage::mbBasename($realpath);
                                $_orginal_name = $c['_name'];
                                $_download_name = $c['downloadName'];
                                $_size  = PublicStorage::getFileSize($realpath);
                                $_secret = md5($fd . '@' . $path);
                                continue;
                            } else {
                                $field->setMethod($fd, 'inValidFile', false);
                            }
                        }

                        //-----
                        // old
                        // 非編集アップデートの時
                        if (!empty($c['old'])) {
                            $_path  = $c['old'];
                            $_name = $c['baseName'];
                            $_orginal_name = $c['originalName'];
                            $_download_name = $c['downloadName'];
                            $_size  = $c['fileSize'];
                            $_secret = $c['secret'];
                            continue;
                        }
                    }

                    //-----------
                    // set field
                    $field->setField($fd . '@path', $aryPath);
                    $field->setField($fd . '@baseName', $aryName);
                    $field->setField($fd . '@fileSize', $arySize);
                    $field->setField($fd . '@secret', $arySecret);
                    $field->setField($fd . '@originalName', $aryOriginalName);
                    $field->setField($fd . '@downloadName', $aryDownloadName);

                    //------------
                    // validation
                    $key    = $fd . '@path';
                    foreach ($this->Post->listFields() as $_fd) {
                        if (preg_match('/^' . $key . ':(?:v#|validator#)(.+)$/', $_fd, $match)) {
                            $method = $match[1];
                            $field->setMethod($key, $method, $this->Post->get($_fd));
                            $this->Post->delete($_fd);
                        }
                    }
                }


                continue;
            }
        }

        //--------
        // search
        foreach ($field->listFields() as $fd) {
            // topic-fix_field_search: Field::getがnullを返さなくなっていたので，無指定時の戻りを擬似定数に変更して対処
            $s = $this->Post->get($fd . ':search', '__NOT_SPECIFIED__');
            if ($s === '__NOT_SPECIFIED__') {
                if (is_int(strpos($fd, '@'))) {
                    $s  = '0';
                } else {
                    $s  = '1';
                }
            }
            $field->setMeta($fd, 'search', $s !== '0');
            $this->Post->deleteField($fd . ':search');
        }

        $field->validate($V);

        return $field;
    }

    /**
     * @return array
     */
    public function getJsModules()
    {
        $Session    =& Field::singleton('session');
        $delStorage = $Session->get('webStorageDeleteKey');

        jsModule('offset', DIR_OFFSET);
        jsModule('jsDir', JS_DIR);
        jsModule('themesDir', '/' . DIR_OFFSET . THEMES_DIR);
        jsModule('ARCHIVES_DIR', $this->replaceDeliveryUrl('/' . DIR_OFFSET . ARCHIVES_DIR));
        jsModule('MEDIA_ARCHIVES_DIR', $this->replaceDeliveryUrl('/' . DIR_OFFSET . MEDIA_LIBRARY_DIR));
        jsModule('MEDIA_STORAGE_DIR', MEDIA_STORAGE_DIR);
        jsModule('bid', BID);
        jsModule('aid', AID);
        jsModule('uid', UID);
        jsModule('cid', CID);
        jsModule('eid', EID);
        jsModule('rvid', RVID);
        jsModule('bcd', htmlspecialchars(ACMS_RAM::blogCode(BID), ENT_QUOTES));
        jsModule('rid', $this->Get->get('rid', null));
        jsModule('mid', $this->Get->get('mid', null));
        jsModule('setid', $this->Get->get('setid', null));
        jsModule('layout', LAYOUT_EDIT);
        jsModule('googleApiKey', config('google_api_key'));
        jsModule('jQuery', JQuery::getVersion());
        jsModule('jQueryMigrate', JQuery::getMigrate());
        jsModule('mediaClientResize', config('media_client_resize', 'on'));
        jsModule('delStorage', $delStorage);
        jsModule('fulltimeSSL', (SSL_ENABLE and FULLTIME_SSL_ENABLE) ? 1 : 0);
        jsModule('v', md5(VERSION));
        jsModule('dbCharset', DB_CONNECTION_CHARSET);
        jsModule('auth', getAuthConsideringRole(SUID) ?: '');

        // 公開ページでも使用（built-in/library.js の fileiconPath() 経由）
        jsModule('fileiconDir', '/' . DIR_OFFSET . config('file_icon_dir'));

        // ログイン時のみ必要（メディアアップロード・タイムマシン・インライン編集など）
        if (Login::isLoggedIn()) {
            jsModule('umfs', ini_get('upload_max_filesize'));
            jsModule('pms', ini_get('post_max_size'));
            jsModule('mfu', ini_get('max_file_uploads'));
            jsModule('lgImg', config('image_size_large_criterion') . ':' . preg_replace('/[^0-9]/', '', config('image_size_large')));
            jsModule('jpegQuality', config('image_jpeg_quality', 75));
            jsModule('urlPreviewExpire', config('url_preview_expire'));
            jsModule('timemachinePreviewDefaultDevice', config('timemachine_preview_default_device'));
            jsModule('timemachinePreviewHasHistoryDevice', config('timemachine_preview_has_history_device'));
            jsModule('unitAlignVersion', config('unit_align_version', 'v2'));
            jsModule('mediaLibrary', config('media_library'));
            jsModule('edition', LICENSE_EDITION);
            jsModule('entryEditPageType', config('entry_edit_page_type'));
            // config set（ユニットエディターの localStorage キー生成に使用）
            jsModule('configSetId', Config::getCurrentConfigSetId());
            jsModule('themeSetId', Config::getCurrentThemeSetId());
            jsModule('editorSetId', Config::getCurrentEditorSetId());

            // limit（管理画面のエントリー/モジュール一覧件数設定）
            $limitOptions = configArray('admin_limit_option');
            $defaultLimit = $limitOptions[config('admin_limit_default')];
            jsModule('limitOptions', $limitOptions);
            jsModule('defaultLimit', $defaultLimit);

            // ダイレクト編集のためのデータをセットする
            jsModule('editInplace', Entry::isDirectEditEnabled() ? 'on' : 'off');
        }

        if ($Session->get('timemachine_datetime')) {
            jsModule('timeMachineMode', 'true');
        }
        if (sessionWithAdministration()) {
            jsModule('rootTpl', ROOT_TPL);
        }
        if (defined('IS_EDITING_ENTRY') && IS_EDITING_ENTRY) {
            $Session->delete('webStorageDeleteKey');
        }

        //--------------
        // multi domain
        jsModule('multiDomain', '0');
        if (defined('LICENSE_OPTION_PLUSDOMAIN') && intval(LICENSE_OPTION_PLUSDOMAIN) > 0) {
            $SQL = SQL::newSelect('blog');
            $SQL->setSelect(SQL::newFunction('blog_domain', 'DISTINCT'), 'domains', null, 'COUNT');
            $domain_num = DB::query($SQL->get(dsn()), 'one');
            if (intval($domain_num) > 1) {
                jsModule('multiDomain', '1');
            }
        }

        //----------
        // category
        if ($cid = CID) { // @phpstan-ignore-line
            $ccds   = [ACMS_RAM::categoryCode($cid)];
            while ($cid = ACMS_RAM::categoryParent($cid)) {
                if ('on' == ACMS_RAM::categoryIndexing($cid)) {
                    $ccds[] = htmlspecialchars(ACMS_RAM::categoryCode($cid), ENT_QUOTES);
                }
            }
            jsModule('ccd', join('/', array_reverse($ccds)));
        }

        //---------
        // session
        jsModule('admin', ADMIN);
        jsModule('rid', RID);
        jsModule('ecd', ACMS_RAM::entryCode(EID));
        jsModule('keyword', htmlspecialchars(str_replace('　', ' ', KEYWORD), ENT_QUOTES));
        jsModule('scriptRoot', '/' . DIR_OFFSET . (REWRITE_ENABLE ? '' : SCRIPT_FILENAME . '/'));

        //-------
        // cache
        if (config('javascript_nocache') === 'on') {
            jsModule('cache', uniqueString());
        }

        // url segments（JS側の AcmsPathSegments 型が必要とするキーのみに絞る）
        $jsSegmentKeys = [
            'bid', 'cid', 'eid', 'uid', 'utid',
            'tag', 'field', 'span', 'page', 'order',
            'limit', 'keyword', 'admin', 'tpl', 'api',
        ];
        jsModule('segments', array_intersect_key(getRoutingSegments(), array_flip($jsSegmentKeys)));

        // 管理画面のみ必要（キャッシュが効くページでは利用できないため、ログイン済みかつ管理ページのみ）
        if (Login::isLoggedIn() && Login::isAuthRequiredPage()) {
            jsModule('suid', SUID);
            jsModule('sbid', SBID);
        }

        // debug mode（built-in/library.js の isDebugMode() 経由で公開ページでも使用。サードパーティテーマ互換性）
        jsModule('isDebugMode', isDebugMode() ? '1' : '0');

        $jsModules  = [];
        foreach (jsModule() as $key => $value) {
            if ($key === 'domains') {
                $value = implode(',', $value);
            }
            $jsModules[$key] = $value;
        }

        return $jsModules;
    }

    /**
     * a-blog cms で管理しているドメインのURLかチェックする
     *
     * @param string $url
     * @return bool
     */
    public function isSafeUrl($url)
    {
        $parsed = WhatWgUrl::parse($url);
        if ($parsed === null) {
            return false;
        }
        // スキームが http or https であること
        if (!in_array($parsed->getScheme(), ['http', 'https'], true)) {
            return false;
        }
        // ホストが自サービスのドメインであること（ASCII IDN に正規化して比較）
        $host = $parsed->getAsciiHost();
        if ($host === null) {
            return false;
        }

        return in_array($host, array_map('strtolower', $this->getManagedDomains([HTTP_HOST])), true);
    }

    /**
     * @param $data
     */
    public function responseJson($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        $this->addSecurityHeader();
        $this->clientCacheHeader(true);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        die();
    }

    /**
     * @param string $lockKey
     */
    public function logLockPost($lockKey)
    {
        if (!$lockKey) {
            return;
        }
        $sql = SQL::newInsert('lock_source');
        $sql->addInsert('lock_source_key', $lockKey);
        $sql->addInsert('lock_source_address', REMOTE_ADDR);
        $sql->addInsert('lock_source_datetime', date('Y-m-d H:i:s', REQUEST_TIME));

        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * @param string $lockKey
     * @param int $trialTime 試行時間
     * @param int $trialNumber 試行回数
     * @param int $lockTime ロックタイム
     * @param bool $remoteAddr 接続元IPアドレスをチェックするかどうか
     * @return bool
     */
    public function validateLockPost($lockKey, $trialTime = 5, $trialNumber = 5, $lockTime = 15, $remoteAddr = true)
    {
        // 秒に変換
        $trialTime = $trialTime * 60;
        $lockTime = $lockTime * 60;

        // ロックされているか判定
        $sql = SQL::newSelect('lock');
        $sql->addWhereOpr('lock_key', $lockKey);
        if ($remoteAddr) {
            $sql->addWhereOpr('lock_address', REMOTE_ADDR);
        }
        $sql->addWhereOpr('lock_datetime', date('Y-m-d H:i:s', REQUEST_TIME - $lockTime), '>');
        if (DB::query($sql->get(dsn()), 'one')) {
            return false;
        }

        $sql = SQL::newSelect('lock_source');
        $sql->addSelect('*', 'trialCount', null, 'COUNT');
        $sql->addWhereOpr('lock_source_key', $lockKey);
        if ($remoteAddr) {
            $sql->addWhereOpr('lock_source_address', REMOTE_ADDR);
        }
        $sql->addWhereOpr('lock_source_datetime', date('Y-m-d H:i:s', REQUEST_TIME - $trialTime), '>');
        $trialCount = DB::query($sql->get(dsn()), 'one');
        if ($trialCount >= $trialNumber) {
            // 試行回数を超えたのでロック
            AcmsLogger::notice('試行回数を超えたのでロックしました', [
                'lockKey' => $lockKey,
                'trialTime' => $trialTime,
                'trialNumber' => $trialNumber,
                'lockTime' => $lockTime,
            ]);

            $sql = SQL::newInsert('lock');
            $sql->addInsert('lock_key', $lockKey);
            $sql->addInsert('lock_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $sql->addInsert('lock_address', REMOTE_ADDR);
            DB::query($sql->get(dsn()), 'exec');

            $sql = SQL::newDelete('lock_source');
            $sql->addWhereOpr('lock_source_key', $lockKey);
            if ($remoteAddr) {
                $sql->addWhereOpr('lock_source_address', REMOTE_ADDR);
            }
            DB::query($sql->get(dsn()), 'exec');
            return false;
        }
        $sql = SQL::newDelete('lock');
        $sql->addWhereOpr('lock_key', $lockKey);
        if ($remoteAddr) {
            $sql->addWhereOpr('lock_address', REMOTE_ADDR);
        }
        DB::query($sql->get(dsn()), 'exec');

        // １ヶ月前のログは削除
        $sql = SQL::newDelete('lock_source');
        $sql->addWhereOpr('lock_source_datetime', date('Y-m-d H:i:s', REQUEST_TIME - 2764800), '<');
        DB::query($sql->get(dsn()), 'exec');

        return true;
    }

    /**
     * @param $str
     * @return string
     */
    public function camelize($str)
    {
        return lcfirst(strtr(ucwords(strtr($str, ['_' => ' '])), [' ' => '']));
    }

    /**
     * セキュリティヘッダーを追加
     *
     * @param bool $noCache
     * @return void
     */
    public function clientCacheHeader(bool $noCache = false): void
    {
        $cacheExpireClient = intval(config('cache_expire_client'));
        if (
            (!defined('ACMS_POST') || !ACMS_POST) &&
            ('200' == substr(httpStatusCode(), 0, 3)) &&
            (!defined('ACMS_SID') || !ACMS_SID) && // @phpstan-ignore-line
            $cacheExpireClient > 0 &&
            !$noCache
        ) {
            if (config('disable_browser_cache', 'on') === 'off') {
                // ブラウザにキャッシュさせる場合
                header('Cache-Control: public, max-age=' . $cacheExpireClient);
                header('Last-Modified: ' . getRFC2068Time(REQUEST_TIME));
                header('Expires: ' . getRFC2068Time(REQUEST_TIME + $cacheExpireClient));
            } else {
                // 中間キャッシュ（CDNなど）にはキャッシュさせるが、ブラウザにはキャッシュさせない場合
                header('Cache-Control: public, no-cache, s-maxage=' . $cacheExpireClient);
                header('Expires: 0');
                header('Pragma: no-cache');
            }
        } else {
            header('Cache-Control: no-store, max-age=0'); // HTTP/1.1
            header('Pragma: no-cache'); // HTTP/1.0 レガシー対応
            header('Expires: 0');
        }
    }

    /**
     * @param string $chid
     * @param string $contents
     * @param string $mime
     */
    public function saveCache($chid, $contents, $mime)
    {
        $no_cache_page = false;
        /** @var \Acms\Services\Cache\Adapters\Tag $pageCache */
        $pageCache = Cache::page();

        if (
            0
            || (defined('NO_CACHE_PAGE') && NO_CACHE_PAGE)
            || strtoupper($_SERVER['REQUEST_METHOD']) !== 'GET'
        ) {
            $no_cache_page = true;
        }
        if (
            !!$chid &&
            !$no_cache_page &&
            '200 OK' === httpStatusCode()
        ) {
            $tagBid = 'bid-' . BID;
            $tagEid = 'eid-' . EID;
            $value = [
                'mime' => $mime,
                'charset' => config('charset'),
                'createdAt' => REQUEST_TIME,
                'data' => $contents,
            ];
            $lifetime = intval(config('cache_expire'));
            $pageCache->put($chid, $value, $lifetime, [$tagBid, $tagEid]);
        }
    }

    /**
     * 例外情報を連想配列に変換
     *
     * @param \Throwable $th
     * @param array $add
     * @return (string|int)[]
     */
    public function exceptionArray(\Throwable $th, array $add = []): array
    {
        $array = [
            'message' => $th->getMessage(),
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'trace' => getExceptionTraceAsString($th),
        ];
        return array_merge($array, $add);
    }

    /**
     * ファイルアップロードを検証
     * @param string $name
     * @return void
     * @throws RuntimeException
     */
    public function validateFileUpload($name)
    {
        if (isset($_FILES[$name]['error'])) {
            switch ($_FILES[$name]['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_INI_SIZE:
                    throw new \RuntimeException('アップロードされたファイルが大きすぎます');
                case UPLOAD_ERR_FORM_SIZE:
                    throw new \RuntimeException('アップロードされたファイルが大きすぎます');
                case UPLOAD_ERR_PARTIAL:
                    throw new \RuntimeException('通信エラーにより、正常にアップロードできませんでした');
                case UPLOAD_ERR_NO_FILE:
                    throw new \RuntimeException('ファイルがアップロードされませんでした');
                case UPLOAD_ERR_NO_TMP_DIR:
                    throw new \RuntimeException('一時ディレクトリがないためアップロードできませんでした');
                case UPLOAD_ERR_CANT_WRITE:
                    throw new \RuntimeException('ファイルの書き込みに失敗しました');
                case UPLOAD_ERR_EXTENSION:
                    throw new \RuntimeException('アップロードが拡張モジュールによって停止されました');
                default:
                    throw new \RuntimeException('不明なエラー');
            }
        }
        if (!is_uploaded_file($_FILES[$name]['tmp_name'])) {
            throw new \RuntimeException('アップロードされたファイルがありません');
        }
    }

    /**
     * 指定されたテーマの継承テーマ・システムテーマすべてのテーマの配列を取得
     *
     * @param string $theme
     * @return string[]
     */
    public function getInheritedThemes(string $theme): array
    {
        $themes = [];
        $theme = trim($theme, '@');
        $themes[] = $theme;
        while ($pos = strpos($theme, '@')) {
            $theme = substr($theme, $pos + 1);
            $themes[] = $theme;
        }
        $themes[] = 'system';
        return array_unique($themes);
    }

    /**
     * プライベートストレージの設定がローカルかどうか
     *
     * @return boolean
     */
    public function isLocalPrivateStorage(): bool
    {
        return get_class(LocalStorage::getInstance()) === get_class(PrivateStorage::getInstance());
    }

    /**
     * パブリックストレージの設定がローカルかどうか
     *
     * @return boolean
     */
    public function isLocalPublicStorage(): bool
    {
        return get_class(LocalStorage::getInstance()) === get_class(PublicStorage::getInstance());
    }

    /**
     * ローカルのディレクトリをS3などのリモートストレージにアップロード
     *
     * @param string $from
     * @param string $to
     * @param boolean $isPublic
     * @return void
     */
    public function uploadAssetDirectory(string $from, string $to, bool $isPublic): void
    {
        $uploadStorage = $isPublic ? PublicStorage::getInstance() : PrivateStorage::getInstance();
        if (!LocalStorage::isDirectory($from)) {
            return;
        }
        $uploadStorage->makeDirectory($to);
        $dir = opendir($from);
        if ($dir === false) {
            return;
        }
        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                if (LocalStorage::isDirectory($from . '/' . $file)) {
                    $this->uploadAssetDirectory($from . '/' . $file, $to . '/' . $file, $isPublic);
                } elseif ($content =  LocalStorage::get($from . '/' . $file)) {
                    $uploadStorage->put($to . '/' . $file, $content);
                }
            }
        }
        closedir($dir);
    }
}
