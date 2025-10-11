<?php

class ACMS_GET_Admin_Fix_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if ('fix_index' !== ADMIN) {
            return '';
        }
        if (!sessionWithAdministration()) {
            die403();
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $aryAdmin = [
            'fix_image',
            'fix_unit-size',
            'fix_unit-group',
            'fix_unit-map',
            'fix_sequence',
            'fix_fulltext',
            'fix_tag',
            'fix_replacement',
        ];
        foreach ($aryAdmin as $admin) {
            $AP     = [
                'bid'   => BID,
                'admin' => $admin,
            ];
            $Tpl->add($admin, [
                'url'   => acmsLink($AP),
            ]);
        }

        return $Tpl->get();
    }
}
