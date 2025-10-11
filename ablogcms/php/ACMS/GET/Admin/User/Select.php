<?php

class ACMS_GET_Admin_User_Select extends ACMS_GET_Admin
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
            false,
            'sort-asc'
        ));
        return $Tpl->get();
    }
}
