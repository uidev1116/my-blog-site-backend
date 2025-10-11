<?php

class ACMS_GET_Touch_NotEdit extends ACMS_GET
{
    function get()
    {
        return !( 1
            and !!ADMIN
            and ( 0
                or 'entry-edit' == ADMIN
                or 'entry_editor' == ADMIN
            )
        ) ? $this->tpl : false;
    }
}
