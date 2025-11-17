<?php

namespace Acms\Services\Template;

use Acms\Services\Facades\Cache;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Template\Acms\Cache as AcmsTemplateCache;
use Acms\Services\Template\Acms\Engine as AcmsTemplateEngine;
use Acms\Services\Template\Acms\Resolver as AcmsTemplateResolver;
use Acms\Services\Template\Contracts\Template;
use Exception;
use Field_Validation;

class Acms implements Template
{
    /**
     * @var string
     */
    protected $template = '';

    /**
     * @var  Field_Validation
     */
    protected $postData;

    /**
     * @var bool
     */
    protected $noBuildIF = false;

    /**
     * @var AcmsTemplateEngine
     */
    protected $engine;

    /**
     * @var AcmsTemplateResolver
     */
    protected $resolver;

    /**
     * @var AcmsTemplateCache
     */
    protected $cache;

    /**
     * Constructor
     *
     * @param AcmsTemplateEngine $engine
     * @param AcmsTemplateResolver $resolver
     * @param AcmsTemplateCache $cache
     * @return void
     */
    public function __construct(AcmsTemplateEngine $engine, AcmsTemplateResolver $resolver, AcmsTemplateCache $cache)
    {
        $this->engine = $engine;
        $this->resolver = $resolver;
        $this->cache = $cache;
    }

    /**
     * 展開されたテンプレートを取得
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setPostData(Field_Validation $data): void
    {
        $this->postData = $data;
    }

    public function setNoBuildIF(bool $noBuildIF): void
    {
        $this->noBuildIF = $noBuildIF;
    }

    /**
     * パスからテンプレートをロード
     *
     * @param string $path
     * @param string $theme
     * @param int $bid
     * @return void
     */
    public function load(string $path, string $theme, int $bid): void
    {
        $templateCacheEnabled = $this->cache->isEnabled();
        $tpl = '';
        if ($templateCacheEnabled) {
            // テンプレートキャッシュからテンプレートを取得
            $tpl = $this->cache->load($path, $theme);
            $tpl = setGlobalVars($tpl);
            if ($tpl !== '') {
                $this->template = $tpl;
                return;
            }
        }
        // テンプレートキャッシュがない場合、パスとテーマ設定からテンプレートを取得
        $rootTpl = $this->resolver->resolvePath($path, $theme, '/');
        $tpl = '<!--#include file="' . $rootTpl . '" vars=""-->';
        $tpl = $this->engine->spreadTemplate($tpl, $theme, $bid, true, $templateCacheEnabled);
        if ($tpl === '') {
            AcmsLogger::critical('テンプレート「' . htmlspecialchars(ROOT_TPL, ENT_QUOTES) . '」が存在しないため、ページを表示できません');
            die500('テンプレート「' . htmlspecialchars(ROOT_TPL, ENT_QUOTES) . '」が存在しないため、ページを表示できません');
        }
        if ($templateCacheEnabled) {
            $this->cache->put($tpl);
            $tpl = setGlobalVars($tpl);
        }

        $this->template = $tpl;
    }

    /**
     * 文字列からテンプレートをロード
     *
     * @param string $txt
     * @param string $path
     * @param string $theme
     * @param int $bid
     * @return void
     */
    public function loadFromString(string $txt, string $path, string $theme, int $bid, bool $withTwig = false): void
    {
        $txt = $this->engine->formatIncludeCode(tpl: $txt, withTwig: $withTwig); // インクルード文を整形
        $txt = $this->resolver->rewritePaths(setGlobalVars($txt), $theme, $path, $bid); // パスを書き換え
        $this->template = $this->engine->spreadTemplate($txt, $theme, $bid, true, false); // テンプレートを展開
    }

    /**
     * レンダリング
     *
     * @return string
     */
    public function render(): string
    {
        return $this->engine->render($this->template, $this->postData, $this->noBuildIF);
    }

    /**
     * テンプレートキャッシュを持っているか判断し、キャッシュがあればキャッシュキーを返す
     *
     * @param string $path
     * @param string $theme
     * @return false|string
     * @throws Exception
     */
    protected function getTemplateCacheKey(string $path, string $theme)
    {
        $templateCache = Cache::template();

        if (EID) { // @phpstan-ignore-line
            // インクルード文に %{ECD} が入ったキャッシュを捜索
            $templateCacheKeyWithEntry = getTemplateCacheKey($path, $theme, true);
            $cacheItem = $templateCache->getItem($templateCacheKeyWithEntry);
            if ($cacheItem && $cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }
        $templateCacheKey = getTemplateCacheKey($path, $theme);
        $cacheItem = $templateCache->getItem($templateCacheKey);
        if ($cacheItem && $cacheItem->isHit()) {
            return $cacheItem->get();
        }
        return false;
    }
}
