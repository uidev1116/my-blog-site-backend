<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Application as App;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Common;

class ACMS_POST_Module_Import extends ACMS_POST
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
        try {
            $import = App::make('config.import.module');
            assert($import instanceof \Acms\Services\Config\ModuleImport);
            $path = $_FILES['file']['tmp_name'];
            if (is_uploaded_file($path) === false) {
                throw new \RuntimeException('無効なファイルです。');
            }
            $yaml = Config::yamlLoad($_FILES['file']['tmp_name']);

            $import->run(BID, $yaml);
            $this->Post->set('notice', $import->getFailedContents());
            $this->Post->set('import', 'success');

            Logger::info('モジュールをインポートしました', [
                'data' => $yaml,
            ]);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());

            Logger::notice('モジュールのインポートに失敗しました。' . $e->getMessage(), Common::exceptionArray($e));
        }
        return $this->Post;
    }

    /**
     * check auth
     *
     * @return bool
     */
    private function checkAuth()
    {
        if (!Module::canImport(BID)) {
            return false;
        }
        if (empty($_FILES['file']['tmp_name'])) {
            $this->addError('No file was uploaded.');
            return false;
        }
        if (is_uploaded_file($_FILES['file']['tmp_name']) === false) {
            $this->addError('Invalid file.');
            return false;
        }
        return true;
    }
}
