<?php

namespace Acms\Services\Update\Operations;

use Acms\Services\Update\Contracts\LoggerInterface;
use Acms\Services\Update\Engine;
use Acms\Services\Update\System\Download;
use Acms\Services\Update\System\PlaceFile;
use Acms\Services\Update\System\CheckForUpdate;
use Acms\Services\Update\Exceptions\RollbackException;
use Acms\Services\Common\Lock;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Mailer;
use Acms\Services\Facades\Logger as AcmsLogger;
use RuntimeException;
use Exception;
use Field;

class Update
{
    public function init(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);
        LocalStorage::changeDir(SCRIPT_DIR);
    }

    public function exec(LoggerInterface $logger, Lock $lockService, int $range = CheckForUpdate::PATCH_VERSION, bool $createSetup = true): void
    {
        $destDir = ARCHIVES_DIR . uniqueString() . '/';

        Database::setThrowException(true);

        try {
            $lockService->tryLock();
            $logger->init();

            // アップデートパッケージを検証
            $package = $this->validatePackage($range);
            $downloadUrl = $package->getPackageUrl();
            $rootDir = $package->getRootDir();

            // システムファイルを更新
            $this->updateSystemFiles($logger, $downloadUrl, $rootDir, $destDir, $createSetup);
            $logger->fileUpdateSuccess();

            // データベースを更新
            $this->updateDatabase($logger, $package->getUpdateVersion());
            $logger->dbUpdateSuccess();

            // トライアルの日付を更新
            setTrial();

            // 成功時の処理
            $this->handleSuccess($package);
            AcmsLogger::info('アップデートが完了しました');
        } catch (RollbackException $e) {
            $logger->failure($e->getMessage());
            AcmsLogger::warning('アップデートに失敗したため、ロールバックしました。' . $e->getMessage(), Common::exceptionArray($e));
            $this->notify('rollback', $e->getMessage());
        } catch (Exception $e) {
            $logger->failure($e->getMessage());
            AcmsLogger::warning('アップデートに失敗しました。' . $e->getMessage(), Common::exceptionArray($e));
            $this->notify('failed', $e->getMessage());
        } finally {
            $lockService->release();
            sleep(3);
            $logger->terminate();
        }

        Database::setThrowException(false);
        $logger->message(gettext('ダウンロードファイルを削除中...'), 0);
        $this->removeDirectory($destDir);

        // opcodeキャッシュをリセット
        if (function_exists("opcache_reset")) {
            opcache_reset();
        }
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

        if ($checkUpdateService->checkUseCache(phpversion(), $range)) {
            return $checkUpdateService;
        }
        throw new RuntimeException(gettext('パッケージ情報の取得に失敗しました'));
    }

    /**
     * システムファイルを更新
     *
     * @param string $downloadUrl
     * @param string $rootDir
     * @param string $destDir
     * @param bool $createSetup
     * @throws RuntimeException
     * @return void
     */
    protected function updateSystemFiles(LoggerInterface $logger, string $downloadUrl, string $rootDir, string $destDir, bool $createSetup): void
    {
        $newPath = "{$destDir}{$rootDir}";
        $backupDir = 'private/' . 'backup' . date('YmdHis') . '/';
        if (!LocalStorage::isWritable('private')) {
            throw new RuntimeException(gettext('privateディレクトリに書き込み権限を与えてください'));
        }
        $downloadService = new Download($logger);
        $placeFileService = new PlaceFile($logger);

        // validate
        $placeFileService->validate($newPath, $backupDir);

        // Update system file
        $downloadService->download($destDir, $downloadUrl);
        $placeFileService->exec($newPath, $backupDir, $createSetup);
    }

    /**
     * データベースを更新
     *
     * @param \Acms\Services\Update\Contracts\LoggerInterface $logger
     * @param string $version
     * @return void
     */
    protected function updateDatabase(LoggerInterface $logger, string $version): void
    {
        $dbUpdateService = new Engine($logger);
        $dbUpdateService->setUpdateVersion($version);
        $dbUpdateService->validate(true);
        $dbUpdateService->update();
    }


    /**
     * ディレクトリを削除
     *
     * @param string $path
     * @return void
     */
    protected function removeDirectory(string $path): void
    {
        if (PHP_OS === 'Windows') {
            exec(sprintf("rd /s /q %s", escapeshellarg($path)));
        } else {
            exec(sprintf("rm -rf %s", escapeshellarg($path)));
        }
        LocalStorage::removeDirectory($path);
    }

    /**
     * 成功時の処理
     *
     * @param \Acms\Services\Update\System\CheckForUpdate $package
     * @return void
     */
    protected function handleSuccess(CheckForUpdate $package): void
    {
        $releaseNoteMessage = '';
        if ($releaseNote = $package->getReleaseNote()) {
            foreach ($releaseNote as $note) {
                $releaseNoteMessage .= "\nVer. {$note->version}\n";
                foreach ($note->logs->features as $message) {
                    $releaseNoteMessage .= "・{$message}\n";
                }
                foreach ($note->logs->changes as $message) {
                    $releaseNoteMessage .= "・{$message}\n";
                }
                foreach ($note->logs->fix as $message) {
                    $releaseNoteMessage .= "・{$message}\n";
                }
            }
        }
        $this->notify('success', $releaseNoteMessage, $package->getUpdateVersion());
    }

    /**
     * システム更新結果をメール通知
     *
     * @param 'success' | 'rollback' | 'failed' $result
     * @param string $message
     * @param string $version
     * @return void
     */
    protected function notify(string $result, string $message, string $version = ''): void
    {
        try {
            $config = Config::loadBlogConfigSet(RBID);

            $subjectTplPath = $config->get('system_update_tpl_subject');
            $subjectTpl = findTemplate($subjectTplPath);
            if (empty($subjectTpl)) {
                throw new RuntimeException("件名のメールテンプレートが存在しません（{$subjectTpl}）");
            }
            $bodyTplPath = $config->get('system_update_tpl_body_plain');
            $bodyTpl = findTemplate($bodyTplPath);
            if (empty($bodyTpl)) {
                throw new RuntimeException("本文のメールテンプレートが存在しません（{$bodyTplPath}）");
            }
            $field = new Field();
            $field->add('result', $result);
            $field->add('message', $message);
            $field->add('version', $version);
            $subject = Common::getMailTxt($subjectTpl, $field);
            $body = Common::getMailTxt($bodyTpl, $field);

            $from = $config->get('system_update_from');
            $to = $config->get('system_update_to');

            if (empty($to)) {
                $userService = Application::make('user');
                $admin = $userService->getAdminUserWithMinId();
                $to = $admin['user_mail'] ?? false;
            }
            if (empty($to)) {
                throw new RuntimeException('送信先アドレスが空です');
            }
            if (empty($from)) {
                throw new RuntimeException('送信元アドレスが空です');
            }
            $mailer = Mailer::init();
            $mailer = $mailer->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body);

            if ($bodyHtmlTpl = findTemplate($config->get('system_update_tpl_body_html'))) {
                $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $field);
                $mailer = $mailer->setHtml($bodyHtml);
            }
            $mailer->send();
        } catch (Exception $e) {
            AcmsLogger::warning('システム更新の通知メール送信に失敗しました', Common::exceptionArray($e));
        }
    }
}
