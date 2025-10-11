<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;

class ACMS_POST_Config_Import extends ACMS_POST
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
            $import = Application::make('config.import');
            $path = $_FILES['file']['tmp_name'];
            if (is_uploaded_file($path) === false) {
                throw new \RuntimeException('無効なファイルです。');
            }
            $yaml = Config::yamlLoad($path);

            $import->run(BID, $yaml);
            $this->Post->set('notice', $import->getFailedContents());
            $this->Post->set('import', 'success');

            Logger::info('コンフィグのインポートを実行しました');
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            Logger::info('コンフィグのインポートが失敗しました', Common::exceptionArray($e));
        }

        return $this->Post;
    }

    /**
     * check auth
     *
     * @return bool
     */
    protected function checkAuth()
    {
        if (!sessionWithAdministration()) {
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
