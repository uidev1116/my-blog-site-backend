<?php

namespace Acms\Services\Facades;

/**
 * @method static bool double(string $identifier, int $mid, string $scope, ?int $bid = null) モジュールが重複しているかどうかを確認
 * @method static int dup(int $mid) モジュールを複製
 * @method static bool isAllowedMultipleArguments(\Field $Module) 複数引数を許可するモジュールかどうかを確認
 * @method static bool canBulkBlogChange(int $blogId) モジュールの一括ブログ変更を許可されているかどうか
 * @method static bool canBulkDelete(int $blogId) モジュールの一括削除を許可されているかどうか
 * @method static bool canBulkExport(int $blogId) モジュールの一括エクスポートを許可されているかどうか
 * @method static bool canBulkStatusChange(int $blogId) モジュールの一括ステータス変更を許可されているかどうか
 * @method static bool canDelete(int $blogId) モジュールの削除を許可されているかどうか
 * @method static bool canDuplicate(int $blogId) モジュールの複製を許可されているかどうか
 * @method static bool canUpdate(int $blogId) モジュールの更新を許可されているかどうか
 * @method static bool canUpdateWithShortcut(int $mid, ?int $rid = null) 現在ログイン中のユーザーがショートカット機能で許可されたモジュールの更新を許可されているかどうか
 * @method static bool canCreate(int $blogId) モジュールの作成を許可されているかどうか
 * @method static bool canExport(int $blogId) モジュールのエクスポートを許可されているかどうか
 * @method static bool canImport(int $blogId) モジュールのインポートを許可されているかどうか
 * @method static bool isSafeModuleName(string $name) モジュール名が安全な文字列かどうかを確認
 */
class Module extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'module';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
