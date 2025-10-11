<?php

class ACMS_POST_Comment_Delete extends ACMS_POST_Comment
{
    function post()
    {
        if (!CMID) {
            die500();
        }
        $DB = DB::singleton(dsn());

        if (!$this->validatePassword()) {
            return $this->Post;
        }
        $step       = $this->Post->get('step');
        $nextstep   = $this->Post->get('nextstep');
        $redirect   = $this->Post->get('redirect');

        $l  = ACMS_RAM::commentLeft(CMID); // @phpstan-ignore-line
        $r  = ACMS_RAM::commentRight(CMID); // @phpstan-ignore-line
        $gap = $r - $l + 1;

        $SQL = SQL::newDelete('comment');
        $SQL->addWhereOpr('comment_left', $l, '>=');
        $SQL->addWhereOpr('comment_right', $r, '<=');
        $SQL->addWhereOpr('comment_entry_id', EID);
        $SQL->addWhereOpr('comment_blog_id', BID);
        $q = $SQL->get(dsn());
        $DB->query($q, 'exec');

        $SQL    = SQL::newUpdate('comment');
        $SQL->setUpdate('comment_left', SQL::newOpr('comment_left', $gap, '-'));
        $SQL->addWhereOpr('comment_left', $r, '>');
        $SQL->addWhereOpr('comment_entry_id', EID);
        $SQL->addWhereOpr('comment_blog_id', BID);
        $q = $SQL->get(dsn());
        $DB->query($q, 'exec');

        $SQL    = SQL::newUpdate('comment');
        $SQL->setUpdate('comment_right', SQL::newOpr('comment_right', $gap, '-'));
        $SQL->addWhereOpr('comment_right', $r, '>');
        $SQL->addWhereOpr('comment_entry_id', EID);
        $SQL->addWhereOpr('comment_blog_id', BID);
        $q = $SQL->get(dsn());
        $DB->query($q, 'exec');

        AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '」エントリーのコメントを削除しました', [ // @phpstan-ignore-line
            'comment_id' => CMID,
        ]);

        if ($redirect && Common::isSafeUrl($redirect)) {
            $this->redirect($redirect);
        } elseif ($nextstep) {
            $this->Post->set('step', $nextstep);
            $this->Post->set('action', 'delete');
            return $this->Post;
        }
        return $this->Post;
    }
}
