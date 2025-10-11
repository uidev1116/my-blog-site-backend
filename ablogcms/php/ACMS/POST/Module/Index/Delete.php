<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Logger;

class ACMS_POST_Module_Index_Delete extends ACMS_POST_Module_Delete
{
    function post()
    {
        $this->Post->reset(true);
        $this->Post->setMethod('checks', 'required');

        $this->Post->setMethod('module', 'operative', Module::canBulkDelete(BID));
        $this->Post->validate(new ACMS_Validator());

        $targetModules = [];

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);
            foreach ($this->Post->getArray('checks') as $mid) {
                $id = preg_split('@:@', $mid, 2, PREG_SPLIT_NO_EMPTY);
                $mid = $id[1];
                if (!($mid = intval($mid))) {
                    continue;
                }
                $module = loadModule($mid);
                $moduleBlogId = (int)$module->get('blog_id');
                if (!Module::canDelete($moduleBlogId)) {
                    // モジュールの削除が許可されていない
                    continue;
                }
                $this->delete($mid);

                $targetModules[] = $module->get('label') . '（' . $module->get('identifier') . '）';
            }

            Logger::info('選択したモジュールIDを削除しました', [
                'targetModules' => $targetModules,
            ]);
        } else {
            Logger::info('選択したモジュールIDの削除に失敗しました');
        }

        return $this->Post;
    }
}
