<?php

class ACMS_POST_Entry_Index_TrashRestore extends ACMS_POST_Trash
{
    function post()
    {
        $this->Post->reset(true);
        $this->Post->setMethod('checks', 'required');
        $this->Post->setMethod('entry', 'operative', Entry::canBulkTrashRestore(BID, CID));
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);
            $targetEIDs = [];
            foreach ($this->Post->getArray('checks') as $eid) {
                $id = preg_split('@:@', $eid, -1, PREG_SPLIT_NO_EMPTY);
                $eid = intval($id[1]);
                if ($eid < 1) {
                    continue;
                }
                if (!Entry::canTrashRestore($eid)) {
                    continue;
                }
                $this->restore($eid);
                $targetEIDs[] = $eid;
            }
            AcmsLogger::info('選択したゴミ箱のエントリーを復元しました', [
                'targetEIDs' => $targetEIDs,
            ]);
        } else {
            AcmsLogger::info('選択したゴミ箱のエントリーを復元できませんでした');
        }
        return $this->Post;
    }
}
