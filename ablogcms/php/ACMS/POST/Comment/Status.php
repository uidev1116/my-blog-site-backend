<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Comment_Status extends ACMS_POST_Comment
{
    public function _post($status)
    {
        $this->Post->reset(true);
        $this->Post->setMethod('comment', 'isOperable', (1
            and !!CMID
            and Login::isLoggedIn()
            and sessionWithContribution()
            and (0
                or sessionWithCompilation()
                or ACMS_RAM::entryStatus(EID) == SUID
                or ACMS_RAM::commentUser(CMID) == SUID
            )
        ));
        $this->Post->validate();

        if ($this->Post->isValidAll()) {
            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newUpdate('comment');
            $SQL->setUpdate('comment_status', $status);
            $SQL->addWhereOpr('comment_left', ACMS_RAM::commentLeft(CMID), '>=');
            $SQL->addWhereOpr('comment_right', ACMS_RAM::commentRight(CMID), '<=');
            $SQL->addWhereOpr('comment_entry_id', EID);
            $SQL->addWhereOpr('comment_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            $statusName = '';
            if ($status === 'awaiting') {
                $statusName = '承認待ち';
            }
            if ($status === 'close') {
                $statusName = '非公開';
            }
            if ($status === 'open') {
                $statusName = '公開';
            }
            AcmsLogger::info('コメントのステータスを「' . $statusName . '」に変更しました');

            $this->redirect(acmsLink([
                'bid'       => BID,
                'cid'       => CID,
                'eid'       => EID,
                'cmid'      => CMID,
                'fragment'  => 'comment-' . CMID,
            ]));
        }

        return $this->Post;
    }
}
