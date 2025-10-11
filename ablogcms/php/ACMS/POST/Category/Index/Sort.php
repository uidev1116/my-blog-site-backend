<?php

class ACMS_POST_Category_Index_Sort extends ACMS_POST
{
    function post()
    {
        if (!sessionWithCompilation()) {
            AcmsLogger::warning('権限がないため、指定されたカテゴリーの並び順変更に失敗しました');
            die403();
        }
        if (!empty($_POST['checks']) and is_array($_POST['checks'])) {
            $DB = DB::singleton(dsn());
            $targetCIDs = [];
            foreach ($_POST['checks'] as $cid) {
                if (!$cid = idval($cid)) {
                    continue;
                }
                if (BID <> ACMS_RAM::categoryBlog($cid)) {
                    continue;
                }
                if (!$toSort = idval($this->Post->get('sort-' . $cid))) {
                    continue;
                }
                if (1 > $toSort) {
                    continue;
                }

                $pid = ACMS_RAM::categoryParent($cid);
                if (is_null($pid)) {
                    continue;
                }

                $SQL    = SQL::newSelect('category');
                $SQL->setSelect('category_sort');
                $SQL->addWhereOpr('category_parent', $pid);
                $SQL->addWhereOpr('category_blog_id', BID);
                $SQL->setOrder('category_sort', 'DESC');
                $SQL->setLimit(1);
                $max    = $DB->query($SQL->get(dsn()), 'one');
                if (1 >= $max) {
                    continue;
                }
                if ($max < $toSort) {
                    continue;
                }

                $SQL    = SQL::newSelect('category');
                $SQL->addSelect('category_left');
                $SQL->addSelect('category_right');
                $SQL->addWhereOpr('category_sort', $toSort);
                $SQL->addWhereOpr('category_parent', $pid);
                $SQL->addWhereOpr('category_blog_id', BID);
                $SQL->setLimit(1);
                if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                    die500('toSort object is not found');
                }
                $toLeft     = intval($row['category_left']);
                $toRight    = intval($row['category_right']);

                $SQL    = SQL::newSelect('category');
                $SQL->addSelect('category_left');
                $SQL->addSelect('category_right');
                $SQL->addSelect('category_sort');
                $SQL->addWhereOpr('category_id', $cid);
                $SQL->addWhereOpr('category_blog_id', BID);
                if (!$row = $DB->query($SQL->get(dsn()), 'row')) {
                    die500('fromSort object is not found');
                }
                $fromSort   = intval($row['category_sort']);
                $fromLeft   = intval($row['category_left']);
                $fromRight  = intval($row['category_right']);

                $gap    = ($fromRight - $fromLeft) + 1;

                $SQL    = SQL::newUpdate('category');
                //$SQL->addWhereOpr('category_parent', $pid);
                $SQL->addWhereOpr('category_blog_id', BID);
                if ($fromRight > $toRight) {
                    //---------
                    // upper
                    $delta  = $fromLeft - $toLeft;

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('category_left', $fromLeft, $fromRight),
                        SQL::newOpr('category_left', $delta, '-')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('category_left', $fromLeft, '<');
                    $Where->addWhereOpr('category_left', $toLeft, '>=');
                    $Case->add($Where, SQL::newOpr('category_left', $gap, '+'));
                    $Case->setElse(SQL::newField('category_left'));
                    $SQL->addUpdate('category_left', $Case);

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('category_right', $fromLeft, $fromRight),
                        SQL::newOpr('category_right', $delta, '-')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('category_right', $fromLeft, '<');
                    $Where->addWhereOpr('category_right', $toLeft, '>=');
                    $Case->add($Where, SQL::newOpr('category_right', $gap, '+'));
                    $Case->setElse(SQL::newField('category_right'));
                    $SQL->addUpdate('category_right', $Case);

                    $Case   = SQL::newCase();
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('category_sort', $fromSort);
                    $Where->addWhereOpr('category_parent', $pid);
                    $Case->add($Where, $toSort);
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('category_sort', $toSort, '>=');
                    $Where->addWhereOpr('category_sort', $fromSort, '<');
                    $Where->addWhereOpr('category_parent', $pid);
                    $Case->add($Where, SQL::newOpr('category_sort', 1, '+'));
                    $Case->setElse(SQL::newField('category_sort'));
                    $SQL->addUpdate('category_sort', $Case);
                } else {
                    //-------
                    // lower
                    $delta  = $toRight - $fromRight;

                    $Case   = SQL::newCase();
                    $Case->add(
                        SQL::newOprBw('category_left', $fromLeft, $fromRight),
                        SQL::newOpr('category_left', $delta, '+')
                    );
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('category_left', $fromRight, '>');
                    $Where->addWhereOpr('category_left', $toRight, '<=');
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
                    $Where->addWhereOpr('category_right', $toRight, '<=');
                    $Case->add($Where, SQL::newOpr('category_right', $gap, '-'));
                    $Case->setElse(SQL::newField('category_right'));
                    $SQL->addUpdate('category_right', $Case);

                    $Case   = SQL::newCase();
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('category_sort', $fromSort);
                    $Where->addWhereOpr('category_parent', $pid);
                    $Case->add($Where, $toSort);
                    $Where  = SQL::newWhere();
                    $Where->addWhereOpr('category_sort', $fromSort, '>');
                    $Where->addWhereOpr('category_sort', $toSort, '<=');
                    $Where->addWhereOpr('category_parent', $pid);
                    $Case->add($Where, SQL::newOpr('category_sort', 1, '-'));
                    $Case->setElse(SQL::newField('category_sort'));
                    $SQL->addUpdate('category_sort', $Case);
                }
                $DB->query($SQL->get(dsn()), 'exec');

                $targetCIDs[] = $cid;
            }
            AcmsLogger::info('指定されたカテゴリーの並び順を変更', [
                'targetCIDs' => implode(',', $targetCIDs),
            ]);
        }
        Cache::flush('temp');

        return $this->Post;
    }
}
