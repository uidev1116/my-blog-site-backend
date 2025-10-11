<?php

class ACMS_GET_Admin_Category_SelectGlobal extends ACMS_GET_Admin
{
    public $_scope  = [
        'cid' => 'global',
        'eid' => 'global',
    ];

    function get()
    {
        if (!sessionWithContribution() || (!ADMIN && !is_ajax())) {
            return '';
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        if (in_array(ADMIN, ['entry-edit', 'entry_editor'], true)) {
            $target_bid = $this->bid;
        } else {
            $target_bid = $this->Get->get('_bid', $this->bid);
        }
        if (!$target_bid) {
            $target_bid = BID;
        }

        $order  = 'sort-asc';
        $order2 = config('category_select_global_order');
        if (!empty($order2)) {
            $order  = $order2;
        }
        $cid = $this->cid;
        $filterCid = 0;
        if ($this->eid) {
            $cid = ACMS_RAM::entryCategory($this->eid);
        }
        $Tpl->add(null, $this->buildCategorySelect(
            $Tpl,
            $target_bid,
            $cid,
            'loop',
            true,
            $order,
            $filterCid
        ));
        return $Tpl->get();
    }
}
