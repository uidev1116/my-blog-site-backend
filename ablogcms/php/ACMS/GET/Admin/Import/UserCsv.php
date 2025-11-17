<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Admin_Import_UserCsv extends ACMS_GET_Admin
{
    public function get()
    {
        if ('import_user' !== ADMIN) {
            return '';
        }
        if (!sessionWithAdministration()) {
            die403();
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $rootVars = [];

        /**
         * CSVインポート中チェック
         */
        $lockService = Application::make('user.import.csv-lock');
        if ($lockService->isLocked()) {
            $rootVars['processing'] = 1;
        } else {
            $rootVars['processing'] = 0;
        }
        $tpl->add(null, $rootVars);

        return $tpl->get();
    }
}
