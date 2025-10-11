<?php

use Acms\Services\Facades\PrivateStorage;

class ACMS_GET_Admin_Backup_ArchiveZipList extends ACMS_GET
{
    public function get()
    {
        if (roleAvailableUser()) {
            if (!roleAuthorization('backup_export', BID)) {
                die403();
            }
        } else {
            if (!sessionWithAdministration()) {
                die403();
            }
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        $zip_list = [];
        $sql_list = [];
        $import_list = [];
        $wxr_list = [];

        $archivesBackupDir = MEDIA_STORAGE_DIR . 'backup_archives/';
        $dbBackupDir = MEDIA_STORAGE_DIR . 'backup_database/';
        $blogBackupDir = MEDIA_STORAGE_DIR . 'backup_blog/';
        $exportWxrDir = MEDIA_STORAGE_DIR . 'export_wxr/';
        $this->createList($archivesBackupDir, $zip_list);
        $this->createList($dbBackupDir, $sql_list);
        $this->createList($blogBackupDir, $import_list);
        $this->createList($exportWxrDir, $wxr_list);

        if (empty($zip_list)) {
            $Tpl->add('notFoundZip');
        } else {
            foreach ($zip_list as $file) {
                $Tpl->add('zip:loop', [
                    'zipfile' => $file,
                ]);
            }
            $Tpl->add('foundZip');
        }

        if (empty($sql_list)) {
            $Tpl->add('notFoundSql');
        } else {
            foreach ($sql_list as $file) {
                $Tpl->add('sql:loop', [
                    'sqlfile' => $file,
                ]);
            }
            $Tpl->add('foundSql');
        }

        if (empty($import_list)) {
            $Tpl->add('notFoundExport');
        } else {
            foreach ($import_list as $file) {
                $Tpl->add('export:loop', [
                    'zip' => $file,
                ]);
            }
            $Tpl->add('foundExport');
        }

        if (empty($wxr_list)) {
            $Tpl->add('notFoundWxr');
        } else {
            foreach ($wxr_list as $file) {
                $Tpl->add('wxr:loop', [
                    'xml' => $file,
                ]);
            }
            $Tpl->add('foundWxr');
        }

        return $Tpl->get();
    }

    private function createList($target, &$list)
    {
        $time_list = []; //ファイルの日付を保存する配列
        if (PrivateStorage::isDirectory($target)) {
            $fileList = PrivateStorage::getFileList($target);
            foreach ($fileList as $path) {
                $basename = PrivateStorage::mbBasename($path);
                if (str_starts_with($basename, '.') || str_starts_with($basename, '..')) {
                    continue;
                }
                $list[] = $basename;
                $time_list[] = PrivateStorage::lastModified($path);
            }
        }
        array_multisort($time_list, SORT_DESC, $list); //時刻でソート
    }
}
