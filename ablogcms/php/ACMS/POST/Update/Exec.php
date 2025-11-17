<?php

use Acms\Services\Update\System\CheckForUpdate;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;

class ACMS_POST_Update_Exec extends ACMS_POST_Update_Base
{
    public function post()
    {
        if (!$this->validatePermissions()) {
            $this->addError(gettext('アップデートする権限がありません。'));
            return $this->Post;
        }
        /** @var \Acms\Services\Update\Operations\Update $updateService */
        $updateService = Application::make('update.exec.update');
        /** @var \Acms\Services\Common\Lock $lockService */
        $lockService = Application::make('update.lock');
        /** @var \Acms\Services\Update\LoggerFactory $loggerFactory */
        $loggerFactory = Application::make('update.logger');

        $updateService->init();
        if ($lockService->isLocked()) {
            $this->addError(gettext('アップデートを中止しました。すでにアップデート中の可能性があります。変化がない場合は、cache/system-update-lock ファイルを削除してお試しください。'));
            return $this->Post;
        }
        Common::backgroundRedirect(HTTP_REQUEST_URL);

        Logger::info('アップデートを開始しました');

        $range = CheckForUpdate::PATCH_VERSION;
        if (config('system_update_range') === 'minor') {
            $range = CheckForUpdate::MINOR_VERSION;
        }
        $logger = $loggerFactory->createLogger('web');
        $createSetup = $this->Post->get('new_setup') === 'create';
        $updateService->exec($logger, $lockService, $range, $createSetup);
        die();
    }
}
