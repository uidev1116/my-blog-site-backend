<?php

class ACMS_POST_Entry_Index_Delete extends ACMS_POST_Entry_Delete
{
    function post()
    {
        $this->Post->reset(true);
        $this->Post->setMethod('checks', 'required');
        $this->Post->setMethod('entry', 'operative', Entry::canBulkDelete(BID, CID));
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);
            $targetEIDs = [];
            foreach ($this->Post->getArray('checks') as $eid) {
                $id = preg_split('@:@', $eid, 2, PREG_SPLIT_NO_EMPTY);
                $eid = intval($id[1]);

                if ($eid <= 0) {
                    continue;
                }
                if (!Entry::canDelete($eid)) {
                    continue;
                }
                $this->delete($eid);
                $targetEIDs[] = $eid;
            }
            AcmsLogger::info('指定されたエントリーを一括削除しました', [
                'targetEIDs' => implode(',', $targetEIDs),
            ]);
        } else {
            AcmsLogger::info('指定されたエントリーの一括削除に失敗しました');
        }

        return $this->Post;
    }
}
