<?php

namespace Acms\Services\Update;

use Acms\Services\Update\System\CheckForUpdate;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Cache;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\LocalStorage;
use RuntimeException;

class AutoUpdate
{
    /**
     * 自動システム更新
     *
     * @throws \RuntimeException
     * @return void
     */
    public function run(): void
    {
        try {
            LocalStorage::changeDir(SCRIPT_DIR);
            $config = Config::loadBlogConfigSet(RBID);

            if ($config->get('system_auto_update') !== 'on') {
                return; // 自動アップデート機能がONではありません
            }
            $startTime = (int) $config->get('system_auto_update_begin_time', 0);
            $endTime = (int) $config->get('system_auto_update_end_time', 0);
            $week = $config->getArray('system_auto_update_week');
            if (!$this->validateTimeFrame($startTime, $endTime, $week)) {
                return; // 設定された更新期間内でない
            }
            if (!$this->validateTimeInterval()) {
                return; // 前回の更新チェックから時間がたっていない
            }
            if (!$this->checkForUpdates()) {
                return; // 更新バージョンがない
            }
            /** @var \Acms\Services\Common\Lock $lockService */
            $lockService = Application::make('update.lock');
            if ($lockService->isLocked()) {
                return; // すでにアップデート中
            }
            $this->update($lockService);
        } catch (\Exception $e) {
            Logger::error($e->getMessage());
        }
    }

    /**
     * 設定された更新期間内か確認
     *
     * @param int $startTime
     * @param int $endTime
     * @param array $updatableDaysOfWeek
     * @return bool
     */
    protected function validateTimeFrame(int $startTime, int $endTime, array $updatableDaysOfWeek = []): bool
    {
        $updatableDaysOfWeek = array_map('intval', $updatableDaysOfWeek);
        $currentWeekday = (int) date('w', REQUEST_TIME);
        $currentTime = (int) date('G', REQUEST_TIME);

        if (!in_array($currentWeekday, $updatableDaysOfWeek, true)) {
            return false;
        }
        if ($startTime === $endTime) { // 同時刻の場合は24時間OKとみなす
            return true;
        } elseif ($startTime < $endTime) {
            if ($startTime <= $currentTime && $currentTime < $endTime) {
                return true;
            }
        } elseif ($startTime > $endTime) { // 日を跨ぐ
            if ($startTime <= $currentTime || $currentTime < $endTime) {
                return true;
            }
        }
        return false;
    }

    /**
     * 前回の更新チェックから時間（1時間）がたっているか確認
     *
     * @return bool
     */
    protected function validateTimeInterval(): bool
    {
        $tempCache = Cache::config();
        $cacheKey = 'system-update-time-interval';
        if ($tempCache->has($cacheKey)) {
            return false;
        }
        $tempCache->put($cacheKey, 'checked', 60 * 60); // システム更新のインターバルを1時間に設定

        return true;
    }

    /**
     * 更新バージョンがあるか確認
     *
     * @return bool
     */
    protected function checkForUpdates(): bool
    {
        /** @var \Acms\Services\Update\System\CheckForUpdate $updateCheckService */
        $updateCheckService = Application::make('update.check');

        Database::setThrowException(true);
        try {
            return $updateCheckService->check(phpversion(), CheckForUpdate::PATCH_VERSION);
        } catch (\Exception $e) {
            Logger::notice($e->getMessage(), Common::exceptionArray($e));
        }
        Database::setThrowException(false);

        return false;
    }

    /**
     * アップデートを実行
     *
     * @param \Acms\Services\Common\Lock $lockService
     * @return void
     */
    protected function update($lockService): void
    {
        Common::backgroundRedirect(HTTP_REQUEST_URL);

        /** @var \Acms\Services\Update\Operations\Update $updateService */
        $updateService = Application::make('update.exec.update');
        /** @var \Acms\Services\Update\LoggerFactory $loggerFactory */
        $loggerFactory = Application::make('update.logger');

        Logger::info('自動アップデートを開始しました');

        $updateService->init();
        $range = CheckForUpdate::PATCH_VERSION;
        $logger = $loggerFactory->createLogger('auto');
        $updateService->exec($logger, $lockService, $range, true);

        die();
    }
}
