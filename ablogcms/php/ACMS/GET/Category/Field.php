<?php

class ACMS_GET_Category_Field extends ACMS_GET
{
    public $_scope = [
        'cid'   => 'global',
    ];

    function get()
    {
        if (!$this->cid) {
            return '';
        }
        if (!$row = ACMS_RAM::category($this->cid)) {
            return '';
        }

        $status = ACMS_RAM::categoryStatus($this->cid);
        if (!sessionWithAdministration() && 'close' === $status) {
            return '';
        }
        if (!sessionWithSubscription() && 'secret'  === $status) {
            return '';
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        if (!$this->cid) {
            return '';
        }
        $Field  = loadCategoryField($this->cid);
        foreach ($row as $key => $val) {
            $Field->setField(preg_replace('@^category_@', '', $key), $val);
        }

        $Geo = loadGeometry('cid', $this->cid);
        if ($Geo) {
            $Tpl->add('geometry', $this->buildField($Geo, $Tpl, null, 'geometry'));
        }

        $Tpl->add(null, $this->buildField($Field, $Tpl));

        return $Tpl->get();
    }
}
