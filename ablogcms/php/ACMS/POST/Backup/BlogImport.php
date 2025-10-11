<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PrivateStorage;

/**
 * Class ACMS_POST_Backup_BlogImport
 */
class ACMS_POST_Backup_BlogImport extends ACMS_POST_Backup_Base
{
    /**
     * @var string $tmpDir
     */
    private $tmpDir;

    /**
     * run
     *
     * @inheritDoc
     */
    public function post()
    {
        @set_time_limit(0);
        DB::setThrowException(true);

        try {
            AcmsLogger::info('「' . ACMS_RAM::blogName(BID) . '」ブログのインポートを実行しました');

            $this->authCheck('backup_import');
            $this->tmpDir = MEDIA_STORAGE_DIR . 'blog_data/';
            $import = App::make('blog.import');

            $this->decompress();
            if (Common::isLocalPrivateStorage()) {
                $this->deleteArchives();
                $this->copyArchives();
            }

            $yaml = $this->getYaml();
            $yaml = $this->fixYaml($yaml);
            $errors = $import->run(BID, $yaml);

            if (empty($errors)) {
                Cache::flush('template');
                Cache::flush('config');
                Cache::flush('field');
                Cache::flush('temp');

                $this->Post->set('import', 'success');
            }
            foreach ($errors as $error) {
                $this->addError($error);
            }
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            if (Common::isLocalPrivateStorage()) {
                $this->deleteArchives();
            }
        }
        DB::setThrowException(false);

        LocalStorage::removeDirectory($this->tmpDir);

        return $this->Post;
    }

    /**
     * get yaml data
     *
     * @return string
     */
    private function getYaml()
    {
        $yamlPath = $this->tmpDir . 'acms_blog_data/data.yaml';
        try {
            return LocalStorage::get($yamlPath, dirname($yamlPath));
        } catch (\Exception $e) {
            $yamlPath = $this->tmpDir . 'data.yaml';
            return LocalStorage::get($yamlPath, dirname($yamlPath));
        }
        throw new \RuntimeException('File does not exist.');
    }

    /**
     * fix blog id
     *
     * @param string $yaml
     *
     * @return string
     */
    private function fixYaml($yaml)
    {
        return preg_replace('@([\d]{3})/(.*)\.([^\.]{2,6})@ui', sprintf("%03d", BID) . '/$2.$3', $yaml, -1);
    }

    /**
     * decompress zip
     *
     * @return bool
     */
    private function decompress()
    {
        $file = $this->Post->get('zipfile', false);
        if (!$file) {
            return false;
        }
        $path = LocalStorage::validateDirectoryTraversal($this->backupBlogDir, $file);
        if (!PrivateStorage::isFile($path)) {
            return false;
        }
        if (!Common::isLocalPrivateStorage()) {
            LocalStorage::makeDirectory($this->backupBlogDir);
            if ($content = PrivateStorage::get($this->backupBlogDir . $file)) {
                LocalStorage::put($this->backupBlogDir . $file, $content);
            }
        }
        LocalStorage::makeDirectory($this->tmpDir);
        LocalStorage::unzip($this->backupBlogDir . $file, $this->tmpDir);

        return true;
    }

    /**
     * delete archives
     *
     * @return void
     */
    private function deleteArchives()
    {
        foreach ([ARCHIVES_DIR, MEDIA_LIBRARY_DIR, MEDIA_STORAGE_DIR] as $baseDir) {
            $target = SCRIPT_DIR . $baseDir . sprintf("%03d", BID) . '/';
            if (LocalStorage::isDirectory($target)) {
                LocalStorage::removeDirectory($target);
            }
        }
    }

    /**
     * copy archives directory
     *
     * @return void
     */
    private function copyArchives()
    {
        $list = [
            'archives/' => ARCHIVES_DIR,
            'media/' => MEDIA_LIBRARY_DIR,
            'storage/' => MEDIA_STORAGE_DIR,
        ];
        foreach ($list as $from => $to) {
            $exists = false;
            $from = $this->tmpDir . 'acms_blog_data/' . $from . '001/';
            if (LocalStorage::exists($from)) {
                $exists = true;
            } else {
                $from = $this->tmpDir . $from . '001/';
                if (LocalStorage::exists($from)) {
                    $exists = true;
                }
            }
            if ($exists) {
                $to = SCRIPT_DIR . $to . sprintf("%03d", BID) . '/';
                LocalStorage::copyDirectory($from, $to);
            }
        }
    }
}
