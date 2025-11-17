<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Admin_Export_WordPress extends ACMS_GET_Admin
{
    public function get()
    {
        if ('export_wordpress' !== ADMIN) {
            return '';
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $rootVars = [];

        /**
         * CSVインポート中チェック
         */
        $lockService = Application::make('export-wxr-lock');
        if ($lockService->isLocked()) {
            $rootVars['processing'] = 1;
        } else {
            $rootVars['processing'] = 0;
        }
        $tpl->add(null, $rootVars);

        return $tpl->get();
    }
}
