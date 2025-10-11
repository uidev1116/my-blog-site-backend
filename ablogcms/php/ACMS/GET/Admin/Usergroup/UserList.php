<?php

class ACMS_GET_Admin_Usergroup_UserList extends ACMS_GET
{
    public function get()
    {
        if (BID !== 1 || !sessionWithEnterpriseAdministration()) {
            die403();
        }
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('user');
        $SQL->addWhereOpr('user_pass', '', '<>');
        $SQL->addWhereOpr('user_blog_id', 1);
        $SQL->addWhereNotIn('user_auth', ['administrator', 'subscriber']);
        $SQL->setOrder('user_id', 'ASC');

        // amount
        $Amount = new SQL_Select($SQL);
        $Amount->setSelect('*', 'user_amount', null, 'count');
        $itemsAmount    = intval($DB->query($Amount->get(dsn()), 'one'));

        // tpl
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());

        // no data
        if (empty($itemsAmount)) {
            $Tpl->add('notFound');
            return $Tpl->get();
        }

        //-----------
        // user:loop
        $q      = $SQL->get(dsn());
        foreach ($DB->query($q, 'all') as $i => $row) {
            unset($row['user_pass']);
            unset($row['user_pass_reset']);
            unset($row['user_generated_datetime']);

            $vars           = $this->buildField(loadUserField(intval($row['user_id'])), $Tpl);
            foreach ($row as $key => $value) {
                if (strpos($key, 'user_') !== 0) {
                    continue;
                }
                $vars[substr($key, strlen('user_'))]    = $value;
            }
            $vars['icon']   = loadUserIcon($row['user_id']);
            $Tpl->add('user:loop', $vars);
        }

        return $Tpl->get();
    }
}
