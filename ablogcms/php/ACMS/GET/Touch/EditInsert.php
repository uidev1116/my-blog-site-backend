<?php

class ACMS_GET_Touch_EditInsert extends ACMS_GET
{
    public function get()
    {
        return ( 1
            && !EID
            && !!ADMIN
            && !RVID
            && ( 0
                || 'entry-edit' === ADMIN
                || 'entry_editor' === ADMIN
            )
        ) ? $this->tpl : '';
    }
}
