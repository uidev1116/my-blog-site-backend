<?php

namespace Acms\Services\Category;

use Acms\Services\Facades\Preview;
use ACMS_RAM;

class Helper
{
    /**
     * 指定したカテゴリーが子孫カテゴリーを持っているか
     * @param int $categoryId
     * @return bool
     */
    public function hasDescendantCategories(int $categoryId): bool
    {
        if (1 < ACMS_RAM::categoryRight($categoryId) - ACMS_RAM::categoryLeft($categoryId)) {
            return true;
        }
        return false;
    }

    /**
     * 現在ログインしているユーザーがカテゴリーを作成できるか
     * @param int $blogId
     * @return bool
     */
    public function canCreate(int $blogId): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (!IS_LICENSED) {
            return false;
        }
        if (roleAvailableUser()) {
            if (roleAuthorization('category_create', $blogId)) {
                return true;
            }
            return false;
        }
        if (sessionWithCompilation()) {
            return true;
        }
        return false;
    }

    /**
     * 現在ログインしているユーザーがカテゴリーを更新できるか
     * @param int $blogId
     * @return bool
     */
    public function canUpdate(int $blogId): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (!IS_LICENSED) {
            return false;
        }
        if (roleAvailableUser()) {
            if (roleAuthorization('category_edit', $blogId)) {
                return true;
            }
            return false;
        }
        if (sessionWithCompilation()) {
            return true;
        }
        return false;
    }

    /**
     * 現在ログインしているユーザーがカテゴリーを削除できるか
     * @param int $blogId
     * @return bool
     */
    public function canDelete(int $blogId): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (!IS_LICENSED) {
            return false;
        }
        if (roleAvailableUser()) {
            if (roleAuthorization('category_edit', $blogId)) {
                return true;
            }
            return false;
        }
        if (sessionWithCompilation()) {
            return true;
        }
        return false;
    }
}
