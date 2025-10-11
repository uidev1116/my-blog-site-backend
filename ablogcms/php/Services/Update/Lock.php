<?php

namespace Acms\Services\Update;

use Acms\Services\Facades\LocalStorage;

class Lock
{
    protected $lockFile;

    /**
     * Constructor
     */
    public function __construct(string $lockFile)
    {
        $this->lockFile = $lockFile;
    }

    /**
     * 実行中か判定
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        if (LocalStorage::exists($this->lockFile)) {
            $lastModified = LocalStorage::lastModified($this->lockFile);
            if (REQUEST_TIME - $lastModified > (60 * 60)) {
                return false; // ロックファイルが作成されてから一時間以上の場合、ロックされていないとみなす
            }
            return true;
        }
        return false;
    }

    /**
     * ロックファイルを作成
     *
     * @return void
     */
    public function createLockFile(): void
    {
        LocalStorage::put($this->lockFile, 'lock');
    }

    /**
     * ロックファイルを削除
     *
     * @return void
     */
    public function removeLockFile(): void
    {
        LocalStorage::remove($this->lockFile);
    }
}
