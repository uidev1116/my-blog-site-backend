<?php

/**
 * ACMS_Filter
 *
 * SQLヘルパの各種処理をラップしたメソッド群です
 * メソッドの外で，条件対象のテーブルが選択されている必要があります
 * 例外で，_field, _keywordはメソッド内でテーブルを自動で選択します
 *
 * @package ACMS
 */
class ACMS_Filter
{
    //------
    // blog

    /**
     * ブログの階層構造を，axisを指定して絞り込みます
     *
     * [example]
     * self         : 指定されたbidのブログ
     * descendant   : 指定されたbidの子孫ブログ
     * ancestor     : 指定されたbidの先祖ブログ
     *
     * ACMS_Filter::blogTree($SQL, $bid, 'self-or-descendant');
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param int $bid
     * @param string $axis self|descendant|ancestor
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function blogTree(&$SQL, $bid, $axis = 'self', $scope = null)
    {
        $self   = is_int(strpos($axis, 'self'));
        if (is_int(strpos($axis, 'descendant'))) {
            $eq = $self ? '=' : '';
            $l  = ACMS_RAM::blogLeft($bid);
            $r  = ACMS_RAM::blogRight($bid);
            if (1 < ($r - $l)) {
                $SQL->addWhereOpr('blog_left', $l, '>' . $eq, 'AND', $scope);
                $SQL->addWhereOpr('blog_right', $r, '<' . $eq, 'AND', $scope);
            } elseif (!$self) {
                $SQL->addWhereOpr('blog_id', -1, '=', 'AND', $scope);
            } else {
                $axis   = 'self';
            }
        } elseif (is_int(strpos($axis, 'ancestor'))) {
            $eq     = $self ? '=' : '';
            $l  = ACMS_RAM::blogLeft($bid);
            $r  = ACMS_RAM::blogRight($bid);
            $SQL->addWhereOpr('blog_left', $l, '<' . $eq, 'AND', $scope);
            $SQL->addWhereOpr('blog_right', $r, '>' . $eq, 'AND', $scope);
        }

        if ('self' === $axis) {
            $SQL->addWhereOpr('blog_id', $bid, '=', 'AND', $scope);
        }
    }

    /**
     * ブログの公開状態とアクセス中の権限に応じて，表示条件を振り分けます
     *
     * ACMS_Filter::blogStatus($SQL);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function blogStatus(&$SQL, $scope = null)
    {
        if (!sessionWithAdministration()) {
            $aryStatus  = ['open'];
            if (sessionWithSubscription()) {
                $aryStatus[]    = 'secret';
            }
            $SQL->addWhereIn('blog_status', $aryStatus, 'AND', $scope);
        }
    }

    /**
     * ブログをfieldテーブルから，指定されたフィールドで検索します
     *
     * ACMS_Filter::blogField($SQL, $Field);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param Field_Search $Field
     * @return void
     * @param-out T $SQL
     */
    public static function blogField(&$SQL, $Field)
    {
        ACMS_Filter::_field($SQL, $Field, 'field_bid', 'blog_id');
    }

    /**
     * ブログの公開状態とアクセス中の権限に応じて，表示条件を振り分けます
     * secretモードを表示対象にします
     *
     * ACMS_Filter::blogDisclosureSecretStatus($SQL);
     *
     * @access public
     * @static
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function blogDisclosureSecretStatus(&$SQL, $scope = null)
    {
        if (!sessionWithAdministration()) {
            $aryStatus  = ['open', 'secret'];
            $SQL->addWhereIn('blog_status', $aryStatus, 'AND', $scope);
        }
    }

    /**
     * ブログの特定フィールドを指定して，昇順または降順で並び替えます
     *
     * [example]
     * id-desc     : ID降順
     * code-asc    : コード昇順
     *
     * ACMS_Filter::blogOrder($SQL, 'id-desc');
     *
     * @param SQL_Select $SQL
     * @param string $order asc|desc
     * @param string|null $scp
     * @return void
     * @param-out SQL_Select $SQL
     */
    public static function blogOrder(&$SQL, $order, $scp = null)
    {
        list($field, $order) = explode('-', $order);
        $SQL->addOrder('blog_' . $field, $order, $scp);
    }

    /**
     * ブログをfulltextテーブルから，指定されたキーワードで全文検索します
     *
     * ACMS_Filter::blogKeyword($SQL, 'first second third keywords')
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string $keyword
     * @return void
     * @param-out T $SQL
     */
    public static function blogKeyword(&$SQL, $keyword)
    {
        ACMS_Filter::_keyword($SQL, $keyword, 'fulltext_bid', 'blog_id');
    }

    //------
    // user

    /**
     * ユーザーの公開状態とアクセス中の権限に応じて，表示条件を振り分けます
     *
     * ACMS_Filter::userStatus($SQL);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function userStatus(&$SQL, $scope = null)
    {
        if (!sessionWithAdministration()) {
            $SQL->addWhereOpr('user_status', 'open', '=', 'AND', $scope);
        }
    }

    /**
     * ユーザーの特定フィールドを指定して，昇順または降順で並び替えます
     *
     * [example]
     * id-desc     : ID降順
     * code-asc    : コード昇順
     *
     * ACMS_Filter::userOrder($SQL, 'code-desc');
     *
     * @param SQL_Select $SQL
     * @param string $order asc|desc
     * @param string|null $scp
     * @return void
     * @param-out SQL_Select $SQL
     */
    public static function userOrder(&$SQL, $order, $scp = null)
    {
        $aryOrder   = explode('-', $order);
        $fd     = isset($aryOrder[0]) ? $aryOrder[0] : null;
        $seq    = isset($aryOrder[1]) ? $aryOrder[1] : null;

        if ('random' == $fd) {
            $Fd = SQL::newFunction(null, 'random', $scp);
        } else {
            if ('field' == $fd) {
                if (false !== strpos($SQL->getSQL(dsn()), 'field_sort')) {
                    $fd = 'strfield_sort';
                } else {
                    $fd = 'user_id';
                }
            } elseif ('intfield' == $fd) {
                if (false !== strpos($SQL->getSQL(dsn()), 'intfield_sort')) {
                    $fd = 'intfield_sort';
                } else {
                    $fd = 'user_id';
                }
            } elseif ('amount' == $fd) {
                $fd = 'entry_amount';
            } else {
                $fd = 'user_' . $fd;
            }

            $Fd = SQL::newField($fd, $scp);
        }

        $SQL->addOrder($Fd, $seq, $scp);
    }

    /**
     * ユーザーをfulltextテーブルから，指定されたキーワードで全文検索します
     *
     * ACMS_Filter::userKeyword($SQL, 'first second third keywords')
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string $keyword
     * @return void
     * @param-out T $SQL
     */
    public static function userKeyword(&$SQL, $keyword)
    {
        ACMS_Filter::_keyword($SQL, $keyword, 'fulltext_uid', 'user_id');
    }

    /**
     * ユーザーをfieldテーブルから，指定されたフィールドで検索します
     *
     * ACMS_Filter::userField($SQL, $Field);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param Field_Search $Field
     * @return void
     * @param-out T $SQL
     */
    public static function userField(&$SQL, $Field)
    {
        ACMS_Filter::_field($SQL, $Field, 'field_uid', 'user_id');
    }

    //----------
    // category


    /**
     * カテゴリーの公開状態とアクセス中の権限に応じて，表示条件を振り分けます
     *
     * ACMS_Filter::categoryStatus($SQL);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function categoryStatus(&$SQL, $scope = null)
    {
        if (!sessionWithContribution()) {
            $where = SQL::newWhere();
            $where->addWhereOpr('category_status', null, '=', 'OR', $scope);
            $where->addWhereOpr('category_status', 'open', '=', 'OR', $scope);
            if (sessionWithSubscription()) {
                $where->addWhereOpr('category_status', 'secret', '=', 'OR', $scope);
            }
            $SQL->addWhere($where, 'AND');
        }
    }

    /**
     * カテゴリーの公開状態とアクセス中の権限に応じて，表示条件を振り分けます
     * secretモードを表示対象にします
     *
     * ACMS_Filter::categoryDisclosureSecretStatus($SQL);
     *
     * @access public
     * @static
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function categoryDisclosureSecretStatus(&$SQL, $scope = null)
    {
        if (!sessionWithAdministration()) {
            $where = SQL::newWhere();
            $where->addWhereOpr('category_status', null, '=', 'OR', $scope);
            $where->addWhereIn('category_status', ['open', 'secret'], 'OR', $scope);
            $SQL->addWhere($where, 'AND');
        }
    }

    /**
     * カテゴリーの階層構造で，指定されたbid以下すべてを対象とします
     *
     * ACMS_Filter::categoryTreeGlobal($SQL, $bid);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param int $bid
     * @param bool $self
     * @param string|null $categoryScope
     * @param string|null $blogScope
     * @return void
     * @param-out T $SQL
     */
    public static function categoryTreeGlobal(&$SQL, $bid, $self = true, $categoryScope = null, $blogScope = null)
    {
        $bleft  = ACMS_RAM::blogLeft($bid);
        $bright = ACMS_RAM::blogRight($bid);

        if ($self) {
            $SQLWhereGlobal = SQL::newWhere();
            $SQLWhereGlobal->addWhereOpr('category_scope', 'global', '=', 'AND', $categoryScope);
            $SQLWhereGlobal->addWhereOpr('blog_left', $bleft, '<', 'AND', $blogScope);
            $SQLWhereGlobal->addWhereOpr('blog_right', $bright, '>', 'AND', $blogScope);

            $SQLWhereScope  = SQL::newWhere();
            $SQLWhereScope->addWhereOpr('category_blog_id', $bid, '=', 'AND', $categoryScope);
            $SQLWhereScope->addWhere($SQLWhereGlobal, 'OR');

            $SQL->addWhere($SQLWhereScope);
        } else {
            $SQL->addWhereOpr('category_scope', 'global', '=', 'AND', $categoryScope);
            $SQL->addWhereOpr('blog_left', $bleft, '<', 'AND', $blogScope);
            $SQL->addWhereOpr('blog_right', $bright, '>', 'AND', $blogScope);
        }
    }

    /**
     * カテゴリーをfieldテーブルから，指定されたフィールドで検索します
     *
     * ACMS_Filter::categoryField($SQL, $Field);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param Field_Search $Field
     * @return void
     * @param-out T $SQL
     */
    public static function categoryField(&$SQL, $Field)
    {
        ACMS_Filter::_field($SQL, $Field, 'field_cid', 'category_id');
    }

    /**
     * カテゴリーの階層構造を，axisを指定して絞り込みます
     *
     * [example]
     * self         : 指定されたbidのカテゴリー
     * descendant   : 指定されたbidの子孫カテゴリー
     * ancestor     : 指定されたbidの先祖カテゴリー
     *
     * ACMS_Filter::categoryTree($SQL, $cid, 'descendant');
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param int $cid
     * @param string $axis
     * @param string|null $scope
     * @return bool
     * @param-out T $SQL
     */
    public static function categoryTree(&$SQL, $cid, $axis = 'self', $scope = null)
    {
        if (!$cid) {
            return false;
        }
        $self = is_int(strpos($axis, 'self'));
        if (is_int(strpos($axis, 'descendant'))) {
            $eq  = $self ? '=' : '';
            $l = ACMS_RAM::categoryLeft($cid);
            $r = ACMS_RAM::categoryRight($cid);
            $cbid = ACMS_RAM::categoryBlog($cid);

            if (1 < ($r - $l)) {
                $SQL->addWhereOpr('category_left', $l, '>' . $eq, 'AND', $scope);
                $SQL->addWhereOpr('category_right', $r, '<' . $eq, 'AND', $scope);
                $SQL->addWhereOpr('category_blog_id', $cbid, '=', 'AND', $scope);
            } elseif (!$self) {
                $SQL->addWhereOpr('category_id', -1, '=', 'AND', $scope);
            } else {
                $axis = 'self';
            }
        } elseif (is_int(strpos($axis, 'ancestor'))) {
            $eq = $self ? '=' : '';
            $l = ACMS_RAM::categoryLeft($cid);
            $r = ACMS_RAM::categoryRight($cid);
            $cbid = ACMS_RAM::categoryBlog($cid);

            $SQL->addWhereOpr('category_left', $l, '<' . $eq, 'AND', $scope);
            $SQL->addWhereOpr('category_right', $r, '>' . $eq, 'AND', $scope);
            $SQL->addWhereOpr('category_blog_id', $cbid, '=', 'AND', $scope);
        }
        if ('self' === $axis) {
            $SQL->addWhereOpr('category_id', $cid, '=', 'AND', $scope);
        }
        return $axis === 'self';
    }

    /**
     * カテゴリーの特定フィールドを指定して，昇順または降順で並び替えます
     *
     * [example]
     * id-desc     : ID降順
     * code-asc    : コード昇順
     *
     * ACMS_Filter::categoryOrder($SQL, 'code-desc');
     *
     * @param SQL_Select $SQL
     * @param string $order asc|desc
     * @param string|null $scope
     * @return void
     * @param-out SQL_Select $SQL
     */
    public static function categoryOrder(&$SQL, $order, $scope = null)
    {
        list($field, $order) = explode('-', $order);
        if ('amount' == $field) {
            $field = 'entry_' . $field;
        }
        if ('sort' == $field) {
            $field = 'left';
        }
        if ($field === 'left') {
            $SQL->addOrder('category_blog_id', config('global_category_sort', 'DESC'));
        }
        $SQL->addOrder('category_' . $field, $order, $scope);
    }

    /**
     * カテゴリーをfulltextテーブルから，指定されたキーワードで全文検索します
     *
     * ACMS_Filter::categoryKeyword($SQL, 'first second third keywords')
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string $keyword
     * @return void
     * @param-out T $SQL
     */
    public static function categoryKeyword(&$SQL, $keyword)
    {
        ACMS_Filter::_keyword($SQL, $keyword, 'fulltext_cid', 'category_id');
    }

    //-------
    // entry

    /**
     * エントリーの公開状態とアクセス中の権限に応じて，表示条件を振り分けます
     *
     * ACMS_Filter::entryStatus($SQL);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function entryStatus(&$SQL, $scope = null)
    {

        if (true || !sessionWithCompilation()) {
            $SQLWhereEntryStatus    = SQL::newWhere();
            $SQLWhereEntryStatus->addWhereOpr('entry_status', 'open', '=', 'AND', $scope);
            if (sessionWithContribution()) {
                $SQLWhereContributor    = SQL::newWhere();
                $SQLWhereContributor->addWhereOpr('entry_user_id', SUID, '=', 'AND', $scope);
                $SQLWhereEntryStatus->addWhere($SQLWhereContributor);
            }
            $SQL->addWhere($SQLWhereEntryStatus);
        }
    }

    /**
     * 指定された日時が，公開期間に含まれているエントリーを絞り込みます
     *
     * ACMS_Filter::entryValidSpan($SQL, '2011-04-21 13:00:00');
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string $datetime '1001-01-01 00:00:00'
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function entryValidSpan(&$SQL, $datetime, $scope = null)
    {
        $SQL->addWhereOpr('entry_start_datetime', $datetime, '<=', 'AND', $scope);
        $SQL->addWhereOpr('entry_end_datetime', $datetime, '>=', 'AND', $scope);
    }

    /**
     * 現在の日時が，公開期間に含まれていて，アクセス中の権限で表示可能なエントリーを絞り込みます
     *
     * ACMS_Filter::entrySession($SQL);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string|null $scp
     * @param bool $private 非公開エントリーを表示するか（true: 表示しない, false: 表示する）
     * @return void
     * @param-out T $SQL
     */
    public static function entrySession(&$SQL, $scp = null, $private = false)
    {
        $SQLWhereSession    = SQL::newWhere();

        //------------
        // valid span
        // @todo issue: 秒のタイムスタンプを 00 に丸めてMySQLキャッシュを効かせるオプションが必要
        $SQLWhereSession->addWhereOpr('entry_start_datetime', date('Y-m-d H:i:s', requestTime()), '<=', 'AND', $scp);
        $SQLWhereSession->addWhereOpr('entry_end_datetime', date('Y-m-d H:i:s', requestTime()), '>=', 'AND', $scp);

        //--------
        // status
        $SQLWhereSession->addWhereOpr('entry_status', 'open', '=', 'AND', $scp);
        $SQLWhereSession->addWhereOpr('entry_approval', 'pre_approval', '<>', 'AND', $scp);

        if ($private || timemachineMode()) {
            $SQL->addWhere($SQLWhereSession);
        } elseif (
            1
            && !sessionWithCompilation()
            && (config('approval_contributor_edit_auth') === 'on' || !approvalAvailableUser(SUID))
        ) {
            if (
                1
                && roleAvailableUser()
                && roleAuthorization('entry_edit', BID)
                && roleAuthorization('entry_edit_all', BID)
            ) {
                if (!(defined('RVID') && RVID) && !isset($_GET['trash'])) {
                    $SQL->addWhereOpr('entry_status', 'trash', '<>', 'AND', $scp);
                }
                return;
            } elseif (sessionWithContribution()) {
                $SQLWhereStatus = SQL::newWhere();
                if (
                    1
                    && (config('approval_contributor_edit_auth') === 'on' || !approvalAvailableUser(SUID))
                    && ( 'on' == config('session_contributor_only_own_entry') )
                ) {
                    $connector  = 'AND';
                } else {
                    $SQLWhereStatus->addWhere($SQLWhereSession);
                    $connector  = 'OR';
                }
                $SQLWhereStatus->addWhereOpr('entry_user_id', SUID, '=', $connector, $scp);

                $SQL->addWhere($SQLWhereStatus);
                $SQL->addWhereOpr('entry_status', 'trash', '<>', 'AND', $scp);
            } else {
                $SQL->addWhere($SQLWhereSession);
            }
        } elseif (!(defined('RVID') && RVID) && !isset($_GET['trash'])) {
            $SQL->addWhereOpr('entry_status', 'trash', '<>', 'AND', $scp);
        }
    }

    /**
     * 開始〜終了の指定による期間の該当する日付のエントリーを絞り込みます
     *
     * ACMS_Filter::entrySpan($SQL, '2010-01-01', '2010-12-31');
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string $start '1001-01-01 00:00:00'
     * @param string $end '9999-12-31 23:59:59'
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function entrySpan(&$SQL, $start, $end, $scope = null)
    {
        if ($start === '1000-01-01 00:00:00' && $end === '9999-12-31 23:59:59') {
            return;
        }
        $SQL->addWhereBw('entry_datetime', $start, $end, 'AND', $scope);
    }

    /**
     * エントリーをタグで絞り込みます
     *
     * ACMS_Filter::entryTag($SQL, $tags);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string[] $tags
     * @param string|null $scope
     * @return void
     * @param-out T $SQL
     */
    public static function entryTag(&$SQL, $tags, $scope = null)
    {
        if (!is_array($tags)) {
            return;
        }

        if (count($tags) < 1) {
            return;
        }

        $tag = array_shift($tags);
        $SQL->addLeftJoin('tag', 'tag_entry_id', 'entry_id', 'tag0');
        $SQL->addWhereOpr('tag_name', $tag, '=', 'AND', 'tag0');
        $i  = 1;
        while ($tag = array_shift($tags)) {
            $SQL->addLeftJoin('tag', 'tag_entry_id', 'tag_entry_id', 'tag' . $i, 'tag' . ($i - 1));
            $SQL->addWhereOpr('tag_name', $tag, '=', 'AND', 'tag' . $i);
            $i++;
        }
    }

    /**
     * エントリーfulltextテーブルから，指定されたキーワードで全文検索します
     *
     * ACMS_Filter::entryKeyword($SQL, 'first second third keywords');
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string $keyword
     * @return void
     * @param-out T $SQL
     */
    public static function entryKeyword(&$SQL, $keyword)
    {
        ACMS_Filter::_keyword($SQL, $keyword, 'fulltext_eid', 'entry_id');
    }

    /**
     * エントリーをfieldテーブルから，指定されたフィールドで検索します
     *
     * ACMS_Filter::entryField($SQL, $Field);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param Field_Search $Field
     * @param string|null $bScp 結合先のエイリアス名
     * @return array
     * @param-out T $SQL
     */
    public static function entryField(&$SQL, $Field, $bScp = null)
    {
        return ACMS_Filter::_field($SQL, $Field, 'field_eid', 'entry_id', $bScp);
    }

    /**
     * エントリーの特定フィールドを指定して，昇順または降順で並び替えます
     *
     * [example]
     * id-desc     : ID降順
     * code-asc    : コード昇順
     *
     * ACMS_Filter::entryOrder($SQL, 'code-asc');
     *
     * @param SQL_Select $SQL
     * @param string|string[] $order 表示順
     * @param int|null $uid ユーザーID
     * @param int|null $cid カテゴリーID
     * @param false|int $secondary_filed_sort 2以上の場合，第２表示順以降もフィールドでのソートを行います
     * @param false|string $field_name ソートするフィールド名
     *
     * @return string
     * @param-out SQL_Select $SQL
     */
    public static function entryOrder(&$SQL, $order, $uid = null, $cid = null, $secondary_filed_sort = false, $field_name = false)
    {
        $orders = [];
        $sortFd = '';

        if (is_array($order)) {
            $orderKeys = [];
            foreach ($order as $item) {
                if (preg_match('/^[^-]+-(asc|desc)$/i', $item)) {
                    $order_info = explode('-', $item);
                    $fd = isset($order_info[0]) ? $order_info[0] : null;
                    if (!isset($orderKeys[$fd])) {
                        $orderKeys[$fd] = $item;
                    }
                } else {
                    $orderKeys[$item] = $item;
                }
            }
            $order = array_values($orderKeys);
            $first = array_shift($order);
            $orders = $order;
        } else {
            $first = $order;
            $order = null;
        }

        $fd = $first;
        $seq = 'asc';
        $field_num = '';
        if (intval($secondary_filed_sort) > 1) {
            $field_num = '_' . $secondary_filed_sort;
        }

        if ($first && preg_match('/^[^-]+-(asc|desc)$/i', $first)) {
            $order_info = explode('-', $first);
            $fd = isset($order_info[0]) ? $order_info[0] : null;
            $seq = isset($order_info[1]) ? $order_info[1] : null;
        }

        if ('random' == $fd) {
            $SQL->setOrder(SQL::newFunction(null, 'random'));
        } else {
            $currentSQL = $SQL->getSQL(dsn());
            switch ($fd) {
                case 'sort':
                    if ($uid !== null && $uid > 0) {
                        $SQL->addOrder('entry_user_sort', $seq);
                        $sortFd = 'entry_user_sort';
                    } elseif ($cid !== null && $cid >= 0) { // 0はカテゴリー未設定のエントリーの場合
                        $SQL->addOrder('entry_category_sort', $seq);
                        $sortFd = 'entry_category_sort';
                    } else {
                        $SQL->addOrder('entry_sort', $seq);
                        $sortFd = 'entry_sort';
                    }
                    break;
                case 'id':
                case 'code':
                case 'status':
                case 'user_sort':
                case 'category_sort':
                case 'title':
                case 'link':
                case 'datetime':
                case 'start_datetime':
                case 'end_datetime':
                case 'posted_datetime':
                case 'updated_datetime':
                case 'summary_range':
                case 'indexing':
                case 'primary_image':
                case 'category_id':
                case 'user_id':
                case 'blog_id':
                    $SQL->addOrder('entry_' . $fd, $seq);
                    $sortFd = 'entry_' . $fd;
                    break;
                case 'field':
                    if ($field_name) {
                        $SUB = SQL::newSelect('field');
                        $SUB->addSelect('field_eid');
                        $SUB->addSelect('field_value', 'strfield_sort_column');
                        $SUB->addWhereOpr('field_key', $field_name);
                        $SQL->addLeftJoin($SUB, 'field_eid', 'entry_id', 'sortFieldTable' . $secondary_filed_sort);
                        $SQL->addOrder(SQL::newOpr('strfield_sort_column', null, '='), 'ASC');
                        $SQL->addOrder('strfield_sort_column', $seq);
                    } elseif (false !== strpos($currentSQL, 'strfield_sort')) {
                        $SQL->addOrder('strfield_sort' . $field_num, $seq);
                    }
                    if (intval($secondary_filed_sort) > 1) {
                        $secondary_filed_sort++;
                    } else {
                        $secondary_filed_sort = 2;
                    }
                    break;
                case 'intfield':
                    if (!empty($field_name)) {
                        $SUB = SQL::newSelect('field');
                        $SUB->addSelect('field_eid');
                        $SUB->addSelect(SQL::newOpr('field_value', 0, '+'), 'intfield_sort_column');
                        $SUB->addWhereOpr('field_key', $field_name);
                        $SQL->addLeftJoin($SUB, 'field_eid', 'entry_id', 'sortFieldTable' . $secondary_filed_sort);
                        $SQL->addOrder(SQL::newOpr('intfield_sort_column', null, '='), 'ASC');
                        $SQL->addOrder('intfield_sort_column', $seq);
                    } elseif (false !== strpos($currentSQL, 'intfield_sort')) {
                        $SQL->addOrder('intfield_sort' . $field_num, $seq);
                    }
                    if (intval($secondary_filed_sort) > 1) {
                        $secondary_filed_sort++;
                    } else {
                        $secondary_filed_sort = 2;
                    }
                    break;
                default:
                    break;
            }
            foreach ($orders as $orderString) {
                if ($orderString !== 'random') {
                    self::entryOrder($SQL, $orderString, $uid, $cid, $secondary_filed_sort, $field_name);
                }
            }
        }

        return $sortFd;
    }

    //-----
    // module

    /**
     * モジュールテーブルから，指定されたキーワードで全文検索します
     *
     * ACMS_Filter::moduleKeyword($SQL, 'first second third keywords');
     *
     * @template T of \SQL_Where
     * @param T $sql
     * @param string $keyword
     * @param string|null $scope
     * @param string[] $columns
     * @return void
     * @param-out T $sql
     */
    public static function moduleKeyword(&$sql, $keyword, $scope = null, $columns = [])
    {
        if (count($columns) === 0) {
            $columns = configArray('module_keyword_search_column');
        }
        $keywords = self::splitKeyword($keyword);
        $columns = array_map(function ($column) {
            return "`{$column}`";
        }, $columns);

        foreach ($keywords as $word) {
            if (substr($word, 0, 1) === '-') {
                $sql->addWhereOpr(
                    SQL::newField(implode(',', $columns), null, false),
                    '%' . addcslashes(substr($word, 1), '%_\\') . '%',
                    'NOT LIKE',
                    'AND',
                    $scope,
                    'CONCAT'
                );
            } else {
                $sql->addWhereOpr(
                    SQL::newField(implode(',', $columns), null, false),
                    '%' . addcslashes($word, '%_\\') . '%',
                    'LIKE',
                    'AND',
                    $scope,
                    'CONCAT'
                );
            }
        }
    }

    //-----
    // tag

    /**
     * タグの特定フィールドを指定して，昇順または降順で並び替えます
     *
     * [example]
     * name-desc   : 名前降順
     *
     * ACMS_Filter::tagOrder($SQL, 'name-desc');
     *
     * @param SQL_Select $SQL
     * @param string $order asc|desc
     * @param null $scope
     * @return void
     * @param-out SQL_Select $SQL
     */
    public static function tagOrder(&$SQL, $order, $scope = null)
    {
        list($field, $order) = explode('-', $order);
        $SQL->addOrder('tag_' . $field, $order);
    }
    /**
     * タグの特定フィールドを指定して，昇順または降順で並び替えます
     *
     * [example]
     * name-desc   : 名前降順
     *
     * ACMS_Filter::tagOrder($SQL, 'name-desc');
     *
     * @param SQL_Select $SQL
     * @param string $order asc|desc
     * @param null $scope
     * @return void
     * @param-out SQL_Select $SQL
     */
    public static function mediaTagOrder(&$SQL, $order, $scope = null)
    {
        list($field, $order) = explode('-', $order);
        $SQL->addOrder('media_tag_' . $field, $order);
    }

    //-----
    // media

    /**
     * メディアの特定フィールドを指定して，昇順または降順で並び替えます
     *
     * [example]
     * name-desc   : 名前降順
     *
     * ACMS_Filter::mediaOrder($SQL, 'name-desc');
     *
     * @param SQL_Select $SQL
     * @param string $order asc|desc
     * @param null $scope
     * @return void
     * @param-out SQL_Select $SQL
     */
    public static function mediaOrder(&$SQL, $order, $scope = null)
    {
        list($field, $order) = explode('-', $order);

        if ($field === 'last_modified') {
            $field = 'update_date';
        }
        if ($field === 'media_datetime') {
            $field = 'upload_date';
        }
        if ($field === 'file_size') {
            $SQL->addOrder(SQL::newFunction("media_{$field}", 'ABS'), $order);
            return;
        }
        $SQL->addOrder('media_' . $field, $order);
    }

    /**
     * メディアテーブルから，指定されたキーワードで全文検索します
     *
     * ACMS_Filter::mediaKeyword($SQL, 'first second third keywords');
     *
     * @template T of \SQL_Where
     * @param T $sql
     * @param string $keyword
     * @param string|null $scope
     * @param string[] $columns
     * @return void
     * @param-out T $sql
     */
    public static function mediaKeyword(&$sql, $keyword, $scope = null, $columns = [])
    {
        $keywords = self::splitKeyword($keyword);
        $columns = array_map(function ($column) {
            return "`{$column}`";
        }, $columns);

        foreach ($keywords as $word) {
            if (substr($word, 0, 1) === '-') {
                $sql->addWhereOpr(
                    SQL::newField(implode(',', $columns), null, false),
                    '%' . addcslashes(substr($word, 1), '%_\\') . '%',
                    'NOT LIKE',
                    'AND',
                    $scope,
                    'CONCAT'
                );
            } else {
                $sql->addWhereOpr(
                    SQL::newField(implode(',', $columns), null, false),
                    '%' . addcslashes($word, '%_\\') . '%',
                    'LIKE',
                    'AND',
                    $scope,
                    'CONCAT'
                );
            }
        }
    }

    //-------
    // field

    /**
     * fieldテーブルを，指定されたフィールドで検索します
     *
     * ACMS_Filter::fieldList($SQL, $Field);
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param Field_Search $Field
     * @return void
     * @param-out T $SQL
     */
    public static function fieldList(&$SQL, $Field)
    {
        ACMS_Filter::_field($SQL, $Field);
    }

    //--------
    // common

    /**
     * 指定したフィールド名のWhere句を組み立てます。
     * フィールドの結合子にemが含まれている場合は，falseを返します。
     * @param \SQL_Where &$Where
     * @param Field_Search $Field
     * @param string $fd フィールド名
     * @return void
     * @param-out \SQL_Where $Where
     */
    private static function _field_where(&$Where, $Field, $fd)
    {
        $filterConditions = \FilterCondition::fromFieldSearch($Field, $fd);
        foreach ($filterConditions as $filterCondition) {
            $Where->addWhereOpr(
                'field_value',
                $filterCondition->getValue(),
                $filterCondition->getOperator(),
                $filterCondition->getGlue()
            );
        }
    }

    /**
     * field による絞り込みを行います
     *
     * @template T of \SQL_Where
     * @param T &$SQL
     * @param Field_Search $Field
     * @param 'field_eid'|'field_cid'|'field_bid'|'field_uid'|null $fieldKey
     * @param string|null $tableKey 結合先のテーブルのキー
     * @param string|null $bScp 結合先のエイリアス名
     * @return string[] ソートに利用するフィールド名の配列
     * @param-out T $SQL
     */
    private static function _field(&$SQL, $Field, $fieldKey = null, $tableKey = null, $bScp = null)
    {
        $sortFields = []; // ソートに利用するフィールド名の配列
        $unionAry   = []; // UNION するための SQL_Select オブジェクトの配列
        $emptyAry   = []; // 結合子にemが指定されているフィールド名の配列
        $sort       = false;

        foreach ($Field->listFields() as $j => $fd) {
            $Where = SQL::newWhere();
            if ($Field->hasEmptyOperator($fd)) {
                // フィールド名（$fd）に対してemが指定されている場合は，処理をスキップ
                $emptyAry[] = $fd;
                continue;
            }
            ACMS_Filter::_field_where($Where, $Field, $fd);

            if (
                $Where->_wheres &&
                !!$fieldKey &&
                !!$tableKey
            ) {
                $SUB = SQL::newSelect('field');
                $SUB->addSelect($fieldKey);
                if (!$sort) {
                    $sort = true;
                    $SUB->addSelect('field_value', 'strfield_sort');
                    $SUB->addSelect(SQL::newField('`field_value` + 0', null, false), 'intfield_sort');
                    $sortFields[] = 'strfield_sort';
                    $sortFields[] = 'intfield_sort';
                } else {
                    $SUB->addSelect('field_value', 'strfield_sort_' . ($j + 1));
                    $SUB->addSelect(SQL::newField('`field_value` + 0', null, false), 'intfield_sort_' . ($j + 1));
                    $sortFields[] = 'strfield_sort_' . ($j + 1);
                    $sortFields[] = 'intfield_sort_' . ($j + 1);
                }
                $SUB->addWhereOpr('field_key', $fd);
                $SUB->addWhere($Where);

                if (empty($j)) {
                    $unionAry[] = clone $SUB;
                } else {
                    $separator = $Field->getSeparator($fd);
                    if ($separator === 'or') {
                        $unionAry[] = clone $SUB;
                        array_pop($sortFields);
                        array_pop($sortFields);
                    } else {
                        $uniouCount = count($unionAry);
                        if ($uniouCount > 1) {
                            $UNION = SQL::newSelect($unionAry[0], 'field_union' . $j);
                            for ($i = 1; $i < $uniouCount; $i++) {
                                $UNION->addUnion($unionAry[$i]);
                            }
                            $SQL->addInnerJoin($UNION, $fieldKey, $tableKey, 'field' . $j, $bScp);
                        } elseif ($uniouCount > 0) {
                            $SQL->addInnerJoin($unionAry[0], $fieldKey, $tableKey, 'field' . $j, $bScp);
                        }

                        $unionAry   = [];
                        $unionAry[] = clone $SUB;
                    }
                }
            } else {
                $Where->addWhereOpr('field_key', $fd);
                $SQL->addWhere($Where);
            }
        }
        $unionCount = count($unionAry);
        if ($fieldKey !== null && $tableKey !== null) {
            if ($unionCount > 1) {
                $UNION = SQL::newSelect($unionAry[0], 'field_union_end');
                for ($i = 1; $i < $unionCount; $i++) {
                    $UNION->addUnion($unionAry[$i]);
                }
                $SQL->addInnerJoin($UNION, $fieldKey, $tableKey, 'field_end', $bScp);
            } elseif ($unionCount > 0) {
                $SQL->addInnerJoin($unionAry[0], $fieldKey, $tableKey, 'field_end', $bScp);
            }
        }

        //-------
        // empty
        if (!empty($emptyAry) && $fieldKey !== null && $tableKey !== null) {
            $temp = '_' . substr(base_convert(md5(uniqid()), 16, 36), 0, 8);
            $NOT_EXISTS = SQL::newSelect('field');
            $NOT_EXISTS->setSelect($fieldKey, null, null, 'DISTINCT');
            $NOT_EXISTS->addWhereIn('field_key', $emptyAry);
            $NOT_EXISTS->addWhereOpr('field_value', '', '<>');
            $NOT_EXISTS_WHERE = SQL::newWhere();
            $NOT_EXISTS_WHERE->addWhereOpr($fieldKey, null);
            $SQL->addLeftJoin($NOT_EXISTS, $fieldKey, $tableKey, $temp);
            $SQL->addWhereOpr($fieldKey, null, '=', 'AND', $temp);
        }

        return $sortFields;
    }

    /**
     * _keyword
     *
     * @template T of \SQL_Where
     * @param T $SQL
     * @param string $keyword
     * @param 'fulltext_eid'|'fulltext_cid'|'fulltext_bid'|'fulltext_uid' $fulltextKey
     * @param string $tableKey 結合先のテーブルのキー
     * @return void
     * @param-out T $SQL
     */
    private static function _keyword(&$SQL, $keyword, $fulltextKey, $tableKey)
    {
        if (empty($keyword)) {
            return;
        }

        $keyword = addcslashes($keyword, '%_');
        $aryWord = self::splitKeyword($keyword);
        if (count($aryWord) === 0) {
            return;
        }

        //----------------------
        // NGRAM & BOOLEAN MODE
        if (config('ngram')) {
            $against    = '';
            foreach ($aryWord as $word) {
                $operator   = '+';
                if (substr($word, 0, 1) === '-') {
                    $operator   = '-';
                    $word       = substr($word, 1);
                }
                $aryToken   = ngram($word, config('ngram'));
                $against    .= ' ' . $operator . '(+' . join(' +', $aryToken) . ')';
            }

            $ngramSQL   = SQL::newSelect('fulltext');
            $ngramSQL->setSelect($fulltextKey);
            $ngramSQL->addWhere('MATCH ( fulltext_ngram ) AGAINST (' . "'" . $against . "'" . ' IN BOOLEAN MODE)');
            $SQL->addInnerJoin($ngramSQL, $fulltextKey, $tableKey, 'ft');
        //-----------
        // LIKE MODE
        } else {
            foreach ($aryWord as $word) {
                if (substr($word, 0, 1) === '-') {
                    $word   = substr($word, 1);
                    $SQL->addWhereOpr('fulltext_value', '%' . $word . '%', 'NOT LIKE');
                } else {
                    $SQL->addWhereOpr('fulltext_value', '%' . $word . '%', 'LIKE');
                }
            }
            $SQL->addLeftJoin('fulltext', $fulltextKey, $tableKey);
        }
    }

    /**
     * キーワードの分割する
     *
     * @param string $keyword
     * @return string[]
     */
    private static function splitKeyword(string $keyword): array
    {
        $keywords = preg_split('/(　|\s)+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY);
        if ($keywords === false) {
            return [];
        }
        return $keywords;
    }

    /**
     * @template T of \SQL_Where
     * @param T &$sql
     * @param Field_Search $fieldSearch
     * @param string $prefix カラム名のプレフィックス
     * @param string|null $scope
     * @return void
     * @param-out T $sql
     */
    public static function columnByField(
        SQL_Where &$sql,
        Field_Search $fieldSearch,
        $prefix = '',
        $scope = null
    ): void {
        /** @var array<string, \FilterCondition[]> */
        $map = [];
        foreach ($fieldSearch->listFields() as $fieldName) {
            $conditions = \FilterCondition::fromFieldSearch($fieldSearch, $fieldName);
            if (count($conditions) > 0) {
                $map += [$fieldName => $conditions];
            }
        }
        if (empty($map)) {
            return;
        }
        $where = SQL::newWhere();
        foreach ($map as $fieldName => $conditions) {
            foreach ($conditions as $condition) {
                $where->addWhereOpr(
                    $prefix . $fieldName,
                    $condition->getValue(),
                    $condition->getOperator(),
                    $condition->getGlue(),
                    $scope
                );
            }
        }
        $sql->addWhere($where);
    }
}
