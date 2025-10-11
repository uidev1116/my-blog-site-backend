<?php

namespace Acms\Services\Facades;

/**
 * @method static string fix(string $html, bool $resizeImage = true) ブロックエディタの内容を修正
 * @method static string fixMediaId(string $html, array $mediaIdMap) ブロックエディタのメディアIDを修正
 * @method static array extractMediaId(string $html) ブロックエディタのメディアIDを抽出
 */
class BlockEditor extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'block-editor';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
