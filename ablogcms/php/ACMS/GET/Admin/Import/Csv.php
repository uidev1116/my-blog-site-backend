<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Admin_Import_Csv extends ACMS_GET_Admin
{
    public function get()
    {
        if ('import_csv' !== ADMIN) {
            return '';
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $rootVars = [];

        /**
         * CSVインポート中チェック
         */
        $lockService = Application::make('entry.import.csv-lock');
        if ($lockService->isLocked()) {
            $rootVars['processing'] = 1;
        } else {
            $rootVars['processing'] = 0;
        }
        $tpl->add(null, $rootVars);

        return $tpl->get();
    }
}
