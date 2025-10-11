<?php

class ACMS_GET_Blog_Field extends ACMS_GET
{
    public $_scope = [
        'bid'   => 'global',
    ];

    function get()
    {
        if (!$this->bid) {
            return '';
        }
        if (!$row = ACMS_RAM::blog($this->bid)) {
            return '';
        }
        $status = ACMS_RAM::blogStatus($this->bid);
        if (!sessionWithAdministration() and 'close' === $status) {
            return '';
        }
        if (!sessionWithSubscription() and 'secret'  === $status) {
            return '';
        }
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        if (!$this->bid) {
            return '';
        }
        $Field = loadBlogField($this->bid);
        foreach ($row as $key => $val) {
            $Field->setField(preg_replace('@^blog_@', '', $key), $val);
        }

        $Geo = loadGeometry('bid', $this->bid);
        if ($Geo) {
            $Tpl->add('geometry', $this->buildField($Geo, $Tpl, null, 'geometry'));
        }

        $Tpl->add(null, $this->buildField($Field, $Tpl));

        return $Tpl->get();
    }
}
