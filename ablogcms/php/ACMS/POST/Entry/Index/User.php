<?php

class ACMS_POST_Entry_Index_User extends ACMS_POST
{
    public function post()
    {
        $userId = intval($this->Post->get('uid'));
        $userBlogId = ACMS_RAM::userBlog($userId);
        $this->Post->setMethod('entry', 'operative', Entry::canBulkUserChange(BID, CID));
        $this->Post->setMethod('checks', 'required');
        $this->Post->setMethod('entry', 'uidIsNull', 1
            && $userId > 0
            && $userBlogId > 0
            && ACMS_RAM::blogLeft($userBlogId) <= ACMS_RAM::blogLeft(BID)
            && ACMS_RAM::blogRight($userBlogId) >= ACMS_RAM::blogRight(BID));
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            $targetEIDs = [];
            foreach ($this->Post->getArray('checks') as $eid) {
                $id = preg_split('@:@', $eid, 2, PREG_SPLIT_NO_EMPTY);
                $bid = $id[0];
                $eid = $id[1];
                if (!($bid = intval($bid))) {
                    continue;
                }
                if (!($eid = intval($eid))) {
                    continue;
                }
                $SQL    = SQL::newUpdate('entry');
                $SQL->setUpdate('entry_user_id', $userId);
                $SQL->addWhereOpr('entry_id', $eid);
                $SQL->addWhereOpr('entry_blog_id', $bid);
                $DB->query($SQL->get(dsn()), 'exec');
                ACMS_RAM::entry($eid, null);
                $targetEIDs[] = $eid;
            }
            AcmsLogger::info('選択したエントリーのユーザーを「' . ACMS_RAM::userName($userId) . '」に変更しました', [
                'targetEIDs' => $targetEIDs,
            ]);
        } else {
            AcmsLogger::info('選択したエントリーのユーザー変更に失敗しました');
        }

        return $this->Post;
    }
}
