<?php

namespace Acms\Services\Update\System;

use Acms\Services\Update\Contracts\LoggerInterface;
use Acms\Services\Update\Exceptions\RollbackException;
use Acms\Services\Facades\LocalStorage;
use Symfony\Component\Finder\Finder;
use Exception;
use RuntimeException;

/**
 * Class PlaceFile
 * @package Acms\Services\Update\System
 */
class PlaceFile
{
    /**
     * @var array
     */
    protected $moveList;

    /**
     * @var array
     */
    protected $exclusionMoveFile;

    /**
     * @var array
     */
    protected $backupList;

    /**
     * @var \Acms\Services\Update\Contracts\LoggerInterface
     */
    protected $logger;

    /**
     * PlaceFile constructor.
     *
     * @param \Acms\Services\Update\Contracts\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        set_time_limit(0);

        $this->logger = $logger;

        $this->moveList = [
            'js' => 'js',
            'lang' => 'lang',
            'php' => 'php',
            'private/config.system.default.yaml' => 'private/config.system.default.yaml',
            'themes/system' => 'themes/system',
            'acms.js' => 'acms.js',
            'index.php' => 'index.php',
            'setup' => '_setup_' . date('YmdHi'),
        ];

        $this->exclusionMoveFile = array_merge(configArray('system_update_ignore'), [
            'php/AAPP',
            'php/ACMS/User',
        ]);

        $this->backupList = [];
    }

    /**
     * Validate
     *
     * @param $new_path
     * @param $backup_dir
     * @throws \Exception
     */
    public function validate($new_path, $backup_dir)
    {
        $this->logger->message(gettext('アップデートの検証中...'), 0);
        $validate = true;
        // backup
        foreach ($this->moveList as $item => $to) {
            if ($item === 'setup') {
                continue;
            }
            $path = $backup_dir . $item;
            LocalStorage::makeDirectory(dirname($path));
            if (!LocalStorage::isWritable(dirname($path))) {
                $validate = false;
                $this->logger->failure(gettext('書き込み権限がありません。') . ' ' . $path);
            }
        }

        // place file
        foreach ($this->moveList as $from => $to) {
            if (!LocalStorage::exists($to)) {
                $to = dirname($to);
            }
            if (!LocalStorage::isWritable($to)) {
                $validate = false;
                $this->logger->failure(gettext('書き込み権限がありません。') . ' ' . $to);
            }
        }
        foreach ($this->exclusionMoveFile as $item) {
            if (!LocalStorage::isWritable($item)) {
                $validate = false;
                $this->logger->failure(gettext('書き込み権限がありません。') . ' ' . $item);
            }
        }
        if (!$validate) {
            throw new RuntimeException(gettext('アップデートの検証に失敗しました。'));
        }
        sleep(5);
        $this->logger->message(gettext('アップデートの検証完了'), 0);
    }

    /**
     * Run
     *
     * @param $new_path
     * @param $backup_dir
     * @param $new_setup
     * @throws \Exception
     */
    public function exec($new_path, $backup_dir, $new_setup = false)
    {
        try {
            if ($new_setup) {
                $this->removeSetup();
            } else {
                unset($this->moveList['setup']);
            }
            $this->backup($backup_dir);
            $this->updateFiles($new_path, $backup_dir);
        } catch (Exception $e) {
            $this->rollback($backup_dir);
            throw new RollbackException($e->getMessage());
        }
    }

    /**
     * Backup
     *
     * @param string $backup_dir
     */
    protected function backup($backup_dir)
    {
        $this->logger->message(gettext('バックアップを作成中...'));
        LocalStorage::makeDirectory(dirname($backup_dir . 'tmp/'));
        $this->copyFiles($this->exclusionMoveFile, '', $backup_dir . 'tmp/');
        $this->copyFiles($this->backupList, '', $backup_dir);
        $this->logger->message(gettext('バックアップの作成完了'));
    }

    /**
     * System Update
     *
     * @param string $new_path
     * @param string $backup_dir
     */
    protected function updateFiles($new_path, $backup_dir)
    {
        $this->logger->message(gettext('システムファイルを展開中...'), 0);
        $base = $new_path . '/';
        $percentage = intval(25 / count($this->moveList));

        foreach ($this->moveList as $from => $to) {
            // 現在のファイルをbackupに退避
            LocalStorage::makeDirectory(dirname($backup_dir . $to));
            LocalStorage::move($to, $backup_dir . $to);

            // Newバージョンのファイルを設置
            if (!LocalStorage::move($base . $from, $to)) {
                throw new RuntimeException('Could not be moved from ' . $base . $from . ' to ' . $to . '.');
            }
            $this->logger->incrementProgress($percentage);
        }
        // アップデートしないファイル（拡張ファイル）を戻す
        $this->copyFiles($this->exclusionMoveFile, $backup_dir . 'tmp/', '');
        $this->logger->message(gettext('システムファイルを展開完了'), 5);
    }

    /**
     * Rollback
     *
     * @param string $backup_dir
     */
    protected function rollback($backup_dir)
    {
        $this->logger->message(gettext('ロールバック中...'));

        foreach ($this->moveList as $item => $to) {
            if ($item === 'setup') {
                continue;
            }
            try {
                LocalStorage::makeDirectory(dirname($backup_dir . 'rollback/' . $to));
                LocalStorage::move($to, $backup_dir . 'rollback/' . $to);
                LocalStorage::move($backup_dir . $item, $item);
            } catch (Exception $e) {
                $this->logger->failure($e->getMessage());
            }
        }
        $this->logger->message(gettext('ロールバック終了'));
    }

    /**
     * Remove setup
     */
    protected function removeSetup()
    {
        $finder = new Finder();
        $lists = [];
        $iterator = $finder
            ->in('./')
            ->depth('< 2')
            ->name('/^\_setup\_.+/')
            ->directories();

        foreach ($iterator as $dir) {
            $lists[] = $dir->getRelativePathname();
        }
        foreach ($lists as $item) {
            try {
                LocalStorage::removeDirectory($item);
            } catch (Exception $e) {
            }
        }
    }

    /**
     * Copy files
     *
     * @param array $list
     * @param string $from_dir
     * @param string $to_dir
     */
    protected function copyFiles($list, $from_dir, $to_dir)
    {
        foreach ($list as $item) {
            $from = $from_dir . $item;
            $to = $to_dir . $item;
            LocalStorage::makeDirectory(dirname($to));
            if (is_link($from)) {
                if ($link = readlink($from)) {
                    $from = $link;
                }
            }
            if (LocalStorage::isDirectory($from) && LocalStorage::exists($from)) {
                if (!LocalStorage::copyDirectory($from, $to)) {
                    throw new RuntimeException('Could not be copied from ' . $from . ' to ' . $to . '.');
                }
            } elseif (LocalStorage::exists($from)) {
                if (!LocalStorage::copy($from, $to)) {
                    throw new RuntimeException('Could not be copied from ' . $from . ' to ' . $to . '.');
                }
            }
        }
    }
}
