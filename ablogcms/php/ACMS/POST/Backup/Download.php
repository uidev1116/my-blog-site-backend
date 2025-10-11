<?php

use Acms\Services\Facades\PrivateStorage;

class ACMS_POST_Backup_Download extends ACMS_POST_Backup_Base
{
    /**
     * @inheritDoc
     */
    public function post()
    {
        try {
            set_time_limit(0);
            $this->authCheck('backup_export');

            $fileName = $this->Post->get('backup_file');
            $type = $this->Post->get('backup_type');

            if (empty($fileName)) {
                throw new \RuntimeException('File name empty.');
            }
            if (!in_array($type, ['database', 'archives'], true)) {
                throw new \RuntimeException('Wrong type.');
            }

            AcmsLogger::info('バックアップファイルをダウンロードしました', [
                'fileName' => $fileName,
                'type' => $type,
            ]);

            Common::download($this->getPath($type, $fileName), $fileName, false, false, PrivateStorage::getInstance());
        } catch (\Exception $e) {
            AcmsLogger::warning('バックアップファイルのダウンロードに失敗しました', Common::exceptionArray($e));
            $this->addError($e->getMessage());
        }
        return $this->Post;
    }
}
