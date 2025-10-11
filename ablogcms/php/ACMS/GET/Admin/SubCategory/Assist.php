<?php

use Acms\Services\Facades\Common;

class ACMS_GET_Admin_SubCategory_Assist extends ACMS_GET_Admin_Category_Assist
{
    /**
     * @inheritDoc
     */
    public function get()
    {
        if (!sessionWithContribution()) {
            return Common::responseJson([]);
        }
        $filterCid = intval(config('entry_edit_sub_category_filter', 0));
        $order = 'sort-asc';
        $order2 = config('category_select_global_order');
        if ($order2 !== '') {
            $order = $order2;
        }
        $limit = (int)config('category_select_limit', 999);
        $query = $this->buildQuery($order, $filterCid, $limit);
        $list = $this->buildList($query, $filterCid);
        return Common::responseJson($list);
    }
}
