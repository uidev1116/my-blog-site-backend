<?php

class ACMS_GET_Touch_EntryAdminV2 extends ACMS_GET
{
    public function get()
    {
        return (int)config('entry_admin_page_version') === 2 ? $this->tpl : '';
    }
}
