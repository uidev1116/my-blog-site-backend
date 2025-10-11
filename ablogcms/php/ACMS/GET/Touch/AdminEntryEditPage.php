<?php

class ACMS_GET_Touch_AdminEntryEditPage extends ACMS_GET
{
    public function get()
    {
        if (sessionWithContribution() && ADMIN === 'entry_editor') {
            return $this->tpl;
        }
        return '';
    }
}
