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
            'CSV'          => 'import_csv',
        ];
        if (LICENSE_BLOG_LIMIT == UNLIMITED_NUMBER_OF_USERS) {
            $aryAdmin['USER']   = 'import_user';
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
