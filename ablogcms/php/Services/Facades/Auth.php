<?php

namespace Acms\Services\Facades;

/**
 * Class Auth
 *
 * @method static bool isSubscriber(int|null $uid) 指定ユーザーが読者か
 * @method static bool isContributor(int|null $uid) 指定したユーザーが投稿者か
 * @method static bool isEditor(int|null $uid) 指定したユーザーが編集者か
 * @method static bool isAdministrator(int|null $uid) 指定したユーザーが管理者か
 * @method static bool isPermissionOfSubscriber(int|null $bid) ログイン中のユーザーがそのブログにおいて読者以上の権限があるか
 * @method static bool isPermissionOfContributor(int|null $bid) ログイン中のユーザーがそのブログにおいて投稿者以上の権限があるか
 * @method static bool isPermissionOfEditor(int|null $bid) ログイン中のユーザーがそのブログにおいて編集者以上の権限があるか
 * @method static bool isPermissionOfAdministrator(int|null $bid) ログイン中のユーザーがそのブログにおいて管理者以上の権限があるか
 * @method static bool isPermissionOfSnsLogin(int|null $uid = null, int|null $bid) 指定したユーザーがSNSログインを利用できるか
 * @method static bool checkShortcut(array{bid?: int|null, cid?: int|null, rid?: int|null, mid?: int|null, scid?: int|null, setid?: int|null} $ids) ログインしているユーザーが特定の管理ページで権限があるかチェック
 * @method static array getAuthorizedBlog(int $uid) 指定したユーザーの権限があるブログリストを取得する
 * @method static bool roleAuthorization(string $action, int|null $bid, int|null $eid = 0, ?int $uid = null) 各ロールの権限があるかチェック
 * @method static bool isControlBlog(int $bid) ログイン中のユーザーがそのブログにおいて権限があるか
 */
class Auth extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'auth';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
