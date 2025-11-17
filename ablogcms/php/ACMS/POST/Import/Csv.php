<?php

use Acms\Services\Facades\Application;

class ACMS_POST_Import_Csv extends ACMS_POST_Import
{
    protected $importCid;

    /**
     * @var array
     */
    protected $csvLabels;

    /**
     * @var int
     */
    protected $errorCount = 0;

    function post()
    {
        @set_time_limit(0);
        if (!sessionWithCompilation()) {
            return $this->Post;
        }

        $this->locale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'ja_JP.UTF-8');

        $this->init();
        $this->unitType = $this->Post->get('unit_type');
        $lockService = Application::make('entry.import.csv-lock');

        try {
            $this->httpFile = ACMS_Http::file($this->uploadFiledName);
            if ($lockService->isLocked()) {
                throw new \RuntimeException('CSVインポートを中止しました。すでにインポート中の可能性があります。変化がない場合は、cache/csv-import-lock ファイルを削除してお試しください。');
            }
            Common::backgroundRedirect(HTTP_REQUEST_URL);
            $this->run($lockService);
            die();
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            AcmsLogger::warning($e->getMessage(), Common::exceptionArray($e));
        }
        return $this->Post;
    }

    function init()
    {
        ignore_user_abort(true);
        set_time_limit(0);

        $this->uploadFiledName = 'csv_import_file';
        $this->importCid = intval($this->Post->get('category_id'));

        if ($this->importCid == 0) {
            $this->importCid = null;
        }
    }

    function run(\Acms\Services\Common\Lock $lockService)
    {
        set_time_limit(0);
        DB::setThrowException(true);

        $logger = App::make('common.logger');

        try {
            $lockService->tryLock();
            $logger->setDestinationPath(CACHE_DIR . 'csv-import-logger.json');
            $logger->init();
            $logger->addMessage('CSV読み込み中...', 10);
            sleep(6);
            $csv = $this->httpFile->getCsv();
            $this->csvLabels = $csv->fgetcsv();
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            $lockService->release();
            sleep(5);
            $logger->terminate();

            AcmsLogger::warning('CSVインポートでエラーが発生しました', Common::exceptionArray($e, ['message' => $e->getMessage()]));
            return;
        }

        $count = $this->getNumberOfCsvRows($csv);
        $increase = 90 / $count;
        if ($this->importCid) {
            $logger->addMessage('「' . ACMS_RAM::categoryName($this->importCid) . '」にインポート中...', 0);
        } else {
            $logger->addMessage('インポート中...', 0);
        }

        foreach ($csv as $i => $line) {
            if ($i === 0) {
                continue; // header行を飛ばす
            }
            $logger->addMessage("$i / $count", $increase, 1, false);
            try {
                $this->save($line);
                $this->entryCount++;
            } catch (ACMS_POST_Import_CsvException $e) {
                $logger->error($e->getMessage());
                break;
            } catch (Exception $e) {
                $logger->addProcessLog('CSV' . ($i + 1) . '行目: ' . $e->getMessage(), 0);
                $this->errorCount++;

                AcmsLogger::notice('CSVインポートの' . ($i + 1) . '行目がエラーのため、この行は読み込みません', Common::exceptionArray($e, ['message' => $e->getMessage()]));
            }
        }
        sleep(3);
        DB::setThrowException(false);

        $logger->addMessage('インポート完了', 100);
        $logger->addProcessLog('インポート成功件数: ' . $this->entryCount . '件');
        $logger->addProcessLog('インポート失敗件数: ' . $this->errorCount . '件');
        $logger->success();

        AcmsLogger::info('CSVインポートを実行しました', [
            'success' => $this->entryCount,
            'error' => $this->errorCount,
        ]);

        $lockService->release();
        sleep(5);
        $logger->terminate();

        Cache::flush('page');
        Cache::flush('field');
        Cache::flush('temp');
    }

    function save($line = false)
    {
        if (is_array($line)) {
            foreach ($line as & $value) {
                $value = preg_replace('/^str-data\_/', '', $value);
            }
        }
        $entry = new ACMS_POST_Import_Model_Entry($line, $this->csvLabels);
        $entry->setTargetCid($this->importCid);

        $entry->saveEntry();
    }

    function getNumberOfCsvRows($csv)
    {
        $count = 0;
        foreach ($csv as $i => $line) {
            if ($i === 0) {
                continue; // header行を飛ばす
            }
            $count++;
        }
        return $count;
    }
}
