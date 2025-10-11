<?php

namespace Acms\Services\Auth;

class SimulateSubscriber extends General
{
    /**
     * 指定ユーザーが読者か
     *
     * @param int|null $uid
     * @return bool
     */
    public function isSubscriber($uid = SUID)
    {
        return true;
    }

    /**
     * 指定したユーザーが投稿者か
     *
     * @param int|null $uid
     * @return bool
     */
    public function isContributor($uid = SUID)
    {
        return false;
    }

    /**
     * 指定したユーザーが編集者か
     *
     * @param int|null $uid
     * @return bool
     */
    public function isEditor($uid = SUID)
    {
        return false;
    }

    /**
     * 指定したユーザーが管理者か
     *
     * @param int|null $uid
     * @return bool
     */
    public function isAdministrator($uid = SUID)
    {
        return false;
    }

    /**
     * ログイン中のユーザーがそのブログにおいて投稿者以上の権限があるか
     *
     * @param int|null $bid
     * @return bool
     */
    public function isPermissionOfContributor($bid = BID)
    {
        return false;
    }

    /**
     * ログイン中のユーザーがそのブログにおいて編集者以上の権限があるか
     *
     * @param int|null $bid
     * @return bool
     */
    public function isPermissionOfEditor($bid = BID)
    {
        return false;
    }

    /**
     * ログイン中のユーザーがそのブログにおいて管理者以上の権限があるか
     *
     * @param int|null $bid
     * @return bool
     */
    public function isPermissionOfAdministrator($bid = BID)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function checkShortcut(array $ids): bool
    {
        return false;
    }

    /**
     * ログイン中のユーザーがそのブログにおいて権限があるか
     *
     * @param int $bid
     * @return bool
     */
    public function isControlBlog($bid)
    {
        return false;
    }
}
