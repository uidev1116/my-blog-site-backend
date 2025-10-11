<?php

class ACMS_POST_Blog_Delete extends ACMS_POST_Blog
{
    function post()
    {
        $this->Post->setMethod('blog', 'subBlogExists', !isBlogGlobal(BID));
        $this->Post->setMethod('blog', 'operable', 1
            and !!BID
            and !!sessionWithAdministration()
            and !!isBlogGlobal(SBID)
            and BID !== SBID);
        $this->Post->validate();

        if ($this->Post->isValidAll()) {
            $l      = ACMS_RAM::blogLeft(BID);
            $r      = ACMS_RAM::blogRight(BID);
            $sort   = ACMS_RAM::blogSort(BID);
            $pid    = ACMS_RAM::blogParent(BID);

            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newUpdate('blog');

            $Case   = SQL::newCase();
            $Case->add(SQL::newOpr('blog_left', $r, '>'), SQL::newOpr('blog_left', 2, '-'));
            $Case->add(SQL::newOpr('blog_left', $l, '>'), SQL::newOpr('blog_left', 1, '-'));
            $Case->setElse(SQL::newField('blog_left'));
            $SQL->addUpdate('blog_left', $Case);

            $Case   = SQL::newCase();
            $Case->add(SQL::newOpr('blog_right', $r, '>'), SQL::newOpr('blog_right', 2, '-'));
            $Case->add(SQL::newOpr('blog_right', $l, '>'), SQL::newOpr('blog_right', 1, '-'));
            $Case->setElse(SQL::newField('blog_right'));
            $SQL->addUpdate('blog_right', $Case);

            $Case   = SQL::newCase();
            $Where  = SQL::newWhere();
            $Where->addWhereOpr('blog_parent', $pid);
            $Where->addWhereOpr('blog_sort', $sort, '>');
            $Case->add($Where, SQL::newOpr('blog_sort', 1, '-'));

            $Case->setElse(SQL::newField('blog_sort'));
            $SQL->addUpdate('blog_sort', $Case);
            $DB->query($SQL->get(dsn()), 'exec');

            $SQL    = SQL::newDelete('blog');
            $SQL->addWhereOpr('blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            foreach (
                [
                    'alias', 'category', 'column', 'comment', 'config', 'config_set', 'dashboard',
                    'entry', 'field', 'form', 'fulltext', 'log_form',
                    'module', 'rule', 'tag', 'user',
                ] as $tb
            ) {
                $SQL    = SQL::newDelete($tb);
                $SQL->addWhereOpr($tb . '_blog_id', BID);
                $DB->query($SQL->get(dsn()), 'exec');
            }

            Cache::flush('temp');
            deleteWorkflow(BID);

            AcmsLogger::info('「' . ACMS_RAM::blogName(BID) . '」ブログを削除しました');

            $this->redirect(acmsLink([
                'bid'   => $pid,
                'admin' => 'blog_edit',
            ]));
        } else {
            AcmsLogger::info('「' . ACMS_RAM::blogName(BID) . '」ブログの削除に失敗しました');
        }

        return $this->Post;
    }
}
