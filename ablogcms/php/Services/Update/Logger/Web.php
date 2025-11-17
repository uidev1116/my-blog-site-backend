<?php

namespace Acms\Services\Update\Logger;

use Acms\Services\Update\Contracts\LoggerInterface;
use Acms\Services\Update\Contracts\WebLoggerInterface;
use Acms\Services\Facades\LocalStorage;
use stdClass;

class Web implements LoggerInterface, WebLoggerInterface
{
    /**
     * @var string
     */
    private $destinationPath = '';

    /**
     * @var stdClass
     */
    private $json;

    /**
     * ログ書き出し先を取得
     *
     * @return string
     */
    public function getDestinationPath(): string
    {
        return $this->destinationPath;
    }

    /**
     * ログ書き出し先を設定
     *
     * @param string $path
     * @return void
     */
    public function setDestinationPath(string $path): void
    {
        $this->destinationPath = $path;
    }

    /**
     * ファイルからログをロード
     */
    public function load(): void
    {
        $json = LocalStorage::get($this->destinationPath);
        $this->json = json_decode($json);
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
     *
     * @return void
     */
    public function init(): void
    {
        if (is_writable($this->destinationPath)) {
            LocalStorage::remove($this->destinationPath);
        }
        $this->json = new stdClass();
        $this->json->processing = true;
        $this->json->updatedAt = date('c');
        $this->json->fileUpdateSuccess = false;
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
     * メッセージを表示
     *
     * @param string $message
     * @param int $percentage
     * @param int $status
     */
    public function message(string $message, int $percentage = 0, int $status = 1): void
    {
        $this->json->inProcess = $message;
        $this->json->processList[] = [
            'message' => $message,
            'status' => empty($status) ? 'ng' : 'ok',
        ];
        $this->incrementProgress($percentage);
        sleep(2);
    }

    /**
     * 進行状況（パーセント）を追加
     *
     * @param int $percentage
     * @return void
     */
    public function incrementProgress($percentage = 0): void
    {
        $this->json->percentage += $percentage;
        if ((int)$this->json->percentage > 100) {
            $this->json->percentage = 100;
        }
        $this->build();
    }

    /**
     * ファイルのアップデート成功時
     *
     * @return void
     */
    public function fileUpdateSuccess(): void
    {
        $this->json->fileUpdateSuccess = true;
        $this->build();
    }

    /**
     * アップデート成功時
     *
     * @return void
     */
    public function dbUpdateSuccess(): void
    {
        $this->json->success = true;
        $this->build();
    }

    /**
     * アップデート失敗時
     *
     * @param string $message
     * @return void
     */
    public function failure(string $message): void
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
     * アップデート終了時
     *
     * @return void
     */
    public function terminate(): void
    {
        sleep(3);

        if (property_exists($this->json, 'processing')) {
            $this->json->processing = false;
            $this->build();
        }

        sleep(3);
        LocalStorage::remove($this->destinationPath);
    }

    /**
     * JSON生成
     *
     * @return void
     */
    protected function build(): void
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
