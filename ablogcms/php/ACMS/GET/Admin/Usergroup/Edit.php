<?php

class ACMS_GET_Admin_Usergroup_Edit extends ACMS_GET_Admin_Edit
{
    function edit(&$Tpl)
    {
        if (BID !== 1 || !sessionWithEnterpriseAdministration()) {
            die403();
        }
        $Usergropu  =& $this->Post->getChild('usergroup');

        if ($Usergropu->isNull()) {
            if ($ugid = intval($this->Get->get('ugid'))) {
                $Usergropu->overload(loadUsergroup($ugid));
            }
        }
        return true;
    }
}
