<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\PrivateStorage;

class ACMS_POST_Export_Remove extends ACMS_POST
{
    /**
     * @inheritDoc
     */
    public function post()
    {
        try {
            set_time_limit(0);
            if (!sessionWithAdministration()) {
                throw new \RuntimeException('Permission denied.');
            }

            $fileName = $this->Post->get('export_file');

            if (empty($fileName)) {
                throw new \RuntimeException('ファイルが指定されていません。');
            }
            $path = MEDIA_STORAGE_DIR . 'export_wxr/' . $fileName;
            if (!PrivateStorage::exists($path)) {
                throw new \RuntimeException('ファイルが見つかりませんでした。');
            }
            PrivateStorage::remove($path);
            $this->addMessage("{$fileName}を削除しました。");

            Logger::info('WXRエクスポートファイルを削除しました', [
                'fileName' => $fileName,
            ]);
        } catch (\Exception $e) {
            Logger::warning('WXRエクスポートファイルの削除に失敗しました', Common::exceptionArray($e));
            $this->addError($e->getMessage());
        }
        return $this->Post;
    }
}
