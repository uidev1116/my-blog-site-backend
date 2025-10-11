<?php

class ACMS_POST_Role_Insert extends ACMS_POST
{
    function post()
    {
        $Role = $this->extract('role');
        $Role->setMethod('name', 'required');
        $Role->setMethod('role', 'operable', sessionWithEnterpriseAdministration() and BID === RBID);

        $Role->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());

            //-----
            // rid
            $rid = $DB->query(SQL::nextval('role_id', dsn()), 'seq');

            //-----------
            // role
            $SQL = SQL::newInsert('role');
            $SQL->addInsert('role_id', $rid);
            foreach ($Role->listFields() as $key) {
                if ($key !== 'blog_list') {
                    $SQL->addInsert('role_' . $key, $Role->get($key));
                }
            }
            $DB->query($SQL->get(dsn()), 'exec');

            //-----------
            // blog list
            $insert = SQL::newBulkInsert('role_blog');
            foreach ($Role->getArray('blog_list') as $bid) {
                $insert->addInsert([
                    'role_id' => $rid,
                    'blog_id' => $bid,
                ]);
            }
            if ($insert->hasData()) {
                $DB->query($insert->get(dsn()), 'exec');
            }

            $this->Post->set('edit', 'insert');

            AcmsLogger::info('「' . $Role->get('name') . '」ロールを作成しました', [
                'roleID' => $rid,
                'data' => $Role->_aryField,
            ]);
        } else {
            AcmsLogger::info('ロールの作成に失敗しました', [
                'validate' => $Role->_aryV,
            ]);
        }
        return $this->Post;
    }
}
