<?php

use Acms\Services\Facades\Category;

class ACMS_POST_Category_Delete extends ACMS_POST_Category
{
    function isEntryExists($cid)
    {
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('entry');
        $SQL->setSelect('entry_id');
        $SQL->addWhereOpr('entry_category_id', $cid);
        $SQL->setLimit(1);
        return !!$DB->query($SQL->get(dsn()), 'one');
    }

    function isSubCategoryExists($cid)
    {
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('category');
        $SQL->setSelect('category_id');
        $SQL->addWhereOpr('category_parent', $cid);
        $SQL->setLimit(1);
        return !!$DB->query($SQL->get(dsn()), 'one');
    }

    function entryUnsetCategory($cid)
    {
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newUpdate('entry');
        $SQL->addUpdate('entry_category_id', null);
        $SQL->addUpdate('entry_category_sort', 1);
        $SQL->addWhereOpr('entry_category_id', $cid);
        return $DB->query($SQL->get(dsn()), 'exec');
    }

    public function post()
    {
        /** @var int<1, max>|null $categoryId */
        $categoryId = CID;
        $DB     = DB::singleton(dsn());

        $this->Post->reset(true);

        $this->Post->setMethod('category', 'operable', !is_null($categoryId) && Category::canDelete(BID));
        $this->Post->setMethod('category', 'subCategoryExists', !$this->isSubCategoryExists($categoryId));

        $this->Post->validate();

        if ($this->Post->isValidAll()) {
            $this->entryUnsetCategory(CID);

            $SQL    = SQL::newUpdate('category');

            $left   = ACMS_RAM::categoryLeft(CID);
            $right  = ACMS_RAM::categoryRight(CID);
            $sort   = ACMS_RAM::categorySort(CID);
            $pid    = ACMS_RAM::categoryParent(CID);

            $Case   = SQL::newCase();
            $Case->add(SQL::newOpr('category_left', $right, '>'), SQL::newOpr('category_left', 2, '-'));
            $Case->add(SQL::newOpr('category_left', $left, '>'), SQL::newOpr('category_left', 1, '-'));
            $Case->setElse(SQL::newField('category_left'));
            $SQL->addUpdate('category_left', $Case);

            $Case   = SQL::newCase();
            $Case->add(SQL::newOpr('category_right', $right, '>'), SQL::newOpr('category_right', 2, '-'));
            $Case->add(SQL::newOpr('category_right', $left, '>'), SQL::newOpr('category_right', 1, '-'));
            $Case->setElse(SQL::newField('category_right'));
            $SQL->addUpdate('category_right', $Case);

            $Case   = SQL::newCase();
            $Where  = SQL::newWhere();
            $Where->addWhereOpr('category_parent', $pid);
            $Where->addWhereOpr('category_sort', $sort, '>');
            $Case->add($Where, SQL::newOpr('category_sort', 1, '-'));
            $Case->setElse(SQL::newField('category_sort'));
            $SQL->addUpdate('category_sort', $Case);

            $SQL->addWhereOpr('category_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            $SQL    = SQL::newDelete('category');
            $SQL->addWhereOpr('category_id', CID);
            $DB->query($SQL->get(dsn()), 'exec');

            Common::saveField('cid', CID);
            Cache::flush('temp');

            deleteWorkflow(BID, CID);

            $this->Post->set('edit', 'delete');

            AcmsLogger::info('「' . ACMS_RAM::categoryName(CID) . '」カテゴリーの削除をしました');
        } else {
            AcmsLogger::info('「' . ACMS_RAM::categoryName(CID) . '」カテゴリーの削除に失敗しました', [
                'Post' => $this->Post->_aryV,
            ]);
        }

        return $this->Post;
    }
}
