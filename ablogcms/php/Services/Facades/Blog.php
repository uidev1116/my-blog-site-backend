<?php

namespace Acms\Services\Facades;

/**
 * Class Blog
 *
 * @method static bool isDomain(string $domain, int $bid, bool $isAlias = false, bool $update = false) ライセンスされているドメインかチェック
 * @method static bool isCodeExists(string $domain, string $code, int $bid = null, int $aid = null) ブログコードの存在をチェック
 * @method static bool isValidStatus(string $val, bool $update = false) 指定したブログのステータスが設定できるか
 * @method static bool hasDescendantBlogs(int $blogId) 指定したブログが子孫ブログを持っているか
 */
class Blog extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'blog';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
