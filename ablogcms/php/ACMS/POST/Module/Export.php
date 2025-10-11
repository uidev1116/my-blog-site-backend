<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Application as App;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;

class ACMS_POST_Module_Export extends ACMS_POST_Config_Export
{
    /**
     * run
     *
     * @inheritDoc
     */
    public function post()
    {
        @set_time_limit(0);

        if (!$this->checkAuth()) {
            return $this->Post;
        }
        try {
            $mid = (int)$this->Get->get('mid', 0);

            if (empty($mid)) {
                return $this->Post;
            }
            $this->export = App::make('config.export.module');
            assert($this->export instanceof \Acms\Services\Config\ModuleExport);
            $this->export->exportModule($mid);
            $this->yaml = $this->export->getYaml();
            $this->destPath = ARCHIVES_DIR . 'config.yaml';

            LocalStorage::remove($this->destPath);
            $this->putYaml();

            $module = loadModule($mid);
            Logger::info('「' . $module->get('label') . '（' . $module->get('identifier') . '）」モジュールをエクスポートしました');

            $this->download();
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            LocalStorage::remove($this->destPath);

            Logger::notice('モジュールのエクスポートに失敗しました。' . $e->getMessage(), Common::exceptionArray($e));
        }

        return $this->Post;
    }

    /**
     * @inheritDoc
     */
    protected function checkAuth()
    {
        return Module::canExport(BID);
    }
}
