<?php

use Acms\Services\Facades\PrivateStorage;

class ACMS_POST_Backup_Remove extends ACMS_POST_Backup_Base
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
            PrivateStorage::remove($this->getPath($type, $fileName));

            $this->addMessage($fileName . ' を削除しました。');

            AcmsLogger::info('バックアップの削除を行いました', [
                'type' => $type,
                'fileName' => $fileName,
            ]);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            AcmsLogger::warning('バックアップの削除に失敗しました', Common::exceptionArray($e));
        }
        return $this->Post;
    }
}
