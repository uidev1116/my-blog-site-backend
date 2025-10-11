<?php

use Acms\Services\Facades\Application;

class ACMS_POST_Update_InitLog extends ACMS_POST_Update_Base
{
    public function post()
    {
        if (!$this->validatePermissions()) {
            $this->addError(gettext('権限がありません。'));
            return $this->Post;
        }
        /** @var \Acms\Services\Update\LoggerFactory $loggerFactory */
        $loggerFactory = Application::make('update.logger');
        $logger = $loggerFactory->createLogger('web');

        /** @var \Acms\Services\Update\Lock $lockService */
        $lockService = Application::make('update.lock');

        $logger->terminate();
        $lockService->removeLockFile();

        return $this->Post;
    }
}
