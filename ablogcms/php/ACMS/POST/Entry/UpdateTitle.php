<?php

class ACMS_POST_Entry_UpdateTitle extends ACMS_POST_Entry_Update
{
    function post()
    {
        $this->Post->setMethod('entry', 'operable', $this->isOperable());
        $this->Post->validate(new ACMS_Validator());
        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            $SQL = SQL::newUpdate('entry');
            $SQL->addUpdate('entry_title', $this->Post->get('title'));
            $SQL->addWhereOpr('entry_id', EID);
            $DB->query($SQL->get(dsn()), 'exec');
            ACMS_RAM::entry(EID, null);
            ACMS_POST_Cache::clearEntryPageCache(EID); // @phpstan-ignore argument.type

            httpStatusCode('200 OK');

            AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '」エントリーのタイトルを変更しました');
        } else {
            httpStatusCode('403 Forbidden');
        }
        Common::setSafeHeadersWithoutCache((int) substr(httpStatusCode(), 0, 3), 'text/plain');
        die(httpStatusCode());
    }
}
