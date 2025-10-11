<?php

class ACMS_Validator_Category extends ACMS_Validator
{
    /**
     * カテゴリーコード重複チェック
     *
     * @param mixed $ccd
     * @param mixed $arg
     * @return bool
     */
    function double($ccd, $arg)
    {
        if (empty($ccd)) {
            return true;
        }

        $scope = isset($arg[0]) ? $arg[0] : 'local';
        $pcid = isset($arg[1]) ? intval($arg[1]) : 0;
        $cid = isset($arg[2]) ? intval($arg[2]) : null;
        $DB = DB::singleton(dsn());

        //-------
        // ブログ
        if (!ACMS_RAM::blogCode(BID)) {
            $domain = ACMS_RAM::blogDomain(BID);
            $SQL = SQL::newSelect('blog');
            $SQL->setSelect('blog_id');
            $SQL->addWhereOpr('blog_domain', $domain);
            $SQL->addWhereOpr('blog_code', $ccd);
            if ($DB->query($SQL->get(dsn()), 'one')) {
                return false;
            }
        }

        //-------------
        // 兄弟カテゴリー
        $SQL = SQL::newSelect('category');
        $SQL->setSelect('category_id');
        $SQL->addWhereOpr('category_code', $ccd);
        $SQL->addWhereOpr('category_blog_id', BID);
        if (config('category_order_strict_mode') === 'on') {
            $SQL->addWhereOpr('category_parent', $pcid);
        }
        if (!!($cid = intval($cid))) {
            $SQL->addWhereOpr('category_id', $cid, '<>'); // 自分自身を除く
        }
        if (!!$DB->query($SQL->get(dsn()), 'one')) {
            return false;
        }


        //-----------------------------
        // 先祖ブログのグローバルカテゴリー
        $SQL = SQL::newSelect('category');
        $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'ancestor');
        $SQL->addSelect('category_id');
        $SQL->addWhereOpr('category_code', $ccd);
        $SQL->addWhereOpr('category_scope', 'global');
        if (!!$DB->query($SQL->get(dsn()), 'one')) {
            return false;
        }
        if ('local' == $scope) {
            return true;
        }

        //----------------------------------------
        // 自身がグローバルの場合の子孫ブログのカテゴリー
        $SQL = SQL::newSelect('category');
        $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'descendant');
        $SQL->addSelect('category_id');
        $SQL->addWhereOpr('category_code', $ccd);
        return !$DB->query($SQL->get(dsn()), 'one');
    }

    function _double($val, $cid = null)
    {
        if (empty($val)) {
            return true;
        }
        $DB     = DB::singleton(dsn());

        //------
        // blog
        if (!ACMS_RAM::blogCode(BID)) {
            $domain = ACMS_RAM::blogDomain(BID);
            $SQL    = SQL::newSelect('blog');
            $SQL->setSelect('blog_id');
            $SQL->addWhereOpr('blog_domain', $domain);
            $SQL->addWhereOpr('blog_code', $val);
            if ($DB->query($SQL->get(dsn()), 'one')) {
                return false;
            }
        }

        //----------
        // category
        $SQL    = SQL::newSelect('category');
        $SQL->setSelect('category_id');
        $SQL->addWhereOpr('category_code', $val);
        $SQL->addWhereOpr('category_blog_id', BID);
        if ($cid = idval($cid)) {
            $SQL->addWhereOpr('category_id', $cid, '<>');
        }

        return !$DB->query($SQL->get(dsn()), 'one');
    }

    function status($val)
    {
        if (empty($val)) {
            return true;
        }
        if (!CID) {
            return true;
        }
        if ('open' <> $val) {
            return true;
        }

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('category');
        $SQL->setSelect('category_id');
        $SQL->addWhereOpr('category_blog_id', BID);
        $l  = ACMS_RAM::categoryLeft(CID);
        $r  = ACMS_RAM::categoryRight(CID);
        $SQL->addWhereOpr('category_left', $l, '<');
        $SQL->addWhereOpr('category_right', $r, '>');
        $SQL->addWhereOpr('category_status', 'close');
        $SQL->setLimit(1);

        return !$DB->query($SQL->get(dsn()), 'one');
    }
}

class ACMS_POST_Category extends ACMS_POST
{
    protected $workflowData = false;

    function changeParentCategory($cid, $toPid)
    {
        if (!$cid = idval($cid)) {
            return false;
        }
        if ($toPid == $cid) {
            return false;
        }
        if (BID <> ACMS_RAM::categoryBlog($cid)) {
            return false;
        }

        $DB = DB::singleton(dsn());

        //-----------------------------
        // from:left, right, pid, sort
        $SQL    = SQL::newSelect('category');
        $SQL->addSelect('category_left');
        $SQL->addSelect('category_right');
        $SQL->addSelect('category_parent');
        $SQL->addSelect('category_sort');
        $SQL->addWhereOpr('category_id', $cid);
        $SQL->addWhereOpr('category_blog_id', BID);
        if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
            die500();
        }
        $fromLeft   = intval($row['category_left']);
        $fromRight  = intval($row['category_right']);
        $fromPid    = intval($row['category_parent']);
        $fromSort   = intval($row['category_sort']);

        //-------------
        // same parent
        if ($toPid == $fromPid) {
            return false;
        }

        //------------------------
        // to: left, right, sort
        if (!empty($toPid)) {
            $SQL    = SQL::newSelect('category');
            $SQL->addSelect('category_left');
            $SQL->addSelect('category_right');
            $SQL->addWhereOpr('category_id', $toPid);
            $SQL->addWhereOpr('category_blog_id', BID);
            if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                die500();
            }
            $toLeft     = $row['category_left'];
            $toRight    = $row['category_right'];

            if ($toLeft > $fromLeft and $toRight < $fromRight) {
                return false;
            }

            //-------
            // toSort
            $SQL    = SQL::newSelect('category');
            $SQL->setSelect('category_sort');
            $SQL->addWhereOpr('category_parent', $toPid);
            $SQL->addWhereOpr('category_blog_id', BID);
            $SQL->setOrder('category_sort', 'DESC');
            $SQL->setLimit(1);
            $toSort = intval($DB->query($SQL->get(dsn()), 'one')) + 1;
        } else {
            $SQL    = SQL::newSelect('category');
            $SQL->addSelect('category_right');
            $SQL->addSelect('category_sort');
            $SQL->addWhereOpr('category_blog_id', BID);
            $SQL->setOrder('category_right', 'DESC');
            $SQL->setLimit(1);
            if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                die500();
            }
            $toLeft     = intval($row['category_right']);
            $toRight    = $toLeft   + 1;
            $toSort     = intval($row['category_sort']) + 1;
            ;
        }

        //-----
        // gap
        $gap    = ($fromRight - $fromLeft) + 1;

        //-------
        // align
        $SQL    = SQL::newUpdate('category');
        $SQL->addWhereOpr('category_blog_id', BID);
        if ($fromRight > $toRight) {
            //-------
            // upper
            $delta  = $fromLeft - $toRight;

            $Case   = SQL::newCase();
            $Case->add(
                SQL::newOprBw('category_left', $fromLeft, $fromRight),
                SQL::newOpr('category_left', $delta, '-')
            );
            $Where  = SQL::newWhere();
            $Where->addWhereOpr('category_left', $toRight, '>=');
            $Where->addWhereOpr('category_left', $fromLeft, '<');
            $Case->add($Where, SQL::newOpr('category_left', $gap, '+'));
            $Case->setElse(SQL::newField('category_left'));
            $SQL->addUpdate('category_left', $Case);

            $Case   = SQL::newCase();
            $Case->add(
                SQL::newOprBw('category_right', $fromLeft, $fromRight),
                SQL::newOpr('category_right', $delta, '-')
            );
            $Where  = SQL::newWhere();
            $Where->addWhereOpr('category_right', $toRight, '>=');
            $Where->addWhereOpr('category_right', $fromLeft, '<');
            $Case->add($Where, SQL::newOpr('category_right', $gap, '+'));
            $Case->setElse(SQL::newField('category_right'));
            $SQL->addUpdate('category_right', $Case);
        } else {
            //------
            // lower
            $delta  = $toRight - $fromRight - 1;

            $Case   = SQL::newCase();
            $Case->add(
                SQL::newOprBw('category_left', $fromLeft, $fromRight),
                SQL::newOpr('category_left', $delta, '+')
            );
            $Where  = SQL::newWhere();
            $Where->addWhereOpr('category_left', $fromRight, '>');
            $Where->addWhereOpr('category_left', $toRight, '<');
            $Case->add($Where, SQL::newOpr('category_left', $gap, '-'));
            $Case->setElse(SQL::newField('category_left'));
            $SQL->addUpdate('category_left', $Case);

            $Case   = SQL::newCase();
            $Case->add(
                SQL::newOprBw('category_right', $fromLeft, $fromRight),
                SQL::newOpr('category_right', $delta, '+')
            );
            $Where  = SQL::newWhere();
            $Where->addWhereOpr('category_right', $fromRight, '>');
            $Where->addWhereOpr('category_right', $toRight, '<');
            $Case->add($Where, SQL::newOpr('category_right', $gap, '-'));
            $Case->setElse(SQL::newField('category_right'));
            $SQL->addUpdate('category_right', $Case);
        }
        $DB->query($SQL->get(dsn()), 'exec');

        //--------
        // sort
        $SQL    = SQL::newUpdate('category');
        $SQL->setUpdate('category_sort', SQL::newOpr('category_sort', 1, '-'));
        $SQL->addWhereOpr('category_sort', $fromSort, '>');
        $SQL->addWhereOpr('category_parent', $fromPid);
        $SQL->addWhereOpr('category_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        //--------
        // update
        $SQL    = SQL::newUpdate('category');
        $SQL->addUpdate('category_parent', $toPid);
        $SQL->addUpdate('category_sort', $toSort);
        $SQL->addWhereOpr('category_id', $cid);
        $SQL->addWhereOpr('category_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        ACMS_RAM::category($cid, null);
    }
}
