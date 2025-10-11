<?php

class ACMS_POST_Entry_Index_Category extends ACMS_POST
{
    public function post()
    {
        if (!($cid = intval($this->Post->get('cid')))) {
            $cid = null;
        }
        $this->Post->setMethod('checks', 'required');
        $this->Post->setMethod('entry', 'operable', Entry::canBulkCategoryChange(BID, CID));

        $this->Post->validate(new ACMS_Validator());

        if ($this->checkCategory($this->Post->getArray('checks'), $cid)) {
            $this->addError(gettext('子ブログのカテゴリーをグローバル（共有）カテゴリーではないものに変更することはできません。'));
            return $this->Post;
        }

        if ($this->Post->isValidAll()) {
            $DB     = DB::singleton(dsn());
            $targetEIDs = [];
            foreach ($this->Post->getArray('checks') as $beid) {
                $id     = preg_split('@:@', $beid, 2, PREG_SPLIT_NO_EMPTY);
                $bid    = $id[0];
                $eid    = $id[1];

                if (!($eid = intval($eid))) {
                    continue;
                }
                if (!($bid = intval($bid))) {
                    continue;
                }

                $SQL    = SQL::newUpdate('entry');
                $SQL->setUpdate('entry_category_id', $cid);
                $SQL->addWhereOpr('entry_id', $eid);
                $SQL->addWhereOpr('entry_blog_id', $bid);
                if (!sessionWithCompilation() && !roleAuthorization('entry_edit_all')) {
                    $SQL->addWhereOpr('entry_user_id', SUID);
                }
                $DB->query($SQL->get(dsn()), 'exec');
                ACMS_RAM::entry($eid, null);

                $targetEIDs[] = $eid;
            }
            AcmsLogger::info('指定されたエントリーのカテゴリーを「' . ACMS_RAM::categoryName($cid) . '」カテゴリーに一括変更しました', [
                'targetEIDs' => implode(',', $targetEIDs),
                'targetCID' => $cid,
            ]);
        } else {
            AcmsLogger::info('指定されたエントリーの一括カテゴリー変更に失敗しました');
        }

        return $this->Post;
    }

    public function checkCategory($checked, $cid)
    {
        if (is_null($cid)) {
            return false;
        }

        $error          = false;
        $discovery      = false;
        $entries        = [];

        foreach ($checked as $beid) {
            $id     = preg_split('@:@', $beid, 2, PREG_SPLIT_NO_EMPTY);
            $bid    = $id[0];
            $eid    = $id[1];

            if (!($eid = intval($eid))) {
                continue;
            }
            if (!($bid = intval($bid))) {
                continue;
            }

            $categoryBlog   = intval(ACMS_RAM::categoryBlog($cid));
            $categoryScope  = ACMS_RAM::categoryScope($cid);

            if ($categoryScope === 'local') {
                if ($bid !== $categoryBlog) {
                    $error  = true;
                    $entries[]  = $eid;
                }
            } else {
                $currentBid = $bid;
                do {
                    if ($categoryBlog === $currentBid) {
                        $discovery  = true;
                        break;
                    }
                    $currentBid = intval(ACMS_RAM::blogParent($currentBid));
                } while (intval(ACMS_RAM::blogParent($currentBid)) !== 0);

                if ($categoryBlog === $currentBid) {
                    $discovery  = true;
                }

                if (!$discovery) {
                    $error      = true;
                    $entries[]  = $eid;
                    $discovery  = false;
                }
            }
        }

        return ( $error ) ? $entries : false;
    }
}
