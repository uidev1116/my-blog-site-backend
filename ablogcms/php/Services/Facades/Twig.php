<?php

namespace Acms\Services\Facades;

/**
 * @method static void load(string $path, string $theme) twigテンプレートをロード
 * @method static void addExtension(\Twig\Extension\AbstractExtension $extension) 拡張機能を追加
 * @method static void addFunction(string $name, callable $function) 関数を追加
 * @method static string render() テンプレートをレンダリング
 */
class Twig extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'template.twig';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
