<?php

use Acms\Services\Facades\LocalStorage;

class ACMS_POST_Backup_ArchiveImport extends ACMS_POST_Backup_Import
{
    public function post()
    {
        try {
            if (env('STORAGE_DRIVER', 'local') !== 'local') {
                throw new \RuntimeException(gettext('ストレージ設定がローカルではない（S3など）ため、リストアできません。'));
            }
            AcmsLogger::info('アーカイブのインポートを実行しました');

            $this->authCheck('backup_import');

            ignore_user_abort(true);
            set_time_limit(0);

            $file_name = $this->Post->get('zipfile', false);
            if (empty($file_name)) {
                throw new \RuntimeException(gettext('バックアップファイルが指定されていません。'));
            }
            $path = Storage::validateDirectoryTraversal($this->backupArchivesDir, $file_name);
            Common::backgroundRedirect(acmsLink(['bid' => RBID]));
            $this->run($path);
            die();
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            AcmsLogger::warning('アーカイブのインポート中にエラーが発生しました。', Common::exceptionArray($e));
        }
        return $this->Post;
    }

    /**
     * @param $path
     * @throws Exception
     */
    protected function run($path)
    {
        $archive_dir = ARCHIVES_DIR;
        $media_dir = MEDIA_LIBRARY_DIR;
        $storage_dir = MEDIA_STORAGE_DIR;

        if (LocalStorage::isFile($path)) {
            LocalStorage::removeDirectory($storage_dir . 'archives_tmp');
            LocalStorage::unzip($path, $storage_dir);

            $this->renameAllFile($storage_dir . 'archives_tmp/archives/', $archive_dir);
            $this->renameAllFile($storage_dir . 'archives_tmp/media/', $media_dir);
            $this->renameAllFile($storage_dir . 'archives_tmp/storage/', $storage_dir);

            if (LocalStorage::isDirectory($storage_dir . 'archives_tmp/')) {
                LocalStorage::removeDirectory($storage_dir . 'archives_tmp/');
            }
        }
        $field = new Field();
        $field->set('backupFileName', $this->Post->get('zipfile'));
        $this->notify($field);
    }

    /**
     * @param $dir
     * @param $new_dir
     */
    protected function renameAllFile($dir, $new_dir)
    {
        if (LocalStorage::isDirectory($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (filetype($dir . $file) === 'dir') {
                        if ($file === '.' || $file === '..') {
                        } else {
                            $this->renameAllFile($dir . $file . '/', $new_dir . $file . '/');
                        }
                    } else {
                        LocalStorage::makeDirectory($new_dir);
                        LocalStorage::move($dir . $file, $new_dir . $file);
                    }
                }
                closedir($dh);
            }
        }
    }
}
