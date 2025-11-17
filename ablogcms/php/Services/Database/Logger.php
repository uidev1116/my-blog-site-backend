<?php

namespace Acms\Services\Database;

use Acms\Services\Facades\LocalStorage;
use stdClass;

class Logger
{
    /**
     * @var string
     */
    protected $destinationPath = '';

    /**
     * @var stdClass
     */
    protected $json;

    /**
     * Logger constructor.
     * @param string $path
     */
    public function __construct($path)
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
     * Get json object
     *
     * @return stdClass
     */
    public function getJson(): stdClass
    {
        $this->load();
        return $this->json;
    }

    /**
     * 初期化
     */
    public function init()
    {
        if (is_writable($this->destinationPath)) {
            LocalStorage::remove($this->destinationPath);
        }
        $this->json = new stdClass();
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
        $json = LocalStorage::get($this->destinationPath);
        $this->json = json_decode($json);
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
     */
    public function addMessage($message, $percentage = 0, $status = 1)
    {
        $this->json->inProcess = $message;
        $this->json->percentage += $percentage;
        $this->json->processList[] = [
            'message' => $message,
            'status' => empty($status) ? 'ng' : 'ok',
        ];
        if ($this->json->percentage > 100) {
            $this->json->percentage = 100;
        }
        $this->build();
        sleep(2);
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

    public function addPercentage($percentage = 0)
    {
        $this->json->percentage += $percentage;
        if ((int)$this->json->percentage > 100) {
            $this->json->percentage = 100;
        }
        $this->build();
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
