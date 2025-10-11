<?php

namespace Acms\Services\Facades;

/**
 * Class Category
 *
 * @method static bool hasDescendantCategories(int $categoryId) 指定したカテゴリーが子孫カテゴリーを持っているか
 * @method static bool canCreate(int $blogId) 現在ログインしているユーザーがカテゴリーを作成できるか
 * @method static bool canUpdate(int $blogId) 現在ログインしているユーザーがカテゴリーを更新できるか
 * @method static bool canDelete(int $blogId) 現在ログインしているユーザーがカテゴリーを削除できるか
 */
class Category extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'category';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
