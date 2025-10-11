<?php

namespace Acms\Services\Facades;

/**
 * @method static string render(mixed $value) リッチエディタの内容をレンダリング
 * @method static string renderTitle(mixed $value) リッチエディタのタイトルをレンダリング
 * @method static string fix(string $value) リッチエディタの内容を修正
 */
class RichEditor extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'rich-editor';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
