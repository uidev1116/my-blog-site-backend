<?php

use Acms\Services\Facades\Entry;

class ACMS_POST_Entry_Trash extends ACMS_POST_Trash
{
    function post()
    {
        if (!$this->validate($this->Post)) {
            AcmsLogger::info('エントリーをゴミ箱に移動できませんでした');
            $this->Post->setValidator('trash', 'operable', false);
            return $this->Post;
        }
        $eid = idval($this->Post->get('eid'));

        if (HOOK_ENABLE) {
            Webhook::call(BID, 'entry', 'entry:deleted', [$eid, null]);
        }

        $this->trash($eid);
        ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除
        AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーをゴミ箱に移動しました');

        //------
        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, null]);
        }

        if ($eid === EID) { // @phpstan-ignore-line
            // 詳細画面からの削除の場合は、一覧画面にリダイレクト
            $this->redirect(acmsLink([
                'bid'   => BID,
                'cid'   => CID,
            ]));
        }
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

        if (!Entry::canDelete($entryId)) {
            return false;
        }

        return true;
    }
}
