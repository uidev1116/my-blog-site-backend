<?php

namespace Acms\Services\Update\Operations;

use Acms\Services\Update\Contracts\LoggerInterface;
use Acms\Services\Update\System\CheckForUpdate;
use Acms\Services\Common\Lock;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger as AcmsLogger;
use RuntimeException;

class Downgrade extends Update
{
    public function exec(LoggerInterface $logger, Lock $lockService, int $range = CheckForUpdate::PATCH_VERSION, bool $createSetup = true): void
    {
        $destDir = ARCHIVES_DIR . uniqueString() . '/';

        Database::setThrowException(true);

        try {
            $lockService->tryLock();
            $logger->init();

            // アップデートパッケージを検証
            $package = $this->validatePackage($range);
            $downloadUrl = $package->getDownGradePackageUrl();
            $rootDir = $package->getRootDir();

            // システムファイルを更新
            $this->updateSystemFiles($logger, $downloadUrl, $rootDir, $destDir, $createSetup);
            $logger->fileUpdateSuccess();

            // データベースを更新
            $this->updateDatabase($logger, $package->getDownGradeVersion());
            $logger->dbUpdateSuccess();

            // トライアルの日付を更新
            setTrial();

            AcmsLogger::info('ダウングレードが完了しました');
        } catch (\Exception $e) {
            $logger->failure($e->getMessage());
            sleep(3);
            $logger->terminate();

            AcmsLogger::warning('ダウングレードに失敗しました。' . $e->getMessage(), Common::exceptionArray($e));
        }

        Database::setThrowException(false);
        $logger->message(gettext('ダウンロードファイルを削除中...'), 0);
        $this->removeDirectory($destDir);
        $lockService->release();

        // opcodeキャッシュをリセット
        if (function_exists("opcache_reset")) {
            opcache_reset();
        }
        sleep(3);
        $logger->terminate();
    }

    /**
     * パッケージを検証
     *
     * @param int $range
     * @throws \RuntimeException
     * @return \Acms\Services\Update\System\CheckForUpdate
     */
    protected function validatePackage(int $range = CheckForUpdate::PATCH_VERSION): CheckForUpdate
    {
        $checkUpdateService = Application::make('update.check');

        if ($checkUpdateService->checkDownGradeUseCache(phpversion())) {
            return $checkUpdateService;
        }
        throw new RuntimeException(gettext('パッケージ情報の取得に失敗しました'));
    }
}
