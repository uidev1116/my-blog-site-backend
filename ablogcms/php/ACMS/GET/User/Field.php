<?php

class ACMS_GET_User_Field extends ACMS_GET
{
    public $_scope = [
        'uid'   => 'global',
    ];

    function get()
    {
        if (!$this->uid) {
            return '';
        }
        if (!$row = ACMS_RAM::user($this->uid)) {
            return '';
        }

        $status = ACMS_RAM::userStatus($this->uid);
        if (!sessionWithAdministration() and 'close' === $status) {
            return '';
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        if (!$this->uid) {
            return '';
        }
        $Field = new Field();
        $Field->overload(loadUserField($this->uid));
        foreach ($row as $key => $val) {
            $Field->setField(preg_replace('@^user_@', '', $key), $val);
        }

        $Geo = loadGeometry('uid', $this->uid);
        if ($Geo) {
            $Tpl->add('geometry', $this->buildField($Geo, $Tpl, null, 'geometry'));
        }

        $Tpl->add(null, $this->buildField($Field, $Tpl));

        return $Tpl->get();
    }
}
