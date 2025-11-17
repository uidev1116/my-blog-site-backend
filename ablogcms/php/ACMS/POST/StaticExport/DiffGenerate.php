<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;

class ACMS_POST_StaticExport_DiffGenerate extends ACMS_POST_StaticExport_Generate
{
    /**
     * @var \Acms\Services\StaticExport\Compiler
     */
    protected $compiler;

    /**
     * @var \Acms\Services\StaticExport\Destination
     */
    protected $destination;

    /**
     * @var int
     */
    protected $maxPublish;

    /**
     * @var \Acms\Services\StaticExport\Logger
     */
    protected $logger;

    /**
     * @var \Acms\Services\StaticExport\TerminateCheck
     */
    protected $terminateFlag;

    /**
     * Run
     */
    public function post()
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $this->validate($this->Post);

        if ($this->Post->isValidAll() === false) {
            return $this->Post;
        }

        $lockService = Application::make('static-export.lock');
        if ($lockService->isLocked()) {
            $this->addError('静的書き出しを中止しました。すでに書き出し中の可能性があります。');
            return $this->Post;
        }

        ignore_user_abort(true);
        set_time_limit(0);

        AcmsLogger::info('差分静的書き出しを開始しました', [
            'bid' => BID,
        ]);

        Common::backgroundRedirect(HTTP_REQUEST_URL);
        $this->run($lockService);
        die();
    }

    protected function run(\Acms\Services\Common\Lock $lockService)
    {
        $setting = Config::loadDefaultField();
        $setting->overload(Config::loadBlogConfig(BID));

        $document_root = $setting->get('static_dest_document_root');
        $offset_dir = $setting->get('static_dest_offset_dir');
        $domain = $setting->get('static_dest_domain');
        $destDiffDir = $setting->get('static_dest_diff');
        $maxPublish = intval($setting->get('static_max_publish', 3));
        $nameServer = config('static_export_name_server', '8.8.8.8');
        $blogCode = ACMS_RAM::blogCode(BID);
        $config = new stdClass();
        $config->theme = config('theme');
        $config->static_export_name_server = config('static_export_name_server', '8.8.8.8');
        $config->static_page_cid = array_map('intval', $setting->getArray('static_page_cid'));
        $config->static_archive_cid = array_map('intval', $setting->getArray('static_archive_cid'));
        $config->static_page_max = array_map('intval', $setting->getArray('static_page_max'));
        $config->static_archive_start = $setting->getArray('static_archive_start');
        $config->static_archive_max = array_map('intval', $setting->getArray('static_archive_max'));
        $exclusionList = [];
        $includeList = [];
        if ($list = $setting->get('static_export_exclusion_list', false)) {
            $exclusionList = $this->createRegexPathList(preg_split('/(\n|\r|\r\n|\n\r)/', $list));
        }
        if ($list = $setting->get('static_export_include_list', false)) {
            $includeList = $this->createRegexPathList(preg_split('/(\n|\r|\r\n|\n\r)/', $list));
        }
        $config->exclusion_list = $exclusionList;
        $config->include_list = $includeList;

        // 書き出し時間を保存するめに現在時刻を取得
        $exportDate = date('Y-m-d', REQUEST_TIME);
        $exportTime = date('H:i:s', REQUEST_TIME);

        set_time_limit(0);
        DB::setThrowException(true);
        $logger = null;
        try {
            $lockService->tryLock();

            $logger = App::make('static-export.logger');
            $engine = App::make('static-export.diff-engine');
            $destination = App::make('static-export.destination');
            $fromDatetime = $this->Post->get('diff_date') . ' ' . $this->Post->get('diff_time');

            if (
                0
                || empty($document_root)
                || empty($domain)
                || empty($maxPublish)
            ) {
                throw new \RuntimeException('Configuration is incorrect.');
            }

            $destination->setDestinationDocumentRoot($document_root);
            $destination->setDestinationOffsetDir($offset_dir);
            $destination->setDestinationDiffDir($destDiffDir);
            $destination->setDestinationDomain($domain);
            $destination->setBlogCode($blogCode);
            $logger->initLog();
            $engine->init($logger, $destination, $maxPublish, $nameServer, $config);
            $engine->runDiff($fromDatetime);
            $this->saveExportDatetime($exportDate, $exportTime);

            AcmsLogger::info('部分静的書き出しを完了しました', [
                'bid' => BID,
            ]);

            App::checkException();
        } catch (\Exception $e) {
            $logger->error($e->getMessage());
            AcmsLogger::warning($e->getMessage(), Common::exceptionArray($e));
        } finally {
            $lockService->release();
        }
        DB::setThrowException(false);
    }

    protected function saveExportDatetime($date, $time)
    {
        $field = new Field();
        $field->set('static-export-last-time-date', $date);
        $field->set('static-export-last-time-time', $time);

        Config::saveConfig($field, BID);
    }

    /**
     * @param \Field_Validation $post
     * @return void
     */
    protected function validate(\Field_Validation $post): void
    {
        $post->setMethod('diff_date', 'required');
        $post->setMethod('diff_date', 'dates');
        $post->setMethod('diff_time', 'required');
        $post->setMethod('diff_time', 'times');
        $post->validate(new ACMS_Validator());
    }
}
