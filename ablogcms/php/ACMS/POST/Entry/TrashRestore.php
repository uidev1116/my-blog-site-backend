<?php

use Acms\Services\Facades\Entry;

class ACMS_POST_Entry_TrashRestore extends ACMS_POST_Trash
{
    public function post()
    {
        if (!$this->validate($this->Post)) {
            AcmsLogger::info('ゴミ箱のエントリーを復元できませんでした');
            $this->Post->setValidator('trashRestore', 'operable', false);
            return $this->Post;
        }
        $eid = idval($this->Post->get('eid'));

        $this->restore($eid);

        AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーをゴミ箱から復元しました');

        return $this->Post;
    }

    /**
     * @param \Field_Validation $post
     * @return bool
     */
    protected function validate(\Field_Validation $post): bool
    {
        if (!IS_LICENSED) {
            return false;
        }
        $entryId = idval($post->get('eid'));
        if ($entryId < 1) {
            return false;
        }

        if (!Entry::canTrashRestore($entryId)) {
            return false;
        }

        return true;
    }
}
