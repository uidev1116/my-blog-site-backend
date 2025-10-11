<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\LocalStorage;

class ACMS_POST_Logger_ProgressJson extends ACMS_POST
{
    public function post()
    {
        $type = $this->Post->get('type');
        $logger = null;
        $output = [
            'message' => 'No log found',
            'status' => 'notfound',
        ];

        if ($type === 'backup_db') {
            $logger = Application::make('db.logger');
        } elseif ($type === 'backup_archives') {
            $logger = Application::make('archives.logger');
        } elseif ($type === 'export_wxr') {
            $logger = Application::make('common.logger');
            $logger->setDestinationPath(CACHE_DIR . 'wxr-export-logger.json');
        } elseif ($type === 'import_csv') {
            $logger = Application::make('common.logger');
            $logger->setDestinationPath(CACHE_DIR . 'csv-import-logger.json');
        } elseif ($type === 'import_user') {
            $logger = Application::make('common.logger');
            $logger->setDestinationPath(CACHE_DIR . 'user-csv-import-logger.json');
        } elseif ($type === 'update') {
            /** @var \Acms\Services\Update\LoggerFactory $loggerFactory */
            $loggerFactory = Application::make('update.logger');
            $logger = $loggerFactory->createLogger('web');
        } elseif ($type === 'publish') {
            try {
                $bid = $this->Post->get('bid');
                $path = CACHE_DIR . "{$bid}_publish.json";
                if ($data = LocalStorage::get($path)) {
                    Common::setSafeHeadersWithoutCache(200, 'application/json');
                    echo($data);
                    die();
                }
            } catch (Exception $e) {
            }
        }
        if ($logger) {
            $output = null;
            $json = json_encode($logger->getJson());
            if ($json !== false) {
                $output = json_decode($json, true);
            }
            if (!is_array($output)) {
                $output = [
                    'message' => 'No log found',
                    'status' => 'notfound',
                ];
            }
        }
        Common::responseJson($output);
    }
}
