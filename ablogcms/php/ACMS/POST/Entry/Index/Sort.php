<?php

class ACMS_POST_Entry_Index_Sort extends ACMS_POST
{
    public $sortField  = null;

    public function post()
    {
        $type = $this->getType();
        $this->Post->setMethod(
            'entry',
            'operative',
            $this->isOperative($type)
        );
        $this->Post->setMethod('checks', 'required');
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            $targetEIDs = [];
            foreach ($this->Post->getArray('checks') as $eid) {
                $id     = preg_split('@:@', $eid, 2, PREG_SPLIT_NO_EMPTY);
                $bid    = $id[0];
                $eid    = $id[1];
                if (!($eid = intval($eid))) {
                    continue;
                }
                if (!($bid = intval($bid))) {
                    continue;
                }
                if (!($sort = intval($this->Post->get('sort-' . $eid)))) {
                    $sort = 1;
                }

                $SQL    = SQL::newUpdate('entry');
                $SQL->setUpdate($this->sortField, $sort);
                $SQL->addWhereOpr('entry_id', $eid);
                $SQL->addWhereOpr('entry_blog_id', $bid);
                /** @var int|null $sessionUserId */
                $sessionUserId = SUID;
                /** @var int|null $userId */
                $userId = UID;
                if ($type === 'user' && $userId !== $sessionUserId && !Entry::canChangeOrderByOtherUser(BID)) {
                    // 権限がないのにユーザーで絞り込んだエントリーの表示順を変更しようとした場合は自分のエントリーのみ変更する
                    $SQL->addWhereOpr('entry_user_id', $sessionUserId);
                }
                $DB->query($SQL->get(dsn()), 'exec');
                ACMS_RAM::entry($eid, null);
                $targetEIDs[] = $eid;
            }
            AcmsLogger::info('指定されたエントリーの並び順を変更しました', [
                'targetEIDs' => $targetEIDs,
            ]);
        } else {
            AcmsLogger::info('指定されたエントリーの並び順変更に失敗しました');
        }

        return $this->Post;
    }

    /**
     * @return 'entry' | 'category' | 'user'
     */
    private function getType(): string
    {
        $type = 'entry';
        if ($this->sortField === 'entry_category_sort') {
            $type = 'category';
        }
        if ($this->sortField === 'entry_user_sort') {
            $type = 'user';
        }
        return $type;
    }

    /**
     * 表示順の変更が可能かどうかを判定する
     * @param 'entry' | 'category' | 'user' $type
     * @return bool
     */
    private function isOperative(string $type): bool
    {
        if (!Entry::canChangeOrder($type, BID)) {
            return false;
        }
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        /** @var int|null $userId */
        $userId = UID;
        if ($type === 'user' && $userId !== $sessionUserId) {
            return Entry::canChangeOrderByOtherUser(BID);
        }
        return true;
    }
}
