<?php

class ACMS_POST_Entry_Index_TrashAll extends ACMS_POST_Trash
{
    function post()
    {
        $this->Post->reset(true);
        $this->Post->setMethod('entry', 'operative', Entry::canDeleteAllFromTrash(BID, CID));
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);
            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newSelect('entry');
            $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
            ACMS_Filter::blogTree($SQL, BID, 'descendant-or-self');
            $SQL->addSelect('entry_id');
            $SQL->addWhereOpr('entry_status', 'trash');

            $all = $DB->query($SQL->get(dsn()), 'all');
            $targetEIDs = [];
            foreach ($all as $entry) {
                $eid = $entry['entry_id'];
                Entry::entryDelete($eid);
                Entry::revisionDelete($eid);
                $targetEIDs[] = $eid;
            }
            AcmsLogger::info('エントリーのゴミ箱を空にしました', [
                'targetEIDs' => $targetEIDs,
            ]);
        } else {
            AcmsLogger::info('エントリーのゴミ箱を空にできませんでした');
        }
        return $this->Post;
    }
}
