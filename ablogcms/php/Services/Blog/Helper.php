<?php

namespace Acms\Services\Blog;

use DB;
use SQL;
use ACMS_RAM;

class Helper
{
    /**
     * ライセンスされているドメインかチェック
     *
     * @param string $domain
     * @param int $aid
     * @param bool $isAlias
     * @param bool $update
     *
     * @return bool
     */
    public function isDomain($domain, $aid, $isAlias = false, $update = false)
    {
        // フォーマットチェック | ドメイン・IP・localhostのいずれか
        if (
            !preg_match(
                '@^([a-z0-9]+[a-z0-9-]*(?<=[a-z0-9])\.)+[a-z]+[a-z0-9-]*(?<=[a-z0-9])\.?$|' .
                '^(?:\d{1,3}\.){3}\d{1,3}$|' .
                '^localhost$@',
                $domain,
                $match
            )
        ) {
            return false;
        }

        // フォーマットチェックを通過済みで，エイリアスであり許可された共用SSLドメインなら true
        if ($isAlias && defined('LICENSE_SHARED_SSLDOMAIN')) {
            if ($domain == LICENSE_SHARED_SSLDOMAIN) {
                return true;
            }
        }

        if (preg_match(REGEX_VALID_IPV4_ADDR, $domain)) {
            return true;
        }

        // 独自ドメイン拡張 であれば true
        if (LICENSE_OPTION_OWNDOMAIN) {
            return true;
        }

        // サブドメイン拡張であれば，入力ドメインが登録ドメインのサブドメインであるかを判定
        if (LICENSE_OPTION_SUBDOMAIN && is_int(strpos($domain, LICENSE_DOMAIN))) {
            return true;
        }

        // 独自ドメイン拡張数内か判定
        if (defined('LICENSE_OPTION_PLUSDOMAIN') && intval(LICENSE_OPTION_PLUSDOMAIN) > 0 && $domain !== DOMAIN) {
            $count  = 0;
            $exsits = false;
            $dnAry  = [];

            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newSelect('blog');
            $SQL->addSelect('blog_domain', null, null, 'DISTINCT');
            $SQL->addWhereOpr('blog_domain', DOMAIN, '<>');
            if ($update) {
                $SQL->addWhereOpr('blog_id', BID, '<>');
            }
            $all    = $DB->query($SQL->get(dsn()), 'all');
            foreach ($all as $blog) {
                $blog_domain = $blog['blog_domain'];
                if (preg_match(REGEX_VALID_IPV4_ADDR, $blog_domain)) {
                    continue;
                }
                $dnAry[] = $blog_domain;
            }

            $SQL    = SQL::newSelect('alias');
            $SQL->addSelect('alias_domain', null, null, 'DISTINCT');
            $SQL->addWhereOpr('alias_domain', DOMAIN, '<>');
            if ($update) {
                $SQL->addWhereOpr('alias_id', $aid, '<>');
            }
            $all    = $DB->query($SQL->get(dsn()), 'all');
            foreach ($all as $alias) {
                $alias_domain = $alias['alias_domain'];
                if (preg_match(REGEX_VALID_IPV4_ADDR, $alias_domain)) {
                    continue;
                }
                $dnAry[] = $alias_domain;
            }

            $dnAry = array_unique($dnAry);
            foreach ($dnAry as $dn) {
                if (LICENSE_OPTION_SUBDOMAIN && is_int(strpos($dn, LICENSE_DOMAIN))) {
                    continue;
                }
                if ($dn === $domain) {
                    $exsits = true;
                }
                $count++;
            }

            if ($exsits || intval(LICENSE_OPTION_PLUSDOMAIN) > $count) {
                return true;
            }
        }

        // 入力ドメインとconfig.serverのDOMAINが同一であるかを判定
        if ($domain === DOMAIN) {
            return true;
        }

        return false;
    }

    /**
     * ブログコードの存在をチェック
     *
     * @param string $domain
     * @param string $code
     * @param int $bid
     * @param int $aid
     *
     * @return array|bool
     */
    public function isCodeExists($domain, $code, $bid = null, $aid = null)
    {
        $DB     = DB::singleton(dsn());
        $domain = strval($domain);
        $code   = strval($code);

        //----------
        // category
        $SQL    = SQL::newSelect('category');
        $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        $SQL->setSelect('category_id');
        $SQL->addWhereOpr('blog_domain', $domain);
        $SQL->addWhereOpr('blog_code', '');
        $SQL->addWhereOpr('category_code', $code);
        $SQL->setLimit(1);
        $res    = !$DB->query($SQL->get(dsn()), 'one');

        //------
        // blog
        if (!empty($res)) {
            $SQL    = SQL::newSelect('blog');
            $SQL->setSelect('blog_id');
            $SQL->addWhereOpr('blog_domain', $domain);
            $SQL->addWhereOpr('blog_code', $code);
            if (!empty($bid)) {
                $SQL->addWhereOpr('blog_id', $bid, '<>');
            }
            $SQL->setLimit(1);
            $res    = !$DB->query($SQL->get(dsn()), 'one');
        }

        //-------
        // alias
        if (!empty($res)) {
            $SQL    = SQL::newSelect('alias');
            $SQL->setSelect('alias_id');
            $SQL->addWhereOpr('alias_domain', $domain);
            $SQL->addWhereOpr('alias_code', $code);
            if (!empty($aid)) {
                $SQL->addWhereOpr('alias_id', $aid, '<>');
            }
            $SQL->setLimit(1);
            $res    = !$DB->query($SQL->get(dsn()), 'one');
        }

        //--------------
        // global alias
        if (!empty($res)) {
            $SQL    = SQL::newSelect('alias');
            $SQL->addSelect('alias_code');
            $SQL->addWhereOpr('alias_scope', 'global');
            $SQL->addWhereOpr('alias_domain', $domain);
            if ($all = $DB->query($SQL->get(dsn()), 'all')) {
                foreach ($all as $acode) {
                    if ($acode == $code) {
                        return false;
                    }
                }
            }
            return true;
        }
        return $res;
    }

    /**
     * 指定したブログのステータスが設定できるか
     *
     * @param string $val
     * @param bool $update
     *
     * @return string|bool
     */
    public function isValidStatus($val, $update = false)
    {
        if (empty($val)) {
            return true;
        }
        if ('close' == $val) {
            return true;
        }

        $aryStatus  = [];
        switch ($val) {
            case 'open':
                $aryStatus[]    = 'secret';
                break;
            case 'secret':
                $aryStatus[]    = 'close';
                break;
            case 'close':
            default:
        }

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('blog');
        $SQL->setSelect('blog_id');
        $SQL->addWhereOpr('blog_left', ACMS_RAM::blogLeft(BID), '<');
        $SQL->addWhereOpr('blog_right', ACMS_RAM::blogRight(BID), '>');
        $SQL->addWhereIn('blog_status', $aryStatus);
        $SQL->setLimit(1);

        return !$DB->query($SQL->get(dsn()), 'one');
    }

    /**
     * 指定したブログが子孫ブログを持っているか
     * @param int $blogId
     * @return bool
     */
    public function hasDescendantBlogs(int $blogId): bool
    {
        if (1 < ACMS_RAM::blogRight($blogId) - ACMS_RAM::blogLeft($blogId)) {
            return true;
        }
        return false;
    }
}
