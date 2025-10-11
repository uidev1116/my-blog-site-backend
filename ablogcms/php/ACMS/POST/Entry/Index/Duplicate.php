<?php

use Acms\Services\Facades\Entry;

class ACMS_POST_Entry_Index_Duplicate extends ACMS_POST_Entry_Duplicate
{
    public function post()
    {
        $this->Post->reset(true);
        $this->Post->setMethod('entry', 'operative', Entry::canBulkDuplicate(BID));
        $this->Post->setMethod('checks', 'required');
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $targetEIDs = [];
            foreach ($this->Post->getArray('checks') as $eid) {
                $id = preg_split('@:@', $eid, 2, PREG_SPLIT_NO_EMPTY);
                $eid = intval($id[1]);
                if (!$this->validate($eid)) {
                    continue;
                }
                $this->duplicate($eid);
                $targetEIDs[] = $eid;
            }
            AcmsLogger::info('指定されたエントリーの一括複製をしました', [
                'targetEIDs' => implode(',', $targetEIDs),
            ]);
        } else {
            AcmsLogger::info('指定されたエントリーの一括複製に失敗しました');
        }
        return $this->Post;
    }
}
