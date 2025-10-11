<?php

use Acms\Services\Facades\PrivateStorage;

class ACMS_POST_Backup_Base extends ACMS_POST
{
    /**
     * データベースバックアップの保存先
     *
     * @var string
     */
    protected $backupDatabaseDir;

    /**
     * アーカイブバックアップの保存先（archives, media, storage）
     *
     * @var string
     */
    protected $backupArchivesDir;

    /**
     * ブログのバックアップの保存先
     *
     * @var string
     */
    protected $backupBlogDir;

    /**
     * ACMS_POST_Backup_Base constructor.
     */
    public function __construct()
    {
        $this->backupDatabaseDir = MEDIA_STORAGE_DIR . 'backup_database/';
        $this->backupArchivesDir = MEDIA_STORAGE_DIR . 'backup_archives/';
        $this->backupBlogDir = MEDIA_STORAGE_DIR . 'backup_blog/';
    }

    /**
     * Validate
     *
     * @param string $role backup_export | backup_import
     */
    protected function authCheck($role)
    {
        if (roleAvailableUser()) {
            if (!roleAuthorization($role, BID)) {
                throw new \RuntimeException('Permission denied.');
            }
        } else {
            if (!sessionWithAdministration()) {
                throw new \RuntimeException('Permission denied.');
            }
        }
    }

    /**
     * @param $type
     * @param $fileName
     * @return string
     */
    protected function getPath($type, $fileName)
    {
        $baseDir = $type === 'database' ? $this->backupDatabaseDir : $this->backupArchivesDir;
        $path = Storage::validateDirectoryTraversal($baseDir, $fileName);
        if (!PrivateStorage::exists($path)) {
            throw new \RuntimeException('ファイルが存在しません。');
        }
        return $path;
    }
}
