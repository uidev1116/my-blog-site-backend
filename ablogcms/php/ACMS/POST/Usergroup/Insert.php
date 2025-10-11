<?php

class ACMS_POST_Usergroup_Insert extends ACMS_POST_Usergroup
{
    function post()
    {
        $Usergroup = $this->extract('usergroup');
        $Usergroup->setMethod('name', 'required');
        $Usergroup->setMethod('name', 'double');
        $Usergroup->setMethod('role_id', 'required');
        $Usergroup->setMethod('usergroup', 'operable', sessionWithEnterpriseAdministration() and BID === RBID);

        $Usergroup->validate(new ACMS_Validator_Usergroup());

        if ($this->Post->isValidAll()) {
            $DB     = DB::singleton(dsn());

            //------
            // ugid
            $ugid   = $DB->query(SQL::nextval('usergroup_id', dsn()), 'seq');

            //-----------
            // usergroup
            $SQL    = SQL::newInsert('usergroup');
            $SQL->addInsert('usergroup_id', $ugid);
            foreach ($Usergroup->listFields() as $key) {
                if ($key !== 'user_list') {
                    $SQL->addInsert('usergroup_' . $key, $Usergroup->get($key));
                }
            }
            $DB->query($SQL->get(dsn()), 'exec');

            //-----------
            // user list
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
            $this->Post->set('edit', 'insert');

            AcmsLogger::info('ユーザーグループ「' . $Usergroup->get('name') . '」を作成しました', [
                'ugid' => $ugid,
                'data' => $Usergroup->_aryField,
            ]);
        } else {
            AcmsLogger::info('ユーザーグループの作成に失敗しました', [
                'data' => $Usergroup->_aryV,
            ]);
        }
        return $this->Post;
    }
}
