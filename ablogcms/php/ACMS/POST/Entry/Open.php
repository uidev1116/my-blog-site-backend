<?php

class ACMS_POST_Entry_Open extends ACMS_POST_Entry
{
    public function post()
    {
        $DB = DB::singleton(dsn());
        $this->Post->reset(true);

        $entryId = intval($this->Post->get('eid'));
        $this->Post->setMethod('entry', 'operable', (1
            && $entryId > 0
            && !!IS_LICENSED
            && (0
                || sessionWithCompilation()
                || (1
                    && sessionWithContribution()
                    && SUID == ACMS_RAM::entryUser($entryId)
                )
            )
        ));
        $this->Post->validate();

        if ($this->Post->isValidAll()) {
            $SQL    = SQL::newUpdate('entry');
            $SQL->addUpdate('entry_status', 'open');
            if ('draft' == ACMS_RAM::entryStatus($entryId) && config('update_datetime_as_entry_open') !== 'off') {
                $SQL->addUpdate('entry_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            }
            $SQL->addWhereOpr('entry_id', $entryId);
            $DB->query($SQL->get(dsn()), 'exec');

            ACMS_RAM::entry($entryId, null);
            ACMS_POST_Cache::clearEntryPageCache($entryId); // このエントリのみ削除

            AcmsLogger::info('「' . ACMS_RAM::entryTitle($entryId) . '」エントリーを公開しました');

            //-------------------
            // キャッシュクリア予約
            Entry::updateCacheControl(ACMS_RAM::entryStartDatetime($entryId), ACMS_RAM::entryEndDatetime($entryId), ACMS_RAM::entryBlog($entryId), $entryId);

            //------
            // Hook
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('saveEntry', [$entryId, null]);
                Webhook::call(BID, 'entry', 'entry:opened', [$entryId, null]);
            }
            $this->redirect(acmsLink([
                'bid'   => BID,
                'eid'   => $entryId,
            ]));
        }

        return $this->Post;
    }
}
