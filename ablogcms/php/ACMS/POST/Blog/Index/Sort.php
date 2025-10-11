<?php

class ACMS_POST_Blog_Index_Sort extends ACMS_POST_Blog
{
    function post()
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('blog');
        $SQL->setSelect('blog_sort');
        $SQL->addWhereOpr('blog_parent', BID);
        $SQL->setOrder('blog_sort', 'DESC');
        $SQL->setLimit(1);
        if (!$max = $DB->query($SQL->get(dsn()), 'one')) {
            die500();
        }
        if ((1 < $max) and !empty($_POST['checks']) and is_array($_POST['checks'])) {
            $aryBid = [];
            foreach ($_POST['checks'] as $bid) {
                if (!$bid = idval($bid)) {
                    continue;
                }
                if (!$toSort = idval($this->Post->get('sort-' . $bid))) {
                    continue;
                }
                if (1 > $toSort) {
                    continue;
                }
                if ($max < $toSort) {
                    continue;
                }
                if (BID <> ACMS_RAM::blogParent($bid)) {
                    continue;
                }

                $SQL    = SQL::newSelect('blog');
                $SQL->addSelect('blog_left');
                $SQL->addSelect('blog_right');
                $SQL->addWhereOpr('blog_parent', BID);
                $SQL->addWhereOpr('blog_sort', $toSort);
                $SQL->setLimit(1);
                if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                    die500('toSort object is not found');
                }
                $toLeft     = intval($row['blog_left']);
                $toRight    = intval($row['blog_right']);

                $SQL    = SQL::newSelect('blog');
                $SQL->addSelect('blog_left');
                $SQL->addSelect('blog_right');
                $SQL->addSelect('blog_sort');
                $SQL->addWhereOpr('blog_id', $bid);
                if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                    die500('fromSort object is not found');
                }
                $fromSort   = intval($row['blog_sort']);
                $fromLeft   = intval($row['blog_left']);
                $fromRight  = intval($row['blog_right']);

                $gap    = ($fromRight - $fromLeft) + 1;

                $SQL    = SQL::newUpdate('blog');
                if ($fromRight > $toRight) {
                    //-------
                    // upper
                    $delta  = $fromLeft - $toLeft;

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('blog_left', $fromLeft, $fromRight),
                        SQL::newOpr('blog_left', $delta, '-')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_left', $fromLeft, '<');
                    $Where->addWhereOpr('blog_left', $toLeft, '>=');
                    $Case->add($Where, SQL::newOpr('blog_left', $gap, '+'));
                    $Case->setElse(SQL::newField('blog_left'));
                    $SQL->addUpdate('blog_left', $Case);

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('blog_right', $fromLeft, $fromRight),
                        SQL::newOpr('blog_right', $delta, '-')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_right', $fromLeft, '<');
                    $Where->addWhereOpr('blog_right', $toLeft, '>=');
                    $Case->add($Where, SQL::newOpr('blog_right', $gap, '+'));
                    $Case->setElse(SQL::newField('blog_right'));
                    $SQL->addUpdate('blog_right', $Case);

                    $Case   = SQL::newCase();
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_sort', $fromSort);
                    $Where->addWhereOpr('blog_parent', BID);
                    $Case->add($Where, $toSort);
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_sort', $toSort, '>=');
                    $Where->addWhereOpr('blog_sort', $fromSort, '<');
                    $Where->addWhereOpr('blog_parent', BID);
                    $Case->add($Where, SQL::newOpr('blog_sort', 1, '+'));
                    $Case->setElse(SQL::newField('blog_sort'));
                    $SQL->addUpdate('blog_sort', $Case);
                } else {
                    //-------
                    // lower
                    $delta  = $toRight - $fromRight;

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('blog_left', $fromLeft, $fromRight),
                        SQL::newOpr('blog_left', $delta, '+')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_left', $fromRight, '>');
                    $Where->addWhereOpr('blog_left', $toRight, '<=');
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
                    $Where->addWhereOpr('blog_right', $toRight, '<=');
                    $Case->add($Where, SQL::newOpr('blog_right', $gap, '-'));
                    $Case->setElse(SQL::newField('blog_right'));
                    $SQL->addUpdate('blog_right', $Case);

                    $Case   = SQL::newCase();
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_sort', $fromSort);
                    $Where->addWhereOpr('blog_parent', BID);
                    $Case->add($Where, $toSort);
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('blog_sort', $fromSort, '>');
                    $Where->addWhereOpr('blog_sort', $toSort, '<=');
                    $Where->addWhereOpr('blog_parent', BID);
                    $Case->add($Where, SQL::newOpr('blog_sort', 1, '-'));
                    $Case->setElse(SQL::newField('blog_sort'));
                    $SQL->addUpdate('blog_sort', $Case);
                }
                $DB->query($SQL->get(dsn()), 'exec');

                Cache::flush('temp');
                $this->Post->set('success', 'sort');

                $aryBid[] = $bid;
            }
            AcmsLogger::info('指定されたブログの並び順を変更しました', [
                'targetBIDs' => implode(',', $aryBid),
            ]);
        } else {
            $this->Post->set('error', 'sort_1');
        }
        return $this->Post;
    }
}
