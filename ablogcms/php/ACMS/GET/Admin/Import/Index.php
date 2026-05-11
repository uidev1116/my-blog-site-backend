<?php

class ACMS_GET_Admin_Import_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if ('import_index' !== ADMIN) {
            return '';
        }
        if (!sessionWithAdministration()) {
            die403();
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());


        $aryAdmin   = [
            'WordPress'    => 'import_wordpress',
            'Movable Type' => 'import_mt',
            'エントリー（CSV）'          => 'import_csv',
        ];
        if (licenseHasUnlimitedUsers()) {
            $aryAdmin['ユーザー（CSV）']   = 'import_user';
        }

        foreach ($aryAdmin as $label => $admin) {
            $AP     = [
                'bid'   => BID,
                'admin' => $admin,
            ];

            $Tpl->add('type:loop', [
                'url'   => acmsLink($AP),
                'label' => $label,
            ]);
        }

        return $Tpl->get();
    }
}
