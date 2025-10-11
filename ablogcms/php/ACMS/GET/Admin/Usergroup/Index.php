<?php

class ACMS_GET_Admin_Usergroup_Index extends ACMS_GET_Admin
{
    function get()
    {
        if (!sessionWithEnterpriseAdministration()) {
            die403();
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $order  = ORDER ? ORDER : 'id-asc';
        $vars   = [];
        $vars['order:selected#' . $order] = config('attr_selected');
        list($field, $order) = explode('-', $order);

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('usergroup');
        $SQL->setOrder('usergroup_' . $field, $order);

        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');
        if (!$statement || !($row = $DB->next($statement))) {
            $Tpl->add('index#notFound');
            $vars['notice_mess'] = 'show';
        }

        $all = $DB->query($q, 'all');
        foreach ($all as $i => $row) {
            $ugid   = intval($row['usergroup_id']);
            $var    = [
                'name'          => $row['usergroup_name'],
                'point'         => $row['usergroup_approval_point'],
                'description'   => $row['usergroup_description'],
                'ugid'          => $ugid,
            ];

            // role
            $SQL    = SQL::newSelect('role');
            $SQL->addSelect('role_name');
            $SQL->addSelect('role_id');
            $SQL->addWhereOpr('role_id', $row['usergroup_role_id']);
            if ($role = $DB->query($SQL->get(dsn()), 'row')) {
                $var['role_id']     = $role['role_id'];
                $var['role_name']   = $role['role_name'];
            }

            // user count
            $SQL    = SQL::newSelect('usergroup_user');
            $SQL->addSelect('user_id', null, null, 'COUNT');
            $SQL->addWhereOpr('usergroup_id', $ugid);
            if ($user_amount = $DB->query($SQL->get(dsn()), 'one')) {
                $var['user_amount'] = $user_amount;
            }

            if (!empty($ugid)) {
                $var['itemUrl'] = acmsLink([
                    'bid'   => 1,
                    'admin' => 'usergroup_edit',
                    'query' => [
                        'ugid'   => $ugid,
                    ],
                ]);
            }
            $Tpl->add('usergroup:loop', $var);
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
