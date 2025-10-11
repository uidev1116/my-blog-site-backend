<?php

class ACMS_POST_Blog_Index_Parent extends ACMS_POST_Blog
{
    function post()
    {
        $DB = DB::singleton(dsn());

        if (!sessionWithAdministration()) {
            die403();
        }
        if (!$toPid = idval($this->Post->get('parent'))) {
            die500();
        }
        if (!isBlogAncestor($toPid, SBID, true)) {
            die500();
        }
        if (!empty($_POST['checks']) and is_array($_POST['checks'])) {
            $aryBid = [];
            foreach ($_POST['checks'] as $bid) {
                if (!$bid = idval($bid)) {
                    continue;
                }
                if (isBlogAncestor($toPid, $bid, true)) {
                    continue;
                }

                //-----
                // from:pid,left,right,sort
                $SQL    = SQL::newSelect('blog');
                $SQL->addSelect('blog_left');
                $SQL->addSelect('blog_right');
                $SQL->addSelect('blog_parent');
                $SQL->addSelect('blog_sort');
                $SQL->addWhereOpr('blog_id', $bid);
                if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                    die();
                }
                $fromLeft   = intval($row['blog_left']);
                $fromRight  = intval($row['blog_right']);
                $fromPid    = intval($row['blog_parent']);
                $fromSort   = intval($row['blog_sort']);

                //-----------
                // same parent
                if ($toPid == $fromPid) {
                    continue;
                }

                //-----------------
                // toLeft, toRight
                $SQL    = SQL::newSelect('blog');
                $SQL->addSelect('blog_left');
                $SQL->addSelect('blog_right');
                $SQL->addWhereOpr('blog_id', $toPid);
                if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                    die();
                }
                $toLeft     = intval($row['blog_left']);
                $toRight    = intval($row['blog_right']);

                //-------
                // toSort
                $SQL    = SQL::newSelect('blog');
                $SQL->setSelect('blog_sort');
                $SQL->addWhereOpr('blog_parent', $toPid);
                $SQL->setOrder('blog_sort', 'DESC');
                $SQL->setLimit(1);
                $toSort = intval($DB->query($SQL->get(dsn()), 'one')) + 1;

                //-----
                // gap
                $gap    = ($fromRight - $fromLeft) + 1;

                //-------
                // align
                $SQL    = SQL::newUpdate('blog');
                if ($fromRight > $toRight) {
                    //------
                    // upper
                    $delta  = $fromLeft - $toRight;

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('blog_left', $fromLeft, $fromRight),
                        SQL::newOpr('blog_left', $delta, '-')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_left', $toRight, '>=');
                    $Where->addWhereOpr('blog_left', $fromLeft, '<');
                    $Case->add($Where, SQL::newOpr('blog_left', $gap, '+'));
                    $Case->setElse(SQL::newField('blog_left'));
                    $SQL->addUpdate('blog_left', $Case);

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('blog_right', $fromLeft, $fromRight),
                        SQL::newOpr('blog_right', $delta, '-')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_right', $toRight, '>=');
                    $Where->addWhereOpr('blog_right', $fromLeft, '<');
                    $Case->add($Where, SQL::newOpr('blog_right', $gap, '+'));
                    $Case->setElse(SQL::newField('blog_right'));
                    $SQL->addUpdate('blog_right', $Case);
                } else {
                    //------
                    // lower
                    $delta  = $toRight - $fromRight - 1;

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('blog_left', $fromLeft, $fromRight),
                        SQL::newOpr('blog_left', $delta, '+')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_left', $fromRight, '>');
                    $Where->addWhereOpr('blog_left', $toRight, '<');
                    $Case->add($Where, SQL::newOpr('blog_left', $gap, '-'));
                    $Case->setElse(SQL::newField('blog_left'));
                    $SQL->addUpdate('blog_left', $Case);

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('blog_right', $fromLeft, $fromRight),
                        SQL::newOpr('blog_right', $delta, '+')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_right', $fromRight, '>');
                    $Where->addWhereOpr('blog_right', $toRight, '<');
                    $Case->add($Where, SQL::newOpr('blog_right', $gap, '-'));
                    $Case->setElse(SQL::newField('blog_right'));
                    $SQL->addUpdate('blog_right', $Case);
                }
                $DB->query($SQL->get(dsn()), 'exec');

                //-------
                // sort
                $SQL    = SQL::newUpdate('blog');
                $SQL->setUpdate('blog_sort', SQL::newOpr('blog_sort', 1, '-'));
                $SQL->addWhereOpr('blog_sort', $fromSort, '>');
                $SQL->addWhereOpr('blog_parent', $fromPid);
                $DB->query($SQL->get(dsn()), 'exec');

                //--------
                // update
                $SQL    = SQL::newUpdate('blog');
                $SQL->addUpdate('blog_parent', $toPid);
                $SQL->addUpdate('blog_sort', $toSort);
                $SQL->addWhereOpr('blog_id', $bid);
                $DB->query($SQL->get(dsn()), 'exec');

                Cache::flush('temp');
                $this->Post->set('success', 'parent');

                $aryBid[] = $bid;
            }
            AcmsLogger::info('指定されたブログの親ブログIDを「' . $toPid . '」に変更', [
                'targetBIDs' => implode(',', $aryBid),
            ]);
        } else {
            $this->Post->set('error', 'parent_1');
        }

        return $this->Post;
    }
}
