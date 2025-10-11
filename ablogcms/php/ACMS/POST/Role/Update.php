<?php

class ACMS_POST_Role_Update extends ACMS_POST
{
    public function post()
    {
        $roleId = (int)$this->Get->get('rid');
        $Role = $this->extract('role');
        $Role->setMethod('name', 'required');
        $Role->setMethod('role', 'operable', $this->isOperable($roleId));

        $Role->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());

            //-----------
            // role
            $SQL    = SQL::newUpdate('role');
            foreach ($Role->listFields() as $key) {
                if ($key !== 'blog_list') {
                    $SQL->addUpdate('role_' . $key, $Role->get($key));
                }
            }
            $SQL->addWhereOpr('role_id', $roleId);
            $DB->query($SQL->get(dsn()), 'exec');

            //-----------
            // blog list
            $SQL    = SQL::newDelete('role_blog');
            $SQL->addWhereOpr('role_id', $roleId);
            $DB->query($SQL->get(dsn()), 'exec');

            $insert = SQL::newBulkInsert('role_blog');
            foreach ($Role->getArray('blog_list') as $bid) {
                $insert->addInsert([
                    'role_id' => $roleId,
                    'blog_id' => $bid,
                ]);
            }
            if ($insert->hasData()) {
                $DB->query($insert->get(dsn()), 'exec');
            }

            $this->Post->set('edit', 'update');

            AcmsLogger::info('「' . $Role->get('name') . '」ロールを更新しました', [
                'roleID' => $roleId,
                'data' => $Role->_aryField,
            ]);
        } else {
            AcmsLogger::info('ロールの更新に失敗しました', [
                'roleID' => $roleId,
                'validate' => $Role->_aryV,
            ]);
        }
        return $this->Post;
    }

    /**
     * ロールの更新が可能かどうか
     * @param int $roleId
     * @return bool
     */
    protected function isOperable(int $roleId)
    {
        if (!sessionWithEnterpriseAdministration()) {
            return false;
        }

        if (BID !== 1) {
            return false;
        }

        if ($roleId < 1) {
            return false;
        }

        return true;
    }
}
