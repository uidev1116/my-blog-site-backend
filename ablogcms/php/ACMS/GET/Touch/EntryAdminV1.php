<?php

class ACMS_GET_Touch_EntryAdminV1 extends ACMS_GET
{
    public function get()
    {
        return (int)config('entry_admin_page_version') === 1 ? $this->tpl : '';
    }
}
