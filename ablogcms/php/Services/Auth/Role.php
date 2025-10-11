<?php

namespace Acms\Services\Auth;

use DB;
use SQL;

class Role extends General
{
    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @var array
     */
    protected $attached = [];

    /**
     * @var bool
     */
    protected $ignoreBlogScope = false;

    /**
     * @param $method
     * @param array $args
     * @return mixed
     */
    protected function cacheMethod($method, $args = [])
    {
        $key = $method . '_' . md5('cache' . json_encode($args, JSON_UNESCAPED_UNICODE));

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }
        $callback = [$this, $method];
        if (!is_callable($callback)) {
            throw new \BadMethodCallException("Method {$method} is not callable on " . static::class);
        }
        $this->attached[$method] = true;
        $ret = call_user_func_array($callback, $args);
        $this->attached[$method] = false;
        $this->cache[$key] = $ret;

        return $ret;
    }

    /**
     * @param $method
     * @return bool|mixed
     */
    protected function cacheAttached($method)
    {
        if (isset($this->attached[$method])) {
            return $this->attached[$method];
        }
        return false;
    }

    /**
     * @param bool $ignore
     * @return void
     */
    public function setIgnoreBlogScope($ignore = true): void
    {
        $this->ignoreBlogScope = $ignore;
    }

    /**
     * 指定ユーザーが読者か
     *
     * @param int|null $uid
     * @return bool
     */
    public function isSubscriber($uid = SUID)
    {
        $args = func_get_args();
        if (!$uid) {
            return false;
        }
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, $args);
        }
        if ('subscriber' === \ACMS_RAM::userAuth($uid)) {
            return true;
        }
        if (
            1
            and !$this->isAdministrator($uid)
            and !$this->isEditor($uid)
            and !$this->isContributor($uid)
        ) {
            return true;
        }
        return false;
    }

    /**
     * 指定したユーザーが投稿者か
     *
     * @param int|null $uid
     * @return bool
     */
    public function isContributor($uid = SUID)
    {
        $args = func_get_args();
        if (!$uid) {
            return false;
        }
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, $args);
        }
        if (
            1
            and !$this->isAdministrator($uid)
            and !$this->isEditor($uid)
            and $this->roleAuthorization('entry_edit', BID, false, $uid)
        ) {
            return true;
        }
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
        $args = func_get_args();
        if (!$uid) {
            return false;
        }
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, $args);
        }
        if (
            1
            and !$this->isAdministrator($uid)
            and $this->roleAuthorization('entry_edit', BID, false, $uid)
            and $this->roleAuthorization('entry_edit_all', BID, false, $uid)
            and $this->roleAuthorization('entry_delete', BID, false, $uid)
            and $this->roleAuthorization('category_create', BID, false, $uid)
            and $this->roleAuthorization('category_edit', BID, false, $uid)
            and $this->roleAuthorization('tag_edit', BID, false, $uid)
        ) {
            return true;
        }
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
        $args = func_get_args();
        if (!$uid) {
            return false;
        }
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, $args);
        }
        if ($this->roleAuthorization('admin_etc', BID, false, $uid)) {
            return true;
        }
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
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        if (!$this->isControlBlog($bid)) {
            return false;
        }
        if (
            0
            or $this->roleAuthorization('entry_edit', BID, false)
            or $this->roleAuthorization('admin_etc', BID)
        ) {
            return true;
        }
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
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        if (!$this->isControlBlog($bid)) {
            return false;
        }
        if (
            1
            and $this->roleAuthorization('entry_edit', BID, false)
            and $this->roleAuthorization('entry_edit_all', BID)
            and $this->roleAuthorization('entry_delete', BID, false)
            and $this->roleAuthorization('category_create', BID)
            and $this->roleAuthorization('category_edit', BID)
            and $this->roleAuthorization('tag_edit', BID)
        ) {
            return true;
        }
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
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        if (!$this->isControlBlog($bid)) {
            return false;
        }
        return $this->isAdministrator();
    }

    /**
     * 指定したユーザーの権限があるブログリストを取得
     *
     * @param int $uid
     * @return array
     */
    public function getAuthorizedBlog($uid)
    {
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        $userGroups = $this->getUserGroup($uid);
        if (empty($userGroups)) {
            return [];
        }
        $authorizedBlog = [];
        $isAdministrator = $this->isAdministrator($uid);

        foreach ($userGroups as $group) {
            $groupId = $group['usergroup_id'];
            $role = $this->getRole($groupId);
            $roleId = $role['role_id'];

            $SQL = SQL::newSelect('role_blog');
            $SQL->setSelect('blog_id');
            $SQL->addWhereOpr('role_id', $roleId);
            $blogIds = DB::query($SQL->get(dsn()), 'list');

            if ($role['role_blog_axis'] === 'descendant') {
                foreach ($blogIds as $bid) {
                    $SQL = SQL::newSelect('blog');
                    $SQL->setSelect('blog_id');
                    \ACMS_Filter::blogTree($SQL, $bid, 'self-descendant');
                    if (!$isAdministrator) {
                        $SQL->addWhereOpr('blog_status', 'close', '<>');
                    }
                    $authorizedBlog = array_merge($authorizedBlog, DB::query($SQL->get(dsn()), 'list'));
                }
            } else {
                $authorizedBlog = array_merge($authorizedBlog, $blogIds);
            }
        }
        return array_unique($authorizedBlog);
    }

    /**
     * @inheritDoc
     */
    public function roleAuthorization($action, $bid = BID, $eid = 0, $uid = SUID)
    {
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        $check = false;
        $usergroups = $this->getUserGroup($uid);

        if (!$usergroups) {
            return false;
        }
        foreach ($usergroups as $ugid) {
            $ugid = $ugid['usergroup_id'];
            $role = $this->getRole($ugid);
            if (
                1
                && $this->isControlBlogByRole($role, $bid)
                && $this->isAuthAction($role, $action, $eid)
            ) {
                $check = true;
            }
        }
        return $check;
    }

    /**
     * ログイン中ユーザーの所属ユーザーグループの取得
     *
     * @param int $uid
     * @return false|array
     */
    protected function getUserGroup($uid = SUID)
    {
        $args = func_get_args();
        if (empty($uid)) {
            return false;
        }
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, $args);
        }
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('usergroup_user');
        $SQL->addSelect('usergroup_id');
        $SQL->addWhereOpr('user_id', $uid);

        if (!$usergroups = $DB->query($SQL->get(dsn()), 'all')) {
            return false;
        }
        if (!is_array($usergroups)) {
            return false;
        }
        return $usergroups;
    }

    /**
     * ロールを取得
     *
     * @param int $ugid
     * @return array
     */
    protected function getRole($ugid)
    {
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        $DB = DB::singleton(dsn());
        $role = false;

        $SQL = SQL::newSelect('usergroup');
        $SQL->addSelect('usergroup_role_id');
        $SQL->addWhereOpr('usergroup_id', $ugid);

        if ($id = $DB->query($SQL->get(dsn()), 'one')) {
            $SQL = SQL::newSelect('role');
            $SQL->addWhereOpr('role_id', $id);
            $role = $DB->query($SQL->get(dsn()), 'row');
        }
        return $role;
    }

    /**
     * このブログに対するアクセス権限がロールにあるかチェック
     *
     * @param array $role
     * @param int $bid
     * @return bool
     */
    protected function isControlBlogByRole($role, $bid)
    {
        if ($this->ignoreBlogScope) {
            return true;
        }
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        $DB = DB::singleton(dsn());
        $blogs = [];
        $check = false;
        $roleid = $role['role_id'];

        $SQL    = SQL::newSelect('role_blog');
        $SQL->addSelect('blog_id');
        $SQL->addWhereOpr('role_id', $roleid);
        $all    = $DB->query($SQL->get(dsn()), 'all');
        foreach ($all as $blog) {
            $blogs[] = $blog['blog_id'];
        }

        if ($role['role_blog_axis'] === 'descendant') {
            foreach ($blogs as $rbid) {
                if (
                    1
                    && \ACMS_RAM::blogLeft($rbid) <= \ACMS_RAM::blogLeft($bid)
                    && \ACMS_RAM::blogRight($rbid) >= \ACMS_RAM::blogRight($bid)
                ) {
                    $check = true;
                    break;
                }
            }
        } else {
            foreach ($blogs as $rbid) {
                if (intval($rbid) === intval($bid)) {
                    $check = true;
                    break;
                }
            }
        }
        return $check;
    }

    /**
     * アクションに対する権限がロールにあるかチェック
     *
     * @param array $role
     * @param string $action
     * @param int $eid
     * @return bool
     */
    protected function isAuthAction($role, $action, $eid)
    {
        if (!$this->cacheAttached(__FUNCTION__)) {
            return $this->cacheMethod(__FUNCTION__, func_get_args());
        }
        $action = 'role_' . $action;
        if (!isset($role[$action])) {
            return false;
        }

        if (
            1
            && in_array($action, ['role_entry_edit', 'role_entry_delete'], true)
            && $eid
            && $role['role_entry_edit_all'] !== 'on'
        ) {
            if (SUID == \ACMS_RAM::entryUser($eid) && $role[$action] === 'on' && $this->isControlBlogByRole($role, \ACMS_RAM::entryBlog($eid))) {
                return true;
            }
        } elseif ($role[$action] === 'on') {
            return true;
        }
        return false;
    }
}
