<?php

use Acms\Services\Facades\Config;
use Acms\Services\Facades\Common;

class ACMS_POST_Config_PartImport extends ACMS_POST_Config_Import
{
    /**
     * @inheritDoc
     */
    public function post()
    {
        @set_time_limit(0);

        if (!$this->checkAuth()) {
            return $this->Post;
        }
        $rid = $this->Get->get('rid') ?: null;
        $setid = $this->Get->get('setid') ?: null;
        try {
            $path = $_FILES['file']['tmp_name'];
            if (is_uploaded_file($path) === false) {
                throw new \RuntimeException('無効なファイルです。');
            }
            $yaml = Config::yamlLoad($path);
            $config = new Field();
            foreach ($yaml as $key => $val) {
                $config->addField($key, $val);
            }
            $config = Config::fix($config);
            Config::saveConfig($config, BID, $rid, null, $setid);

            $this->Post->set('import', 'success');

            AcmsLogger::info('コンフィグの部分インポートをしました');
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            AcmsLogger::info('コンフィグの部分エクスポートが失敗しました', Common::exceptionArray($e));
        }

        return $this->Post;
    }
}
