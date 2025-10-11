<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Application as App;

class ACMS_POST_Module_Index_Export extends ACMS_POST_Config_Export
{
    /**
     * run
     *
     * @inheritDoc
     */
    public function post()
    {
        @set_time_limit(0);

        $this->Post->setMethod('module', 'operative', Module::canBulkExport(BID));

        $this->Post->setMethod('checks', 'required');
        $this->Post->validate(new ACMS_Validator());

        if (!$this->Post->isValidAll()) {
            Logger::info('選択したモジュールIDのエクスポートに失敗しました');
            return $this->Post;
        }

        try {
            $this->export = App::make('config.export.module');
            assert($this->export instanceof \Acms\Services\Config\ModuleExport);
            $targetModules = [];

            foreach ($this->Post->getArray('checks') as $mid) {
                $id = preg_split('@:@', $mid, 2, PREG_SPLIT_NO_EMPTY);
                $bid = intval($id[0]);
                $mid = intval($id[1]);
                if ($bid < 1) {
                    continue;
                }
                if ($mid < 1) {
                    continue;
                }
                $module = loadModule($mid);
                $moduleBlogId = (int)$module->get('blog_id');
                if (!Module::canExport($moduleBlogId)) {
                    continue;
                }
                $this->export->exportModule($mid);
                $targetModules[] = $module->get('label') . '（' . $module->get('identifier') . '）';
            }
            $this->yaml = $this->export->getYaml();
            $this->destPath = CACHE_DIR . 'config.yaml';

            LocalStorage::remove($this->destPath);
            $this->putYaml();

            Logger::info('選択したモジュールIDをエクスポートしました', [
                'targetModules' => $targetModules,
            ]);

            $this->download();
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            LocalStorage::remove($this->destPath);

            Logger::notice('選択したモジュールIDのエクスポートに失敗しました', [
                'message' => $e->getMessage(),
                'targetModules' => $targetModules,
            ]);
        }

        return $this->Post;
    }
}
