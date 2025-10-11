<?php

class ACMS_GET_Admin_User_SelectGlobal extends ACMS_GET_Admin
{
    public $_scope = [
        'uid'   => 'global',
    ];
    function get()
    {
        if (!sessionWithCompilation()) {
            return '';
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $Tpl->add(null, $this->buildUserSelect(
            $Tpl,
            BID,
            $this->uid,
            'loop',
            ['administrator', 'editor', 'contributor'],
            true,
            'sort-asc'
        ));
        return $Tpl->get();
    }

    function get2()
    {
        if (!sessionWithContribution() || (!ADMIN && !is_ajax())) {
            return '';
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $target_bid = $this->Get->get('_bid', $this->bid);
        if (!$target_bid) {
            $target_bid = BID;
        }

        $order  = 'sort-asc';
        $order2 = config('category_select_global_order');
        if (!empty($order2)) {
            $order  = $order2;
        }

        $Tpl->add(null, $this->buildCategorySelect(
            $Tpl,
            $target_bid,
            $this->cid,
            'loop',
            true,
            $order
        ));
        return $Tpl->get();
    }
}
