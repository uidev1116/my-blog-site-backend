<?php

class ACMS_GET_Admin_Tag_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if ('tag_index' !== ADMIN) {
            return '';
        }
        if (roleAvailableUser()) {
            if (!roleAuthorization('tag_edit', BID)) {
                die403();
            }
        } else {
            if (!sessionWithCompilation()) {
                die403();
            }
        }

        $order  = ORDER ? ORDER : config('admin_tag_order');
        $limits = configArray('admin_tag_limit_option');
        $limit  = LIMIT ? LIMIT : $limits[config('admin_tag_limit')];

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $DB     = DB::singleton(dsn());

        $vars   = [];

        //-------
        // order
        $vars['order:selected#' . $order]  = config('attr_selected');

        //-------
        // limit
        foreach ($limits as $val) {
            $_vars  = [
                'value' => $val,
                'label' => $val,
            ];
            if ($limit == $val) {
                $_vars['selected'] = config('attr_selected');
            }
            $Tpl->add('limit:loop', $_vars);
        }

        $SQL    = SQL::newSelect('tag');
        $SQL->setSelect(SQL::newFunction('tag_name', 'DISTINCT'), 'tag_amount', null, 'COUNT');
        $SQL->addWhereOpr('tag_blog_id', BID);
        if (!$pageAmount = $DB->query($SQL->get(dsn()), 'one')) {
            $Tpl->add('index#notFound');
            $Tpl->add(null, $vars);
            return $Tpl->get();
        }

        $vars   += $this->buildPager(
            PAGE,
            $limit,
            $pageAmount,
            config('admin_pager_delta'),
            config('admin_pager_cur_attr'),
            $Tpl,
            [],
            ['admin' => ADMIN]
        );

        $SQL    = SQL::newSelect('tag');
        $SQL->addSelect('tag_name');
        $SQL->addSelect('tag_name', 'tag_amount', null, 'count');
        $SQL->addWhereOpr('tag_blog_id', BID);
        $SQL->setGroup('tag_name');
        ACMS_Filter::tagOrder($SQL, $order);
        $SQL->setLimit($limit, (PAGE - 1) * $limit);
        $q  = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        while ($row = $DB->next($statement)) {
            $tag    = $row['tag_name'];
            $Tpl->add('tag:loop', [
                'url'   => acmsLink([
                    'admin' => 'tag_edit',
                    'tag'   => $tag,
                ]),
                'name'      => $tag,
                'amount'    => $row['tag_amount'],
            ]);
        }

        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
