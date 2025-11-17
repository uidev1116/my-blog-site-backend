<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Admin_Backup_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if ('backup_index' <> ADMIN) {
            return '';
        }
        if (roleAvailableUser()) {
            if (!roleAuthorization('backup_export', BID)) {
                die403();
            }
        } else {
            if (!sessionWithAdministration()) {
                die403();
            }
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $rootVars = [];

        /**
         * DBエクスポート中チェック
         */
        $dbLockService = Application::make('db.backup-lock');
        if ($dbLockService->isLocked()) {
            $rootVars['processing'] = 1;
        } else {
            $rootVars['processing'] = 0;
        }

        /**
         * アーカイブ、エクスポート中チェック
         */
        $archiveLockService = Application::make('archive.backup-lock');
        if ($archiveLockService->isLocked()) {
            $rootVars['archivesProcessing'] = 1;
        } else {
            $rootVars['archivesProcessing'] = 0;
        }

        $tpl->add(null, $rootVars);
        return $tpl->get();
    }
}
