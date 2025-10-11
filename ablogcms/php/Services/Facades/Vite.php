<?php

declare(strict_types=1);

namespace Acms\Services\Facades;

/**
 * @method static string generateHtml(string|string[] $entrypoints, array $options = []) ViteのHTMLを生成
 * @method static string generateReactRefreshHtml() React RefreshのHTMLを生成
 * @method static 'development' | 'production' getEnvironment() 現在の Vite の動作モードの値を出力します（development または production）
 * @method static string getDevServerUrl() 開発サーバーのURLを取得
 */
class Vite extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'vite';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
