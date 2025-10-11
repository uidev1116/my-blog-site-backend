<?php

class ACMS_GET_Admin_Dashboard_ThemeChanger extends ACMS_GET
{
    function get()
    {
        if (roleAvailableUser()) {
            if (!roleAuthorization('publish_edit', BID) && !roleAuthorization('publish_exec', BID)) {
                die403();
            }
        } else {
            if (!sessionWithAdministration()) {
                die403();
            }
        }
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];

        $thmPath = SCRIPT_DIR . THEMES_DIR;
        $curThm = config('theme');

        if (LocalStorage::isDirectory($thmPath)) {
            $dh = opendir($thmPath);
            if ($dh === false) {
                return '';
            }
            while (false != ($dir = readdir($dh))) {
                $vars = ['theme' => $dir, 'label' => $dir];

                if (!LocalStorage::isDirectory($thmPath . $dir)) {
                    continue;
                } elseif ($dir == 'system') {
                    continue;
                } elseif ($dir == '.' || $dir == '..') {
                    continue;
                } elseif ($dir == $curThm) {
                    $vars['selected'] = 'selected="selected"';
                }

                $Tpl->add('theme:loop', $vars);
            }
        }

        return $Tpl->get();
    }
}
