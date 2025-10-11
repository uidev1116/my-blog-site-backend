<?php

namespace Acms\Services\StaticExport;

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;

class TerminateCheck
{
    /**
     * @var string
     */
    protected $loggerPath;

    /**
     * @var string
     */
    protected $terminateFlagPath;

    /**
     * TerminateCheck constructor.
     * @param string $logger_path
     * @param string $terminate_flag_path
     */
    public function __construct($logger_path, $terminate_flag_path)
    {
        $this->loggerPath = $logger_path;
        $this->terminateFlagPath = $terminate_flag_path;
    }

    /**
     * 処理を中断する
     */
    public function terminate()
    {
        LocalStorage::remove($this->loggerPath);
        LocalStorage::put($this->terminateFlagPath, 'terminate');
    }

    /**
     * 処理中断フラグの削除
     */
    public function removeFlag()
    {
        LocalStorage::remove($this->terminateFlagPath);
    }

    /**
     * 処理が中断したか判定
     */
    public function check()
    {
        if (LocalStorage::exists($this->terminateFlagPath)) {
            LocalStorage::remove($this->loggerPath);
            $this->removeFlag();
            Common::setSafeHeadersWithoutCache(200, 'text/plain');
            die();
        }
    }
}
