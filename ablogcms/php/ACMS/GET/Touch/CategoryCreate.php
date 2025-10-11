<?php

use Acms\Services\Facades\Category;

class ACMS_GET_Touch_CategoryCreatable extends ACMS_GET
{
    public function get()
    {
        return Category::canCreate(BID) ? $this->tpl : '';
    }
}
