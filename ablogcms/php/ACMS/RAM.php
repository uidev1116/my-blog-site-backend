<?php

use Acms\Services\Facades\Cache;

/**
 * ACMS_RAM
 *
 * idを与えると，そのidに対応したレコードの
 * 特定フィールドの値を返すメソッド群です
 * 各テーブルに実在するフィールド名と対応しています
 *
 * @package ACMS
 */
class ACMS_RAM
{
    /**
     * 関数キャッシュの存在フラグ
     * @var array<string, bool>
     */
    private static $cacheAttached = [];

    /**
     * 関数キャッシュ
     * @var array<string, mixed>
     */
    private static $funcCache = [];

    /**
     * キャッシュ
     * @var \Acms\Services\Cache\Contracts\AdapterInterface|null
     */
    private static $cache = null;

    /**
     * functionキャッシュ
     * 指定されたメソッドのキャッシュを取得、設定する
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function cacheMethod($method, $args = [])
    {
        $key = $method . '_' . md5('cache' . json_encode($args, JSON_UNESCAPED_UNICODE));

        if (isset(self::$funcCache[$key])) {
            return self::$funcCache[$key];
        }
        if (is_callable($method)) {
            self::$cacheAttached[$method] = true;
            $ret = call_user_func_array($method, $args);
            self::$cacheAttached[$method] = false;
            self::$funcCache[$key] = $ret;

            return $ret;
        }
        throw new \RuntimeException("Method $method is not callable.");
    }

    /**
     * 関数キャッシュが存在するか
     * @param string $method
     * @return bool
     */
    public static function cacheAttached($method)
    {
        if (timemachineMode()) {
            return true;
        }
        if (isset(self::$cacheAttached[$method])) {
            return self::$cacheAttached[$method];
        }
        return false;
    }

    /**
     * 関数キャッシュを削除する
     * @return void
     */
    public static function cacheDelete()
    {
        self::$funcCache = [];
    }

    /**
     * 各種レコードの静的なキャッシュテーブルに対するセッター兼ゲッターメソッド
     * セッターとして利用する場合には元の値を返します。
     *
     * @param string $key
     * @param string|int $id
     * @param mixed $val
     * @return mixed
     */
    public static function _mapping($key, $id, $val = null)
    {
        static $table = [];

        if (self::$cache === null) {
            self::$cache = Cache::temp();
        }
        $s = explode('_', $key, 2);
        $type = $s[0];
        if (strpos($key, 'config_set') === 0) {
            $type = 'config_set';
        }
        $isAll = $type === $key;
        $cacheKey = "cache-ram-$type-$id";
        $isCacheType = in_array($type, ['unit', 'comment'], true);
        $cacheItem = $isCacheType ? self::$cache->getItem($cacheKey) : null;

        if (3 <= func_num_args()) {
            $oldValue = null;
            if ($isAll) {
                if (isset($table[$type][$id])) {
                    $oldValue = $table[$type][$id];
                }
            } else {
                if (isset($table[$type][$id][$key])) {
                    $oldValue = $table[$type][$id][$key];
                }
            }
            if (empty($val)) {
                if ($isAll) {
                    if (isset($table[$type][$id])) {
                        unset($table[$type][$id]);
                    }
                } else {
                    if (isset($table[$type][$id][$key])) {
                        unset($table[$type][$id][$key]);
                    }
                }
                if ($isCacheType) {
                    self::$cache->forget($cacheKey);
                }
            } else {
                if ($isAll) {
                    if (!isset($table[$type])) {
                        // typeが存在しない場合は空の配列を作成
                        $table[$type] = [];
                    }
                    $table[$type][$id] = $val;
                } else {
                    if (!isset($table[$type])) {
                        // typeが存在しない場合は空の配列を作成
                        $table[$type] = [];
                    }
                    if (!isset($table[$type][$id])) {
                        // idが存在しない場合は空の配列を作成
                        $table[$type][$id] = [];
                    }
                    $table[$type][$id][$key] = $val;
                }
                if ($isCacheType && isset($table[$type][$id])) {
                    $cacheItem->set($table[$type][$id]);
                    self::$cache->putItem($cacheItem);
                }
            }
            return $oldValue;
        } else {
            $table[$type] = $table[$type] ?? [];
            $table[$type][$id] = $table[$type][$id] ?? [];

            if ($isAll ? empty($table[$type][$id]) : !array_key_exists($key, $table[$type][$id])) {
                if ($isCacheType && $cacheItem->isHit()) {
                    $table[$type][$id] = $cacheItem->get();
                } else {
                    DB::setThrowException(true);
                    try {
                        $SQL = new SQL_Select();
                        $SQL->setTable($type);
                        $SQL->addWhereOpr($type . '_id', $id);
                        if (!$row = DB::query($SQL->get(dsn()), 'row')) {
                            return null;
                        }
                        $table[$type][$id] = $row;
                        if ($isCacheType) {
                            $cacheItem->set($row);
                            self::$cache->putItem($cacheItem);
                        }
                    } catch (\Exception $e) {
                    }
                    DB::setThrowException(false);
                }
                if ($isAll ? empty($table[$type][$id]) : !array_key_exists($key, $table[$type][$id])) {
                    AcmsLogger::error("$type テーブルのID「" . $id . "」に該当する $key カラムが取得できませんでした");
                    return null;
                }
            }
            return $isAll ? $table[$type][$id] : $table[$type][$id][$key];
        }
    }

    //  {{{ Blog

    /**
     * 指定されたidから該当するブログのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $bid
     * @param array|null $val
     * @return array|null
     */
    public static function blog($bid, $val = null)
    {
        if ($val !== null && isset($val['blog_domain']) && isUnregisteredDomain()) {
            $val['blog_domain'] = HTTP_HOST;
        }
        return func_num_args() === 1 ? ACMS_RAM::_mapping('blog', $bid) : ACMS_RAM::_mapping('blog', $bid, $val);
    }

    /**
     * 指定されたidから該当するブログコードを返します
     * $code = ACMS_RAM::blogCode($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogCode($bid)
    {
        return ACMS_RAM::_mapping('blog_code', $bid);
    }

    /**
     * 指定されたidから該当するブログドメインを返します
     * $domain = ACMS_RAM::blogDomain($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogDomain($bid)
    {
        return ACMS_RAM::_mapping('blog_domain', $bid);
    }

    /**
     * 指定されたidから該当するブログの木構造leftを返します
     * $left = ACMS_RAM::blogLeft($bid);
     *
     * @param int $bid
     * @return int
     */
    public static function blogLeft($bid)
    {
        return intval(ACMS_RAM::_mapping('blog_left', $bid));
    }

    /**
     * 指定されたidから該当するブログの木構造rihgtを返します
     * $right = ACMS_RAM::blogRight($bid);
     *
     * @param int $bid
     * @return int
     */
    public static function blogRight($bid)
    {
        return intval(ACMS_RAM::_mapping('blog_right', $bid));
    }

    /**
     * 指定されたidから該当するブログのステータスを返します
     * $status = ACMS_RAM::blogStatus($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogStatus($bid)
    {
        return ACMS_RAM::_mapping('blog_status', $bid);
    }

    /**
     * 指定されたidから該当するブログの名前を返します
     * $name = ACMS_RAM::blogName($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogName($bid)
    {
        return ACMS_RAM::_mapping('blog_name', $bid);
    }

    /**
     * 指定されたidから該当するブログのインデキシングの状態を返します
     * $indexing = ACMS_RAM::blogIndexing($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogIndexing($bid)
    {
        return ACMS_RAM::_mapping('blog_indexing', $bid);
    }

    /**
     * 指定されたidから該当するブログのコンフィグセットIDを返します
     * $configSetId = ACMS_RAM::blogConfigSetId($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogConfigSetId($bid)
    {
        return ACMS_RAM::_mapping('blog_config_set_id', $bid);
    }

    /**
     * 指定されたidから該当するブログのテーマセットIDを返します
     * $configSetId = ACMS_RAM::blogThemeSetId($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogThemeSetId($bid)
    {
        return ACMS_RAM::_mapping('blog_theme_set_id', $bid);
    }

    /**
     * 指定されたidから該当するブログの編集画面セットIDを返します
     * $configSetId = ACMS_RAM::blogEditorSetId($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogEditorSetId($bid)
    {
        return ACMS_RAM::_mapping('blog_editor_set_id', $bid);
    }

    /**
     * 指定されたidから該当するブログのソート番号を返します
     * $sort = ACMS_RAM::blogSort($bid);
     *
     * @param int $bid
     * @return int
     */
    public static function blogSort($bid)
    {
        return intval(ACMS_RAM::_mapping('blog_sort', $bid));
    }

    /**
     * 指定されたidから該当するブログの親ブログIDを返します
     * $parent_bid = ACMS_RAM::blogParent($bid);
     *
     * @param int $bid
     * @return int
     */
    public static function blogParent($bid)
    {
        return intval(ACMS_RAM::_mapping('blog_parent', $bid));
    }

    /**
     * 指定されたidから該当するブログの生成日時を返します
     * $gen_datetime = ACMS_RAM::blogGeneratedDatetime($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogGeneratedDatetime($bid)
    {
        return ACMS_RAM::_mapping('blog_generated_datetime', $bid);
    }

    /**
     * 指定されたidから該当するブログのエイリアスとしての公開状態を返します
     * $alias_status = ACMS_RAM::blogAliasStatus($bid);
     *
     * @param int $bid
     * @return string|null
     */
    public static function blogAliasStatus($bid)
    {
        return ACMS_RAM::_mapping('blog_alias_status', $bid);
    }

    /**
     * 指定されたidから該当するブログのエイリアスとしてのソート番号を返します
     * $alias_sort = ACMS_RAM::blogAliasSort($bid);
     *
     * @param int $bid
     * @return int
     */
    public static function blogAliasSort($bid)
    {
        return intval(ACMS_RAM::_mapping('blog_alias_sort', $bid));
    }

    /**
     * 指定されたidから該当するブログの主エイリアスIDを返します
     * $alias_primary = ACMS_RAM::blogAliasPrimary$(bid);
     *
     * @param int $bid
     * @return int|null
     */
    public static function blogAliasPrimary($bid)
    {
        $aid    = ACMS_RAM::_mapping('blog_alias_primary', $bid);
        return is_null($aid) ? null : intval($aid);
    }

    /**
     * @param int $bid
     * @return string|false
     */
    public static function blogMaintenanceMode($bid)
    {
        static $maintenanceMode = [];
        $bid = intval($bid);

        if (isset($maintenanceMode[$bid])) {
            return $maintenanceMode[$bid];
        }

        $sql = SQL::newSelect('blog');
        $sql->setSelect('blog_maintenance_mode');
        ACMS_Filter::blogTree($sql, $bid, 'self-ancestor');
        $sql->setOrder('blog_left', 'desc');
        $sql->setLimit(1);
        $sql->addWhereOpr('blog_maintenance_mode', 2, '>', 'AND', null, 'CHAR_LENGTH');
        if ($mode = DB::query($sql->get(dsn()), 'one')) {
            $maintenanceMode[$bid] = $mode;
        } else {
            $maintenanceMode[$bid] = false;
        }
        return $maintenanceMode[$bid];
    }

    /**
     * @param int $bid
     * @param string $mode
     * @return string|null
     */
    public static function setBlogMaintenanceMode($bid, $mode)
    {
        return ACMS_RAM::_mapping('blog_maintenance_mode', $bid, $mode);
    }

    /**
     * @param int $bid
     * @param int $aid
     * @return string|null
     */
    public static function setBlogAliasPrimary($bid, $aid)
    {
        return ACMS_RAM::_mapping('blog_alias_primary', $bid, $aid);
    }

    //  }}}
    //  {{{ Rule

    /**
     * 指定されたidから該当するルールのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $rid
     * @param array|null $row
     * @return array|null
     */
    public static function rule($rid, $row = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('rule', $rid) : ACMS_RAM::_mapping('rule', $rid, $row);
    }

    /**
     * 指定されたidから該当するルールの名前を返します
     * $name = ACMS_RAM::ruleName($rid);
     *
     * @param int $rid
     * @return string|null
     */
    public static function ruleName($rid)
    {
        return ACMS_RAM::_mapping('rule_name', $rid);
    }

    //  }}}
    //  {{{ Alias

    /**
     * 指定されたidから該当するエイリアスのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $aid
     * @param array|null $row
     * @return array|null
     */
    public static function alias($aid, $row = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('alias', $aid) : ACMS_RAM::_mapping('alias', $aid, $row);
    }

    /**
     * 指定されたidから該当するエイリアスの公開状態を返します
     * $status = ACMS_RAM::aliasStatus($aid);
     *
     * @param int $aid
     * @return string|null
     */
    public static function aliasStatus($aid)
    {
        return ACMS_RAM::_mapping('alias_status', $aid);
    }

    /**
     * 指定されたidから該当するエイリアスのソート番号を返します
     * $sort = ACMS_RAM::aliasSort($aid);
     *
     * @param int $aid
     * @return int
     */
    public static function aliasSort($aid)
    {
        return intval(ACMS_RAM::_mapping('alias_sort', $aid));
    }

    /**
     * 指定されたidから該当するエイリアスのドメインを返します
     * $domain = ACMS_RAM::aliasDomain($aid);
     *
     * @param int $aid
     * @return string|null
     */
    public static function aliasDomain($aid)
    {
        return ACMS_RAM::_mapping('alias_domain', $aid);
    }

    /**
     * 指定されたidから該当するエイリアスのコードを返します
     * $code = ACMS_RAM::aliasCode($aid);
     *
     * @param int $aid
     * @return string|null
     */
    public static function aliasCode($aid)
    {
        return ACMS_RAM::_mapping('alias_code', $aid);
    }

    /**
     * 指定されたidから該当するエイリアスのブログIDを返します
     * $alias_bid = ACMS_RAM::aliasBlog($aid);
     *
     * @param int $aid
     * @return int
     */
    public static function aliasBlog($aid)
    {
        return intval(ACMS_RAM::_mapping('alias_blog_id', $aid));
    }

    /**
     * 指定されたidから該当するエイリアスの名前を返します
     * $name = ACMS_RAM::aliasName($aid);
     *
     * @param int $aid
     * @return string|null
     */
    public static function aliasName($aid)
    {
        return ACMS_RAM::_mapping('alias_name', $aid);
    }

    //  }}}
    //  {{{ User

    /**
     * 指定されたidから該当するユーザーのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $uid
     * @param array|null $val
     * @return array|null
     */
    public static function user($uid, $val = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('user', $uid) : ACMS_RAM::_mapping('user', $uid, $val);
    }

    /**
     * 指定されたidから該当するユーザーのコードを返します
     * $code = ACMS_RAM::userCode($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userCode($uid)
    {
        return ACMS_RAM::_mapping('user_code', $uid);
    }

    /**
     * 指定されたidから該当するユーザーの公開状態を返します
     * $status = ACMS_RAM::userStatus($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userStatus($uid)
    {
        return ACMS_RAM::_mapping('user_status', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのソート番号を返します
     * $sort = ACMS_RAM::userSort($uid);
     *
     * @param int $uid
     * @return int
     */
    public static function userSort($uid)
    {
        return intval(ACMS_RAM::_mapping('user_sort', $uid));
    }

    /**
     * 指定されたidから該当するユーザーの名前を返します
     * $name = ACMS_RAM::userName($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userName($uid)
    {
        return ACMS_RAM::_mapping('user_name', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのメールアドレスを返します
     * $mail = ACMS_RAM::userMail($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userMail($uid)
    {
        return ACMS_RAM::_mapping('user_mail', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのモバイルメールアドレスを返します
     * $mobile = ACMS_RAM::userMailMobile($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userMailMobile($uid)
    {
        return ACMS_RAM::_mapping('user_mail_mobile', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのURLを返します
     * $url = ACMS_RAM::userUrl($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userUrl($uid)
    {
        return ACMS_RAM::_mapping('user_url', $uid);
    }

    /**
     * 指定されたidから該当するユーザーの権限を返します
     * $auth = ACMS_RAM::userAuth($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userAuth($uid)
    {
        return ACMS_RAM::_mapping('user_auth', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのロケールを返します
     * $auth = ACMS_RAM::userLocale($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userLocale($uid)
    {
        return ACMS_RAM::_mapping('user_locale', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのインデキシングを返します
     * $indexing = ACMS_RAM::userIndexing($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userIndexing($uid)
    {
        return ACMS_RAM::_mapping('user_indexing', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのどこでもログイン機能のon/offを返します
     * $anywhere = ACMS_RAM::userLoginAnywhere($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userLoginAnywhere($uid)
    {
        return ACMS_RAM::_mapping('user_login_anywhere', $uid);
    }

    /**
     * 指定されたidから該当するユーザーの子ブログ管理権限をon/offで返します
     * $anywhere = ACMS_RAM::userGlobalAuth($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userGlobalAuth($uid)
    {
        return ACMS_RAM::_mapping('user_global_auth', $uid);
    }

    /**
     * 指定されたidから該当するユーザーのログイン有効期限を返します
     * $expire = ACMS_RAM::userLoginExpire($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userLoginExpire($uid)
    {
        return ACMS_RAM::_mapping('user_login_expire', $uid);
    }

    /**
     * 指定されたidから該当するユーザーの最終ログイン日時を返します
     * $last_login = ACMS_RAM::userLoginDatetime($uid);
     *
     * @param int $uid
     * @return string|null
     */
    public static function userLoginDatetime($uid)
    {
        return ACMS_RAM::_mapping('user_login_datetime', $uid);
    }

    /**
     * 指定されたidから該当するユーザーの所属するブログIDを返します
     * $user_bid = ACMS_RAM::userBlog($uid);
     *
     * @param int $uid
     * @return int
     */
    public static function userBlog($uid)
    {
        return intval(ACMS_RAM::_mapping('user_blog_id', $uid));
    }


    //  }}}
    //  {{{ Category

    /**
     * 指定されたidから該当するカテゴリーのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $cid
     * @param array|null $val
     * @return array|null
     */
    public static function category($cid, $val = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('category', $cid) : ACMS_RAM::_mapping('category', $cid, $val);
    }

    /**
     * 指定されたidから該当するカテゴリーのコードを返します
     * $code = ACMS_RAM::categoryCode($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryCode($cid)
    {
        return ACMS_RAM::_mapping('category_code', $cid);
    }

    /**
     * 指定されたidから該当するカテゴリーの親カテゴリーIDを返します
     * $parent_cid = ACMS_RAM::categoryParent($cid);
     *
     * @param int $cid
     * @return int
     */
    public static function categoryParent($cid)
    {
        return intval(ACMS_RAM::_mapping('category_parent', $cid));
    }

    /**
     * 指定されたidから該当するカテゴリーの木構造leftを返します
     * $left = ACMS_RAM::categoryLeft($cid);
     *
     * @param int $cid
     * @return int
     */
    public static function categoryLeft($cid)
    {
        return intval(ACMS_RAM::_mapping('category_left', $cid));
    }

    /**
     * 指定されたidから該当するカテゴリーの木構造rihgtを返します
     * $right = ACMS_RAM::categoryRight($cid);
     *
     * @param int $cid
     * @return int
     */
    public static function categoryRight($cid)
    {
        return intval(ACMS_RAM::_mapping('category_right', $cid));
    }

    /**
     * 指定されたidから該当するカテゴリーのブログIDを返します
     * $category_bid = ACMS_RAM::categoryBlog($cid);
     *
     * @param int $cid
     * @return int
     */
    public static function categoryBlog($cid)
    {
        return intval(ACMS_RAM::_mapping('category_blog_id', $cid));
    }

    /**
     * 指定されたidから該当するカテゴリーの名前を返します
     * $name = ACMS_RAM::categoryName($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryName($cid)
    {
        return ACMS_RAM::_mapping('category_name', $cid);
    }

    /**
     * 指定されたidから該当するカテゴリーのインデキシングの状態を返します
     * $indexing = ACMS_RAM::categoryIndexing($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryIndexing($cid)
    {
        return ACMS_RAM::_mapping('category_indexing', $cid);
    }

    /**
     * 指定されたidから該当するブログのコンフィグセットIDを返します
     * $configSetId = ACMS_RAM::categoryConfigSetId($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryConfigSetId($cid)
    {
        return ACMS_RAM::_mapping('category_config_set_id', $cid);
    }

    /**
     * 指定されたidから該当するブログのテーマセットIDを返します
     * $configSetId = ACMS_RAM::categoryThemeSetId($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryThemeSetId($cid)
    {
        return ACMS_RAM::_mapping('category_theme_set_id', $cid);
    }

    /**
     * 指定されたidから該当するブログの編集画面セットIDを返します
     * $configSetId = ACMS_RAM::categoryEditorSetId($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryEditorSetId($cid)
    {
        return ACMS_RAM::_mapping('category_editor_set_id', $cid);
    }

    /**
     * 指定されたidから該当するカテゴリーの公開状態を返します
     * $status = ACMS_RAM::categoryStatus($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryStatus($cid)
    {
        return ACMS_RAM::_mapping('category_status', $cid);
    }

    /**
     * 指定されたidから該当するカテゴリーのグローバル状態を返します
     * $scope = ACMS_RAM::categoryScope($cid);
     *
     * @param int $cid
     * @return string|null
     */
    public static function categoryScope($cid)
    {
        return ACMS_RAM::_mapping('category_scope', $cid);
    }

    /**
     * 指定されたidから該当するカテゴリーのソート番号を返します
     * $sort = ACMS_RAM::categorySort($cid);
     *
     * @param int $cid
     * @return int
     */
    public static function categorySort($cid)
    {
        return intval(ACMS_RAM::_mapping('category_sort', $cid));
    }

    //  }}}
    //  {{{ Entry

    /**
     * 指定されたidから該当するエントリーのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $eid
     * @param array|null $val
     * @return array|null
     */
    public static function entry($eid, $val = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('entry', $eid) : ACMS_RAM::_mapping('entry', $eid, $val);
    }

    /**
     * 指定されたidから該当するエントリーのコードを返します
     * $code = ACMS_RAM::entryCode($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryCode($eid)
    {
        return ACMS_RAM::_mapping('entry_code', $eid);
    }

    /**
     * 指定されたidから該当するエントリーのカテゴリーIDを返します
     * $entry_cid = ACMS_RAM::entryCategory($eid);
     *
     * @param int $eid
     * @return int|null
     */
    public static function entryCategory($eid)
    {
        $categoryId = ACMS_RAM::_mapping('entry_category_id', $eid);
        return is_null($categoryId) ? null : intval($categoryId);
    }

    /**
     * 指定されたidから該当するエントリーのブログIDを返します
     * $entry_bid = ACMS_RAM::entryBlog($eid)
     *
     * @param int $eid
     * @return int
     */
    public static function entryBlog($eid)
    {
        return intval(ACMS_RAM::_mapping('entry_blog_id', $eid));
    }

    /**
     * 指定されたidから該当するエントリーのユーザーIDを返します
     * $entry_uid = ACMS_RAM::entryUser($eid);
     *
     * @param int $eid
     * @return int
     */
    public static function entryUser($eid)
    {
        return intval(ACMS_RAM::_mapping('entry_user_id', $eid));
    }

    /**
     * 指定されたidから該当するエントリーのタイトルを返します
     * $title = ACMS_RAM::entryTitle($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryTitle($eid)
    {
        return ACMS_RAM::_mapping('entry_title', $eid);
    }

    /**
     * 指定されたidから該当するエントリーの日付を返します
     * $datetime = ACMS_RAM::entryDatetime($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryDatetime($eid)
    {
        return ACMS_RAM::_mapping('entry_datetime', $eid);
    }

    /**
     * 指定されたidから該当するエントリーの更新日時を返します
     * $datetime = ACMS_RAM::entryUpdatedDatetime($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryUpdatedDatetime($eid)
    {
        return ACMS_RAM::_mapping('entry_updated_datetime', $eid);
    }

    /**
     * 指定されたidから該当するエントリーの投稿日を返します
     * $datetime = ACMS_RAM::entryPostedDatetime($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryPostedDatetime($eid)
    {
        return ACMS_RAM::_mapping('entry_posted_datetime', $eid);
    }

    /**
     * 指定されたidから該当するエントリーの公開開始日付を返します
     * $datetime = ACMS_RAM::entryStartDatetime($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryStartDatetime($eid)
    {
        return ACMS_RAM::_mapping('entry_start_datetime', $eid);
    }

    /**
     * 指定されたidから該当するエントリーの公開終了日付を返します
     * $datetime = ACMS_RAM::entryEndDatetime($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryEndDatetime($eid)
    {
        return ACMS_RAM::_mapping('entry_end_datetime', $eid);
    }

    /**
     * 指定されたidから該当するエントリーのソート番号を返します
     * $sort = ACMS_RAM::entrySort($eid);
     *
     * @param int $eid
     * @return int
     */
    public static function entrySort($eid)
    {
        return intval(ACMS_RAM::_mapping('entry_sort', $eid));
    }

    /**
     * 指定されたidから該当するエントリーのユーザー内ソート番号を返します
     * $user_sort = ACMS_RAM::entryUserSort($eid);
     *
     * @param int $eid
     * @return int
     */
    public static function entryUserSort($eid)
    {
        return intval(ACMS_RAM::_mapping('entry_user_sort', $eid));
    }

    /**
     * 指定されたidから該当するエントリーのカテゴリー内ソート番号を返します
     * $category_sort = ACMS_RAM::entryCategorySort($eid);
     *
     * @param int $eid
     * @return int
     */
    public static function entryCategorySort($eid)
    {
        return intval(ACMS_RAM::_mapping('entry_category_sort', $eid));
    }

    /**
     * 指定されたidから該当するエントリーのインデキシングの状態を返します
     * $indexing = ACMS_RAM::entryIndexing($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryIndexing($eid)
    {
        return ACMS_RAM::_mapping('entry_indexing', $eid);
    }

    /**
     * 指定されたidから該当するエントリーのインデキシングの状態を返します
     * $indexing = ACMS_RAM::entryIndexing($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryMembersOnly($eid)
    {
        return ACMS_RAM::_mapping('entry_members_only', $eid);
    }

    /**
     * 指定されたidから該当するエントリーのメイン画像のユニットIDを返します
     * $primaryImage = ACMS_RAM::entryPrimaryImage($eid);
     *
     * @param int $eid
     * @return ?non-empty-string
     */
    public static function entryPrimaryImage($eid): ?string
    {
        /** @var non-empty-string|null $primaryImage */
        $primaryImage = ACMS_RAM::_mapping('entry_primary_image', $eid);
        if (is_null($primaryImage)) {
            return null;
        }
        return $primaryImage;
    }

    /**
     * 指定されたidから該当するエントリーの公開状態を返します
     * $status = ACMS_RAM::entryStatus($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryStatus($eid)
    {
        return ACMS_RAM::_mapping('entry_status', $eid);
    }

    /**
     * 指定されたidから該当するエントリーの承認状態を返します
     * $status = ACMS_RAM::entryStatus($eid);
     *
     * @param int $eid
     * @return string|null
     */
    public static function entryApproval($eid)
    {
        return ACMS_RAM::_mapping('entry_approval', $eid);
    }

    //  }}}
    //  {{{ Unit

    /**
     * 指定されたidから該当するユニットのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param string $utid
     * @param array|null $val
     * @return array|null
     */
    public static function unit($utid, $val = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('column', $utid) : ACMS_RAM::_mapping('column', $utid, $val);
    }

    /**
     * 指定されたidから該当するユニットのソート番号を返します
     * $sort = ACMS_RAM::unitSort($utid);
     *
     * @param string $utid
     * @return int
     */
    public static function unitSort($utid)
    {
        return intval(ACMS_RAM::_mapping('column_sort', $utid));
    }

    /**
     * 指定されたidから該当するユニットの揃え位置を返します
     * $align = ACMS_RAM::unitAlign($utid);
     *
     * @param string $utid
     * @return string|null
     */
    public static function unitAlign($utid)
    {
        return ACMS_RAM::_mapping('column_align', $utid);
    }

    /**
     * 指定されたidから該当するユニットの種別を返します
     * $type = ACMS_RAM::unitType($utid);
     *
     * @param string $utid
     * @return string|null
     */
    public static function unitType($utid)
    {
        return ACMS_RAM::_mapping('column_type', $utid);
    }

    /**
     * 指定されたidから該当するユニットの属性を返します
     * $attr = ACMS_RAM::unitAttr($utid);
     *
     * @param string $utid
     * @return string|null
     */
    public static function unitAttr($utid)
    {
        return ACMS_RAM::_mapping('column_attr', $utid);
    }

    /**
     * 指定されたidから該当するユニットのサイズを返します
     * $size = ACMS_RAM::unitSize($utid);
     *
     * @param string $utid
     * @return string|null
     */
    public static function unitSize($utid)
    {
        return ACMS_RAM::_mapping('column_size', $utid);
    }

    /**
     * 指定されたidから該当するユニットのフィールド1を返します
     * $field1 = ACMS_RAM::unitField1($utid);
     *
     * @param string $utid
     * @return string|null
     */
    public static function unitField1($utid)
    {
        return ACMS_RAM::_mapping('column_field_1', $utid);
    }

    /**
     * 指定されたidから該当するユニットのフィールド2を返します
     * $field2 = ACMS_RAM::unitField2($utid);
     *
     * @param string $utid
     * @return int
     */
    public static function unitField2($utid)
    {
        return intval(ACMS_RAM::_mapping('column_field_2', $utid));
    }

    /**
     * 指定されたidから該当するユニットのフィールド3を返します
     * $field3 = ACMS_RAM::unitField3($utid);
     *
     * @param string $utid
     * @return int
     */
    public static function unitField3($utid)
    {
        return intval(ACMS_RAM::_mapping('column_field_3', $utid));
    }

    /**
     * 指定されたidから該当するユニットのフィールド4を返します
     * $field4 = ACMS_RAM::unitField4($utid);
     *
     * @param string $utid
     * @return int
     */
    public static function unitField4($utid)
    {
        return intval(ACMS_RAM::_mapping('column_field_4', $utid));
    }

    /**
     * 指定されたidから該当するユニットのフィールド5を返します
     * $field5 = ACMS_RAM::unitField5($utid);
     *
     * @param string $utid
     * @return int
     */
    public static function unitField5($utid)
    {
        return intval(ACMS_RAM::_mapping('column_field_5', $utid));
    }

    /**
     * 指定されたidから該当するユニットの所属するエントリーIDを返します
     * $unit_eid = ACMS_RAM::unitEntry($utid);
     *
     * @param string $utid
     * @return int|null
     */
    public static function unitEntry($utid)
    {
        return ACMS_RAM::_mapping('column_entry_id', $utid);
    }

    /**
     * 指定されたidから該当するユニットの所属するブログIDを返します
     * $unit_bid = ACMS_RAM::unitBlog($utid);
     *
     * @param int $utid
     * @return int|null
     */
    public static function unitBlog($utid)
    {
        return ACMS_RAM::_mapping('column_blog_id', $utid);
    }

    // }}}
    // {{{ Comment

    /**
     * 指定されたidから該当するコメントのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $cmid
     * @param array|null $val
     * @return array|null
     */
    public static function comment($cmid, $val = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('comment', $cmid) : ACMS_RAM::_mapping('comment', $cmid, $val);
    }

    /**
     * 指定されたidから該当するコメントの名前を返します
     * $name = ACMS_RAM::commentName($cmid);
     *
     * @param int $cmid
     * @return string|null
     */
    public static function commentName($cmid)
    {
        return ACMS_RAM::_mapping('comment_name', $cmid);
    }

    /**
     * 指定されたidから該当するコメントのメールアドレスを返します
     * $mail = ACMS_RAM::commentMail($cmid);
     *
     * @param int $cmid
     * @return string|null
     */
    public static function commentMail($cmid)
    {
        return ACMS_RAM::_mapping('comment_mail', $cmid);
    }

    /**
     * 指定されたidから該当するコメントのURLを返します
     * $url = ACMS_RAM::commentUrl($cmid);
     *
     * @param int $cmid
     * @return string|null
     */
    public static function commentUrl($cmid)
    {
        return ACMS_RAM::_mapping('comment_url', $cmid);
    }

    /**
     * 指定されたidから該当するコメントのタイトルを返します
     * $title = ACMS_RAM::commentTitle($cmid);
     *
     * @param int $cmid
     * @return string|null
     */
    public static function commentTitle($cmid)
    {
        return ACMS_RAM::_mapping('comment_title', $cmid);
    }

    /**
     * 指定されたidから該当するコメントの本文を返します
     * $body = ACMS_RAM::commentBody($cmid);
     *
     * @param int $cmid
     * @return string|null
     */
    public static function commentBody($cmid)
    {
        return ACMS_RAM::_mapping('comment_body', $cmid);
    }

    /**
     * 指定されたidから該当するコメントのパスワード(md5)を返します
     * $pass = ACMS_RAM::commentPass($cmid);
     *
     * @param int $cmid
     * @return string|null
     */
    public static function commentPass($cmid)
    {
        return ACMS_RAM::_mapping('comment_pass', $cmid);
    }

    /**
     * 指定されたidから該当するコメントの所属するエントリーIDを返します
     * $comment_eid = ACMS_RAM::commentEntry($cmid);
     *
     * @param int $cmid
     * @return int|null
     */
    public static function commentEntry($cmid)
    {
        return intval(ACMS_RAM::_mapping('comment_entry_id', $cmid));
    }

    /**
     * 指定されたidから該当するコメントのユーザーIDを返します
     * $comment_uid = ACMS_RAM::commentUser($cmid);
     *
     * @param int $cmid
     * @return int
     */
    public static function commentUser($cmid)
    {
        return intval(ACMS_RAM::_mapping('comment_user_id', $cmid));
    }

    /**
     * 指定されたidから該当するコメントの所属するブログIDを返します
     * $commnet_bid = ACMS_RAM::commentBlog($cmid);
     *
     * @param int $cmid
     * @return int
     */
    public static function commentBlog($cmid)
    {
        return intval(ACMS_RAM::_mapping('comment_blog_id', $cmid));
    }

    /**
     * 指定されたidから該当するコメントの木構造leftを返します
     * $left = ACMS_RAM::commentLeft($cmid);
     *
     * @param int $cmid
     * @return int
     */
    public static function commentLeft($cmid)
    {
        return intval(ACMS_RAM::_mapping('comment_left', $cmid));
    }

    /**
     * 指定されたidから該当するコメントの木構造rightを返します
     * $right = ACMS_RAM::commentRight($cmid);
     *
     * @param int $cmid
     * @return int
     */
    public static function commentRight($cmid)
    {
        return intval(ACMS_RAM::_mapping('comment_right', $cmid));
    }

    /**
     * 指定されたidから該当するコメントの公開状態を返します
     * $status = ACMS_RAM::commentStatus($cmid);
     *
     * @param int $cmid
     * @return string|null
     */
    public static function commentStatus($cmid)
    {
        return ACMS_RAM::_mapping('comment_status', $cmid);
    }

    /**
     * 指定されたidから該当するコンフィグセットの名前を返します
     * @param int $setid
     * @return string|null
     */
    public static function configSetName($setid)
    {
        return ACMS_RAM::_mapping('config_set_name', $setid);
    }

    /**
     * 指定されたidから該当するコンフィグセットのブログIDを返します
     * @param int $setid
     * @return string|null
     */
    public static function configSetBlog($setid)
    {
        return ACMS_RAM::_mapping('config_set_blog_id', $setid);
    }

    /**
     * 指定されたidから該当するフォームのコードを返します
     * @param int $id
     * @return string|null
     */
    public static function formCode($id)
    {
        return ACMS_RAM::_mapping('form_code', $id);
    }

    /**
     * 指定されたidから該当するフォームの名前を返します
     * @param int $id
     * @return string|null
     */
    public static function formName($id)
    {
        return ACMS_RAM::_mapping('form_name', $id);
    }

    /**
     * 指定されたidから該当するメディアのレコードを配列で返します
     * $valが指定されていると，一時的なレコードのキャッシュを上書きします（恒久的な書き換えではありません）
     *
     * @param int $mid
     * @param array|null $val
     * @return array|null
     */
    public static function media($mid, $val = null)
    {
        return func_num_args() === 1 ? ACMS_RAM::_mapping('media', $mid) : ACMS_RAM::_mapping('media', $mid, $val);
    }

    /**
     * 指定されたidから該当するメディアのステータスを返します
     * $name = ACMS_RAM::mediaStatus($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaStatus($mid)
    {
        return ACMS_RAM::_mapping('media_status', $mid);
    }

    /**
     * 指定されたidから該当するメディアのタイプを返します
     * $name = ACMS_RAM::mediaType($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaType($mid)
    {
        return ACMS_RAM::_mapping('media_type', $mid);
    }

    /**
     * 指定されたidから該当するメディアの拡張子を返します
     * $name = ACMS_RAM::mediaExtension($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaExtension($mid)
    {
        return ACMS_RAM::_mapping('media_extension', $mid);
    }

    /**
     * 指定されたidから該当するメディアのパスを返します
     * $name = ACMS_RAM::mediaPath($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaPath($mid)
    {
        return ACMS_RAM::_mapping('media_extension', $mid);
    }

    /**
     * 指定されたidから該当するメディアのサムネイルパスを返します
     * $name = ACMS_RAM::mediaThumbnail($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaThumbnail($mid)
    {
        return ACMS_RAM::_mapping('media_thumbnail', $mid);
    }

    /**
     * 指定されたidから該当するメディアのオリジナルパスを返します
     * $name = ACMS_RAM::mediaOriginal($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaOriginal($mid)
    {
        return ACMS_RAM::_mapping('media_original', $mid);
    }

    /**
     * 指定されたidから該当するメディアのファイル名を返します
     * $name = ACMS_RAM::mediaFileName($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaFileName($mid)
    {
        return ACMS_RAM::_mapping('media_file_name', $mid);
    }

    /**
     * 指定されたidから該当するメディアの画像サイズを返します
     * $name = ACMS_RAM::mediaImageSize($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaImageSize($mid)
    {
        return ACMS_RAM::_mapping('media_image_size', $mid);
    }

    /**
     * 指定されたidから該当するメディアのファイルサイズを返します
     * $name = ACMS_RAM::mediaFileSize($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaFileSize($mid)
    {
        return ACMS_RAM::_mapping('media_file_size', $mid);
    }

    /**
     * 指定されたidから該当するメディアのアップロード日を返します
     * $name = ACMS_RAM::mediaUploadDate($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaUploadDate($mid)
    {
        return ACMS_RAM::_mapping('media_upload_date', $mid);
    }

    /**
     * 指定されたidから該当するメディアの更新日を返します
     * $name = ACMS_RAM::mediaUpdateDate($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaUpdateDate($mid)
    {
        return ACMS_RAM::_mapping('media_update_date', $mid);
    }

    /**
     * 指定されたidから該当するフィールド1を返します
     * $name = ACMS_RAM::mediaField1($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaField1($mid)
    {
        return ACMS_RAM::_mapping('media_field_1', $mid);
    }

    /**
     * 指定されたidから該当するフィールド2を返します
     * $name = ACMS_RAM::mediaField2($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaField2($mid)
    {
        return ACMS_RAM::_mapping('media_field_2', $mid);
    }

    /**
     * 指定されたidから該当するフィールド3を返します
     * $name = ACMS_RAM::mediaField3($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaField3($mid)
    {
        return ACMS_RAM::_mapping('media_field_3', $mid);
    }

    /**
     * 指定されたidから該当するフィールド4を返します
     * $name = ACMS_RAM::mediaField4($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaField4($mid)
    {
        return ACMS_RAM::_mapping('media_field_4', $mid);
    }

    /**
     * 指定されたidから該当するフィールド5を返します
     * $name = ACMS_RAM::mediaField5($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaField5($mid)
    {
        return ACMS_RAM::_mapping('media_field_5', $mid);
    }

    /**
     * 指定されたidから該当するフィールド6を返します
     * $name = ACMS_RAM::mediaField6($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaField6($mid)
    {
        return ACMS_RAM::_mapping('media_field_6', $mid);
    }

    /**
     * 指定されたidから該当するユーザーIDを返します
     * $name = ACMS_RAM::mediaUserId($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaUserId($mid)
    {
        return ACMS_RAM::_mapping('media_user_id', $mid);
    }

    /**
     * 指定されたidから該当するブログIDを返します
     * $name = ACMS_RAM::mediaBlogId($mid);
     *
     * @param int $mid
     * @return string|null
     */
    public static function mediaBlogId($mid)
    {
        return ACMS_RAM::_mapping('media_blog_id', $mid);
    }
}
