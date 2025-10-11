<?php

class ACMS_GET_Admin_Title extends ACMS_GET_Admin
{
    function get()
    {
        if (!SUID) {
            return '';
        }
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());

        $aryAdmin   = [];
        if ('form_log' == ADMIN) {
            $aryAdmin[] = 'form_index';
            $aryAdmin[] = 'form_edit';
            $aryAdmin[] = 'form_log';
        } elseif ('shop' == substr(ADMIN, 0, strlen('shop'))) {
            if ('shop_menu' != ADMIN) {
                $aryAdmin[] = 'shop_menu';
            }
            if (preg_match('@_edit$@', ADMIN)) {
                $aryAdmin[] = str_replace('_edit', '_index', ADMIN);
            }
            $aryAdmin[] = ADMIN;
        } elseif ('schedule' == substr(ADMIN, 0, strlen('schedule'))) {
            if ('schedule_index' != ADMIN) {
                $aryAdmin[] = 'schedule_index';
            }
            $aryAdmin[] = ADMIN;
        } elseif ('config' == substr(ADMIN, 0, strlen('config'))) {
            if (!!$this->Get->get('rid')) {
                $aryAdmin[] = 'rule_index';
                $aryAdmin[] = 'rule_edit';
            } elseif (!!$this->Get->get('mid')) {
                $aryAdmin[] = 'module_index';
                $aryAdmin[] = 'module_edit';
            }
            if (!$this->Get->get('mid')) {
                $aryAdmin[] = 'config_index';
            }
            if ('config_index' <> ADMIN) {
                $aryAdmin[] = ADMIN;
            }
        } elseif (preg_match('@_edit$@', ADMIN)) {
            if (!('user_edit' == ADMIN and !sessionWithContribution())) {
                $aryAdmin[] = str_replace('_edit', '_index', ADMIN);
            }
            $aryAdmin[] = ADMIN;
        } elseif ('import' == substr(ADMIN, 0, strlen('import'))) {
            if ('import_index' != ADMIN) {
                $aryAdmin[] = 'import_index';
            }
            $aryAdmin[] = ADMIN;
        } else {
            $aryAdmin[] = ADMIN;
        }

        foreach ($aryAdmin as $admin) {
            $Tpl->add($admin);
        }

        return $Tpl->get();
    }
}
