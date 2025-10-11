<?php

class ACMS_POST_Entry_Delete extends ACMS_POST_Entry
{
    /**
     * @param int $entryId
     * @return true
     */
    protected function delete(int $entryId): bool
    {
        Entry::entryDelete($entryId);
        Entry::revisionDelete($entryId);

        return true;
    }

    public function post()
    {
        $this->Post->reset(true);
        $entryId = intval($this->Post->get('eid', EID));
        $this->Post->setMethod('eid', 'min', 1);
        $this->Post->setMethod('entry', 'operable', Entry::canDelete($entryId));
        $this->Post->validate();

        if ($this->Post->isValidAll()) {
            if (HOOK_ENABLE) {
                Webhook::call(BID, 'entry', 'entry:deleted', [$entryId, null]);
            }
            $entryTitle = ACMS_RAM::entryTitle($entryId);
            $this->delete($entryId);
            ACMS_POST_Cache::clearEntryPageCache($entryId); // このエントリのみ削除
            $redirect = $this->Post->get('redirect');

            AcmsLogger::info('「' . $entryTitle . '」エントリーを削除しました', [
                'entryId' => $entryId
            ]);

            if (!empty($redirect) && Common::isSafeUrl($redirect)) {
                $this->redirect($redirect);
            } else {
                $this->redirect(acmsLink([
                    'bid'   => BID,
                    'cid'   => CID,
                    'eid'   => null,
                ]));
            }
        }
        return $this->Post;
    }
}
