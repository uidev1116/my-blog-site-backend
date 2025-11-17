<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;

class ACMS_POST_Update_DownGradeExec extends ACMS_POST_Update_Base
{
    public function post()
    {
        /** @var \Acms\Services\Update\Operations\Downgrade $downgradeService */
        $downgradeService = Application::make('update.exec.downgrade');
        /** @var \Acms\Services\Common\Lock $lockService */
        $lockService = Application::make('update.lock');
        /** @var \Acms\Services\Update\LoggerFactory $loggerFactory */
        $loggerFactory = Application::make('update.logger');

        if (!$this->validatePermissions()) {
            $this->addError(gettext('ダウングレードする権限がありません。'));
            return $this->Post;
        }
        $downgradeService->init();
        if ($lockService->isLocked()) {
            $this->addError(gettext('ダウングレードを中止しました。すでにダウングレード中の可能性があります。変化がない場合は、cache/system-update-lock ファイルを削除してお試しください。'));
            return $this->Post;
        }
        Common::backgroundRedirect(HTTP_REQUEST_URL);

        Logger::info('ダウングレードを開始しました');

        $logger = $loggerFactory->createLogger('web');
        $createSetup = $this->Post->get('new_setup') === 'create';
        $downgradeService->exec($logger, $lockService, 0, $createSetup);
        die();
    }
}
