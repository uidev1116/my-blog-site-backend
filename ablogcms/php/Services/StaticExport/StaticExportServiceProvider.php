<?php

namespace Acms\Services\StaticExport;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Common\Lock;

class StaticExportServiceProvider extends ServiceProvider
{
    /**
     * register service
     *
     * @param \Acms\Services\Container $container
     *
     * @return void
     */
    public function register(Container $container)
    {
        $logger_path = CACHE_DIR . 'BID_publish.json';
        $terminate_check_path = CACHE_DIR . 'BID_publish_terminate';

        $container->singleton('static-export.compiler', 'Acms\Services\StaticExport\Compiler');
        $container->singleton('static-export.destination', 'Acms\Services\StaticExport\Destination');
        $container->singleton('static-export.engine', 'Acms\Services\StaticExport\Engine');
        $container->singleton('static-export.diff-engine', 'Acms\Services\StaticExport\DiffEngine');
        $container->singleton('static-export.terminate-check', function () use ($logger_path, $terminate_check_path) {
            $logger_path = str_replace('BID', BID, $logger_path);
            $terminate_check_path = str_replace('BID', BID, $terminate_check_path);
            return new TerminateCheck($logger_path, $terminate_check_path);
        });
        $container->singleton('static-export.logger', function () use ($container, $logger_path) {
            $logger = new Logger();
            $logger_path = str_replace('BID', BID, $logger_path);
            $excludeLogStatusCodes = configArray('static_export_exclude_log_status_codes', false);
            $logger->init($logger_path, $container->make('static-export.terminate-check'), $excludeLogStatusCodes);
            return $logger;
        });
        $container->singleton('static-export.lock', function () {
            return new Lock(CACHE_DIR . 'static-export-lock');
        });
    }
}
