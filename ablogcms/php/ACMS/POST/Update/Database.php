<?php

use Acms\Services\Update\Engine;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;

class ACMS_POST_Update_Database extends ACMS_POST_Update_Base
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

        $updateService = new Engine($logger);

        Database::setThrowException(true);
        try {
            $updateService->validate(true);
            $updateService->dbUpdate();

            $this->addMessage(gettext('データベースのアップデートに成功しました。'));
            Logger::info('データベースをアップデートしました');
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            Logger::warning('データベースのアップデートに失敗しました。' . $e->getMessage(), Common::exceptionArray($e));
        }
        Database::setThrowException(false);

        return $this->Post;
    }
}
