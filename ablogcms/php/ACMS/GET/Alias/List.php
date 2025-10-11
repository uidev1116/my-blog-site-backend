<?php

class ACMS_GET_Alias_List extends ACMS_GET
{
    function get()
    {
        $DB = DB::singleton(dsn());

        $limit  = 3;
        $order  = 'code-asc';
        $blogAliasSort = ACMS_RAM::blogAliasSort($this->bid);

        $SQL    = SQL::newSelect('alias');
        $SQL->addWhereOpr('alias_blog_id', $this->bid);
        $SQL->setOrder('alias_sort');
        $all    = $DB->query($SQL->get(dsn()), 'all');

        array_unshift($all, [
            'alias_name'    => ACMS_RAM::blogName($this->bid),
            'alias_domain'  => ACMS_RAM::blogDomain($this->bid),
            'alias_code'    => ACMS_RAM::blogCode($this->bid),
            'alias_status'  => ACMS_RAM::blogAliasStatus($this->bid),
            'alias_id'      => null,
            'alias_sort'    => $blogAliasSort,
        ]);

        //-------------------
        // name-(asc|desc)
        // domain-(asc|desc)
        // code-(asc|desc)
        // id-(asc|desc)
        // sort(asc|desc)
        $sort   = explode('-', config('alias_list_order'), 2);
        $key    = !empty($sort[0]) ? $sort[0] : 'id';
        $order  = !empty($sort[1]) ? $sort[1] : 'asc';

        $map    = [];
        $sort   = [];
        foreach ($all as $row) {
            $aid        = intval($row['alias_id']);
            $map[$aid]  = $row;
            $sort[$aid] = $row['alias_' . $key];
        }
        if ('desc' == $order) {
            arsort($sort);
        } else {
            asort($sort);
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        $i  = 0;
        $limit  = config('alias_list_limit');
        foreach ($sort as $aid => $kipple) {
            if ($limit <= $i++) {
                break;
            }
            $row    = $map[$aid];
            $Tpl->add('alias:loop', [
                'id'        => $aid,
                'name'      => $row['alias_name'],
                'domain'    => $row['alias_domain'],
                'code'      => $row['alias_code'],
                'url'       => !empty($aid) ? acmsLink([
                    'bid'   => $this->bid,
                    'aid'   => $aid,
                ]) : acmsLink([
                    'bid'   => $this->bid,
                ]),
            ]);
        }

        return $Tpl->get();
    }
}
