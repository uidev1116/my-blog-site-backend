<?php

class ACMS_GET_Admin_Rule_List extends ACMS_GET
{
    function get()
    {
        if (roleAvailableUser()) {
            if (!roleAuthorization('rule_edit', BID)) {
                die403();
            }
        } else {
            if (!sessionWithAdministration()) {
                die403();
            }
        }

        $rid    = isset($_GET['rid']) ? idval($_GET['rid']) : null;

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('rule');
        $SQL->addSelect('rule_id');
        $SQL->addSelect('rule_name');
        $SQL->addWhereOpr('rule_blog_id', BID);
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        if (!$statement) {
            return '';
        }
        if (!($row = $DB->next($statement))) {
            return '';
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        do {
            $id     = intval($row['rule_id']);
            $name   = $row['rule_name'];
            $vars   = [
                'id'    => $id,
                'name'  => $name,
            ];
            if ($rid === $id) {
                $vars['selected'] = config('attr_selected');
            }
            $Tpl->add('loop', $vars);
        } while ($row = $DB->next($statement));

        return $Tpl->get();
    }
}
