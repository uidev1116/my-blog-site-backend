<?php

class ACMS_Validator_Usergroup extends ACMS_Validator
{
    function double($name, $ugid)
    {
        if (empty($name)) {
            return true;
        }

        $DB = DB::singleton(dsn());

        //-----------
        // usergroup
        $SQL = SQL::newSelect('usergroup');
        $SQL->setSelect('usergroup_id');
        $SQL->addWhereOpr('usergroup_name', $name);
        if (!empty($ugid)) {
            $SQL->addWhereOpr('usergroup_id', $ugid, '<>');
        }
        if ($DB->query($SQL->get(dsn()), 'one')) {
            return false;
        }

        return true;
    }
}

class ACMS_POST_Usergroup extends ACMS_POST
{
}
