<?php

use Symfony\Component\Finder\Finder;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;

class ACMS_POST_Update_RemoveBackup extends ACMS_POST_Update_Base
{
    public function post()
    {
        if (!$this->validatePermissions()) {
            $this->addError(gettext('権限がありません。'));
            return $this->Post;
        }

        $finder = new Finder();
        $lists = [];
        $iterator = $finder
            ->in('private')
            ->depth('< 2')
            ->name('/^backup.+/')
            ->directories();

        foreach ($iterator as $dir) {
            $lists[] = $dir->getRelativePathname();
        }
        foreach ($lists as $item) {
            try {
                LocalStorage::removeDirectory('private/' . $item);
            } catch (\Exception $e) {
                Logger::warning($e->getMessage(), Common::exceptionArray($e));
            }
        }
        $this->addMessage(gettext('バックアップを削除しました。'));
        if (!empty($lists)) {
            Logger::info('システム更新時に取られたバックアップを削除しました', $lists);
        }
        return $this->Post;
    }
}
