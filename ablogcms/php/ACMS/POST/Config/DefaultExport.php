<?php

use Acms\Services\Facades\LocalStorage;

class ACMS_POST_Config_DefaultExport extends ACMS_POST_Config_Export
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
            $export = App::make('config.export');
            $export->exportDefaultConfig(BID);
            $this->yaml = $export->getYaml();
            $this->destPath = CACHE_DIR . 'config.yaml';

            LocalStorage::remove($this->destPath);
            $this->putYaml();
            $this->download();

            AcmsLogger::info('デフォルトコンフィグのエクスポートをしました');
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            LocalStorage::remove($this->destPath);

            AcmsLogger::info('デフォルトコンフィグのエクスポートに失敗しました', Common::exceptionArray($e));
        }

        return $this->Post;
    }
}
