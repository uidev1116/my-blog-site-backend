<?php

class ACMS_GET_Admin_Role_Index extends ACMS_GET_Admin
{
    function get()
    {
        if (BID !== 1 || !sessionWithEnterpriseAdministration()) {
            die403();
        }
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $order  = ORDER ? ORDER : 'id-asc';
        $vars   = [];
        $vars['order:selected#' . $order] = config('attr_selected');
        list($field, $order) = explode('-', $order);

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('role');
        $SQL->setOrder('role_' . $field, $order);

        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');
        if (!$statement || !($row = $DB->next($statement))) {
            $Tpl->add('index#notFound');
            $vars['notice_mess'] = 'show';
        }

        $all = $DB->query($q, 'all');
        foreach ($all as $i => $row) {
            $rid    = intval($row['role_id']);
            $var    = [
                'name'          => $row['role_name'],
                'description'   => $row['role_description'],
                'rid'           => $row['role_id'],
            ];

            // blog count
            $SQL    = SQL::newSelect('role_blog');
            $SQL->addSelect('blog_id', null, null, 'COUNT');
            $SQL->addWhereOpr('role_id', $rid);
            if ($blog_amount = $DB->query($SQL->get(dsn()), 'one')) {
                $var['blog_amount'] = $blog_amount;
            }

            if (!empty($rid)) {
                $var['itemUrl'] = acmsLink([
                    'bid'   => 1,
                    'admin' => 'role_edit',
                    'query' => [
                        'rid'   => $rid,
                    ],
                ]);
            }
            $Tpl->add('role:loop', $var);
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
