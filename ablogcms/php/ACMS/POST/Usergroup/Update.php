<?php

class ACMS_POST_Usergroup_Update extends ACMS_POST_Usergroup
{
    function post()
    {
        $Usergroup = $this->extract('usergroup');

        $Usergroup->setMethod('usergroup', 'operable', $ugid = intval($this->Get->get('ugid')) and sessionWithEnterpriseAdministration() and BID === RBID);
        $Usergroup->setMethod('name', 'required');
        $Usergroup->setMethod('name', 'double', $ugid);
        $Usergroup->setMethod('role_id', 'required');
        $Usergroup->setMethod('approval_point', 'required');

        $Usergroup->validate(new ACMS_Validator_Usergroup());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());

            //-----------
            // usergroup
            $SQL    = SQL::newUpdate('usergroup');
            foreach ($Usergroup->listFields() as $key) {
                if ($key !== 'user_list') {
                    $SQL->addUpdate('usergroup_' . $key, $Usergroup->get($key));
                }
            }
            $SQL->addWhereOpr('usergroup_id', $ugid);
            $DB->query($SQL->get(dsn()), 'exec');

            //-----------
            // user list
            $SQL    = SQL::newDelete('usergroup_user');
            $SQL->addWhereOpr('usergroup_id', $ugid);
            $DB->query($SQL->get(dsn()), 'exec');

            $sql = SQL::newBulkInsert('usergroup_user');
            foreach ($Usergroup->getArray('user_list') as $uid) {
                $sql->addInsert([
                    'usergroup_id' => $ugid,
                    'user_id' => $uid,
                ]);
            }
            if ($sql->hasData()) {
                $DB->query($sql->get(dsn()), 'exec');
            }

            $this->Post->set('edit', 'update');

            AcmsLogger::info('ユーザーグループ「' . $Usergroup->get('name') . '」の情報を更新しました', [
                'ugid' => $ugid,
                'data' => $Usergroup->_aryField,
            ]);
        } else {
            AcmsLogger::info('ユーザーグループ「' . $Usergroup->get('name') . '」の更新に失敗しました', [
                'ugid' => $ugid,
                'data' => $Usergroup->_aryV,
            ]);
        }
        return $this->Post;
    }
}
