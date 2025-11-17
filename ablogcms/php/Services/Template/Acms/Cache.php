<?php

namespace Acms\Services\Template\Acms;

use Acms\Services\Facades\Cache as SystemCache;
use ACMS_Hook;
use Exception;

class Cache
{
    /**
     * @var string
     */
    protected $cacheKey = '';

    /**
     * @var string
     */
    protected $cacheKeyWithEntry = '';

    /**
     * @var \Acms\Services\Cache\Contracts\AdapterInterface
     */
    protected $systemCache;

    /**
     * テンプレートキャッシュが有効か確認
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !isDebugMode() && config('template_cache') === 'on';
    }

    /**
     * テンプレートキャッシュを取得
     *
     * @param string $path
     * @param string $theme
     * @return string
     * @throws Exception
     */
    public function load(string $path, string $theme): string
    {
        $this->systemCache = SystemCache::template();

        if (EID) { // @phpstan-ignore-line
            // インクルード文に %{ECD} が入ったキャッシュを捜索
            $this->cacheKeyWithEntry = $this->getCacheKey($path, $theme, true);
            $cacheItem = $this->systemCache->getItem($this->cacheKeyWithEntry);
            if ($cacheItem && $cacheItem->isHit()) {
                return $cacheItem->get();
            }
        }
        // インクルード文に %{ECD} が入っていないキャッシュを捜索
        $this->cacheKey = $this->getCacheKey($path, $theme);
        $cacheItem = $this->systemCache->getItem($this->cacheKey);
        if ($cacheItem && $cacheItem->isHit()) {
            return $cacheItem->get();
        }
        return '';
    }

    /**
     * テンプレートキャッシュを保存
     *
     * @param string $tpl
     * @return void
     */
    public function put(string $tpl): void
    {
        if (defined('CACHE_TPL_ENTRY') && CACHE_TPL_ENTRY === '1') {
            $this->systemCache->put($this->cacheKeyWithEntry, $tpl);
        } else {
            $this->systemCache->put($this->cacheKey, $tpl);
        }
    }

    /**
     * テンプレートキャッシュキーを取得
     *
     * @param string $path
     * @param string $theme
     * @param bool $entry
     * @return string
     */
    public function getCacheKey(string $path, string $theme, bool $entry = false): string
    {
        $globalVarsList = globalVarsList();
        $key = "$path-$theme";

        $list = [
            'BCD', 'PBCD', 'RBCD', 'CCD', 'PCCD', 'RCCD', 'ALIAS_CODE', 'CATEGORY_LEVEL',
            'IS_ADMIN', 'MODULE_NAME', 'MODULE_ID', 'ADMIN_PATH', 'ADMIN_PATH_MID',
        ];
        if (HOOK_ENABLE) {
            $hook = ACMS_Hook::singleton();
            $globalVarNames = [];
            $hook->call('addGlobalVarsInIncludePath', [ &$globalVarNames]);
            if (is_array($globalVarNames)) { // @phpstan-ignore-line
                $list = array_merge($list, $globalVarNames);
            }
        }
        foreach ($list as $name) {
            $vName = '%{' . $name . '}';
            if (isset($globalVarsList[$vName])) {
                $key .= '-' . $name . '_' . $globalVarsList[$vName];
            }
        }
        if ($entry) {
            $key .= ('-entry_' . EID);
        }
        return 'cache-tpl-' . md5($key);
    }
}
