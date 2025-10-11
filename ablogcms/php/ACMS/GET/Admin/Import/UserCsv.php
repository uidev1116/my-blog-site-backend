<?php

use Acms\Services\Facades\LocalStorage;

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
        $logger = App::make('common.logger');
        $logger->setDestinationPath(CACHE_DIR . 'user-csv-import-logger.json');
        $rootVars = [];

        /**
         * CSVインポート中チェック
         */
        if (LocalStorage::exists($logger->getDestinationPath())) {
            $rootVars['processing'] = 1;
        } else {
            $rootVars['processing'] = 0;
        }
        $tpl->add(null, $rootVars);

        return $tpl->get();
    }
}
