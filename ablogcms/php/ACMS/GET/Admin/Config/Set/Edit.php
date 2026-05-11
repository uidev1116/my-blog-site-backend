<?php

class ACMS_GET_Admin_Config_Set_Edit extends ACMS_GET_Admin_Edit
{
    function edit(&$Tpl)
    {
        $configSet =& $this->Post->getChild('config_set');

        if ($configSet->isNull()) {
            if ($setid = intval($this->Get->get('setid'))) {
                $configSet->overload(loadConfigSet($setid));
            }
        }

        return true;
    }
}
