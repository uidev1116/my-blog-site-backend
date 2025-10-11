<?php

use Acms\Services\Update\System\CheckForUpdate;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Database;

class ACMS_POST_Update_CheckForUpdate extends ACMS_POST_Update_Base
{
    public function post()
    {
        if (!$this->validatePermissions()) {
            $this->addError(gettext('権限がありません。'));
            return $this->Post;
        }

        $check = Application::make('update.check');
        Database::setThrowException(true);
        try {
            if (!$check->check(phpversion(), CheckForUpdate::PATCH_VERSION)) {
                return $this->Post;
            }
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            Logger::notice($e->getMessage(), Common::exceptionArray($e));
        }
        Database::setThrowException(false);

        return $this->Post;
    }
}
