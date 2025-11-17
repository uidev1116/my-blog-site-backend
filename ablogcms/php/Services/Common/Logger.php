<?php

namespace Acms\Services\Common;

use Acms\Services\Facades\LocalStorage;

class Logger
{
    /**
     * @var string
     */
    protected $destinationPath = '';

    /**
     * @var \stdClass
     */
    protected $json;

    /**
     * Setter $destinationPath
     * @param string $path
     */
    public function setDestinationPath($path)
    {
        if (!is_writable(dirname($path))) {
            throw new \RuntimeException($path . ' is not writable.');
        }
        $this->destinationPath = $path;
    }

    /**
     * Getter $destinationPath
     *
     * @return string
     */
    public function getDestinationPath()
    {
        return $this->destinationPath;
    }

    /**
     * 初期化
     */
    public function init()
    {
        if (is_writable($this->destinationPath)) {
            LocalStorage::remove($this->destinationPath);
        }
        $this->json = new \stdClass();
        $this->json->processing = true;
        $this->json->updatedAt = date('c');
        $this->json->success = false;
        $this->json->error = '';
        $this->json->inProcess = '';
        $this->json->percentage = 0;
        $this->json->processList = [];

        if ($json = json_encode($this->json)) {
            LocalStorage::put($this->destinationPath, $json);
        }
    }

    /**
     * ファイルからロード
     */
    public function load()
    {
        try {
            $json = LocalStorage::get($this->destinationPath);
            $this->json = json_decode($json, false);
        } catch (\Exception $e) {
        }
    }

    /**
     * Get json object
     *
     * @return \stdClass
     */
    public function getJson()
    {
        $this->load();
        return $this->json;
    }

    /**
     * 終了処理
     */
    public function terminate()
    {
        sleep(3);

        if ($this->json) {
            $this->json->processing = false;
            $this->build();
        }

        sleep(3);
        LocalStorage::remove($this->destinationPath);
    }

    /**
     * メッセージを追加
     *
     * @param string $message
     * @param int $percentage
     * @param int $status
     * @param boolean $log
     */
    public function addMessage($message, $percentage = 0, $status = 1, $log = true)
    {
        $this->json->inProcess = $message;
        $this->json->percentage += $percentage;
        if ($log) {
            $this->addProcessLog($message, $status);
        }
        if ($this->json->percentage > 100) {
            $this->json->percentage = 100;
        }
        $this->build();
    }

    public function addProcessLog($message, $status = 1)
    {
        $this->json->processList[] = [
            'message' => $message,
            'status' => empty($status) ? 'ng' : 'ok',
        ];
    }

    /**
     * 成功時
     */
    public function success()
    {
        $this->json->success = true;
        $this->build();
    }

    /**
     * エラー処理
     * @param $message
     */
    public function error($message)
    {
        $this->json->error = $message;
        $this->json->processList[] = [
            'message' => $message,
            'status' => 'ng',
        ];
        $this->json->percentage = 100;
        $this->build();
    }

    /**
     * @return int
     */
    public function getPercentage()
    {
        return $this->json->percentage;
    }

    /**
     * @param int $percentage
     */
    public function addPercentage($percentage = 0)
    {
        $this->json->percentage += $percentage;
        if ($this->json->percentage > 100) {
            $this->json->percentage = 100;
        }
        $this->build();
    }

    /**
     * 処理中か判定
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        if (LocalStorage::exists($this->getDestinationPath())) {
            $lastModified = LocalStorage::lastModified($this->getDestinationPath());
            if (REQUEST_TIME - $lastModified > (60 * 60 * 12)) {
                return false; // ファイルが作成されてから12時間以上の場合、処理を終了したとみなす
            }
            return true;
        }
        return false;
    }

    /**
     * JSON出力
     */
    protected function build()
    {
        if (!is_writable($this->destinationPath)) {
            return;
        }
        $this->json->updatedAt = date('c');

        if ($json = json_encode($this->json)) {
            LocalStorage::put($this->destinationPath, $json);
        }
    }
}
