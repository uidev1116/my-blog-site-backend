<?php

class ACMS_GET_Tag_Assist extends ACMS_GET
{
    function get()
    {
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $DB = DB::singleton(dsn());

        $SQL = SQL::newSelect('tag');
        $SQL->addSelect('tag_name');
        $SQL->addSelect('tag_name', 'tag_amount', null, 'count');
        $SQL->addWhereOpr('tag_blog_id', $this->bid);
        $SQL->addGroup('tag_name');
        $SQL->setLimit(config('tag_assist_limit'));
        ACMS_Filter::tagOrder($SQL, config('tag_assist_order'));
        if (1 < ($tagThreshold = idval(config('tag_assist_threshold')))) {
            $SQL->addHaving(SQL::newOpr('tag_amount', $tagThreshold, '>='));
        }
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        if (!$statement) {
            return $Tpl->get();
        }
        if (!$row = $DB->next($statement)) {
            return $Tpl->get();
        }
        $firstLoop = true;
        do {
            if (!$firstLoop) {
                $Tpl->add(['tag:glue', 'tag:loop']);
            }
            $firstLoop = false;
            $Tpl->add('tag:loop', [
                'name' => $row['tag_name'],
                'amount' => $row['tag_amount'],
            ]);
        } while ($row = $DB->next($statement));

        return $Tpl->get();
    }
}
