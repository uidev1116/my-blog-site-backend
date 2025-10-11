<?php

use Acms\Services\Facades\LocalStorage;

class ACMS_POST_Config_Export extends ACMS_POST
{
    /**
     * @var string $yaml
     */
    protected $yaml;

    /**
     * @var string $destPath
     */
    protected $destPath;

    /**
     * @var \Acms\Services\Config\Export $export
     */
    protected $export;

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
            $this->export = App::make('config.export');
            $this->export->exportAll(BID);
            $this->yaml = $this->export->getYaml();
            $this->destPath = CACHE_DIR . 'config.yaml';

            LocalStorage::remove($this->destPath);
            $this->putYaml();
            $this->download();

            AcmsLogger::info('コンフィグをエクスポートしました', [
                'path' => $this->destPath,
            ]);
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
            LocalStorage::remove($this->destPath);

            AcmsLogger::info('コンフィグのエクスポートに失敗しました', Common::exceptionArray($e));
        }

        return $this->Post;
    }

    /**
     * download yaml data
     *
     * @return void
     */
    protected function download()
    {
        if (!LocalStorage::exists($this->destPath)) {
            throw new RuntimeException('Can not read a yaml to a file.');
        }
        $file = 'config' . date('_Ymd_Hi') . '.yaml';
        Common::download($this->destPath, $file, false, true);
    }

    /**
     * write a yaml to a file
     *
     * @return void
     *
     * @throws RuntimeException
     */
    protected function putYaml()
    {
        if (!LocalStorage::put($this->destPath, $this->yaml)) {
            throw new RuntimeException('Can not write a yaml to a file.');
        }
    }

    /**
     * check auth
     *
     * @return bool
     */
    protected function checkAuth()
    {
        return sessionWithAdministration();
    }
}
