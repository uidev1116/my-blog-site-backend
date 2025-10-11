<?php

class ACMS_POST_Alias extends ACMS_POST
{
    function checkScope($scope = 'local')
    {
        $DB = DB::singleton(dsn());
        do {
            if ($scope !== 'global') {
                return true;
            }
            //-----------
            // blog code
            $SQL = SQL::newSelect('blog');
            $SQL->addWhereOpr('blog_code', '');
            ACMS_Filter::blogTree($SQL, BID, 'descendant');
            if ($DB->query($SQL->get(dsn()), 'one')) {
                return false;
            }

            //-------------------
            // overlap blog code
            $SQL = SQL::newSelect('blog');
            $SQL->addSelect('blog_code');
            ACMS_Filter::blogTree($SQL, BID, 'descendant');
            $SQL->addGroup('blog_code');
            $SQL->addHaving(SQL::newOpr('*', 1, '>', null, 'COUNT'));
            if ($DB->query($SQL->get(dsn()), 'one')) {
                return false;
            }
        } while (false);

        return true;
    }
}
