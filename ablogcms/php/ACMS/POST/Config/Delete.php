<?php

class ACMS_POST_Config_Delete extends ACMS_POST
{
    function post()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        if (!$rid = idval($this->Post->get('rid'))) {
            $rid = null;
        }
        if (!$setid = idval($this->Post->get('setid'))) {
            $setid = null;
        }

        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('config');
        $SQL->addWhereOpr('config_rule_id', $rid);
        $SQL->addWhereOpr('config_set_id', $setid);
        $SQL->addWhereOpr('config_module_id', null);
        $SQL->addWhereOpr('config_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        Config::forgetCache(BID, $rid, null, $setid);

        $this->addMessage('success');

        AcmsLogger::info('コンフィグをリセットしました', [
            'bid' => BID,
            'rid' => $rid,
            'setid' => $setid,
        ]);

        return $this->Post;
    }
}
