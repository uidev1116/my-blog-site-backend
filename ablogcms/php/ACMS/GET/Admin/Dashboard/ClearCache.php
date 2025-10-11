<?php

class ACMS_GET_Admin_Dashboard_ClearCache extends ACMS_GET
{
    function get()
    {
        if (!sessionWithCompilation()) {
            return '';
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $vars = [];

        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('cache_reserve');
        $SQL->addLeftJoin('entry', 'cache_reserve_entry_id', 'entry_id');
        $SQL->addWhereOpr('cache_reserve_blog_id', BID);
        $SQL->setOrder('cache_reserve_datetime', 'ASC');
        $SQL->setLimit(100);
        $all = $DB->query($SQL->get(dsn()), 'all');

        foreach ($all as $row) {
            $reserve = [
                'title'     => $row['entry_title'],
                'datetime'  => $row['cache_reserve_datetime'],
                'type'      => $row['cache_reserve_type'],
                'entryUrl'  => acmsLink([
                    'bid'   => $row['entry_blog_id'],
                    'eid'   => $row['entry_id'],
                ]),
                'entryEdit' => acmsLink([
                    'bid'   => $row['entry_blog_id'],
                    'eid'   => $row['entry_id'],
                    'admin' => 'entry_editor',
                ]),
            ];
            $reserveVal = $this->buildField(new Field($reserve), $Tpl, ['cache_reserve:loop']);
            $Tpl->add('cache_reserve:loop', $reserveVal);
        }
        $Tpl->add(null, $vars);
        return $Tpl->get();
    }
}
