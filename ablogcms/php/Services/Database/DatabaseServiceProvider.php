<?php

namespace Acms\Services\Database;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Database\Engine;
use Acms\Services\Common\Lock;

class DatabaseServiceProvider extends ServiceProvider
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
        $container->singleton('db', function () {
            return Engine\PdoEngine::singleton(dsn());
        });
        $container->bind('db.replication', 'Acms\Services\Database\Replication');
        $container->bind('db.logger', function () {
            return new Logger(CACHE_DIR . 'db-export-process.json');
        });
        $container->singleton('db.backup-lock', function () {
            return new Lock(CACHE_DIR . 'system-backup-lock');
        });
        $container->bind('archives.logger', function () {
            return new Logger(CACHE_DIR . 'archives-export-process.json');
        });
        $container->singleton('archive.backup-lock', function () {
            return new Lock(CACHE_DIR . 'archive-backup-lock');
        });
    }

    /**
     * initialize service
     *
     * @return void
     */
    public function init()
    {
    }
}
