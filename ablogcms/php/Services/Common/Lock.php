<?php

namespace Acms\Services\Common;

use Acms\Services\Facades\LocalStorage;

class Lock
{
    protected $lockFile;

    protected $fp;

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
    public function isLocked(): bool
    {
        if (LocalStorage::exists($this->lockFile) === false) {
            return false;
        }
        $fp = fopen($this->lockFile, 'r+'); // 既存ファイルを開くだけ
        if ($fp === false) {
            throw new \RuntimeException("Unable to open lock file: {$this->lockFile}");
        }
        $locked = !flock($fp, LOCK_EX | LOCK_NB); // 取れなければ他プロセスがロック中
        fclose($fp); // 判定だけなので即クローズ
        return $locked;
    }

    /**
     * ロックを取得
     *
     * @return bool
     */
    public function tryLock(): bool
    {
        $this->fp = fopen($this->lockFile, 'c');
        if ($this->fp === false) {
            throw new \RuntimeException("Unable to open lock file: {$this->lockFile}");
        }
        if (!flock($this->fp, LOCK_EX | LOCK_NB)) {
            fclose($this->fp);
            $this->fp = null;
            return false;
        }
        return true;
    }

    /**
     * ロックを解放
     *
     * @return void
     */
    public function release(): void
    {
        if ($this->fp) {
            flock($this->fp, LOCK_UN);
            fclose($this->fp);
            $this->fp = null;
        }
    }
}
