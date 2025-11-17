<?php

namespace Acms\Services\Update;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Common\Lock;

class UpdateServiceProvider extends ServiceProvider
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
        $cache = SCRIPT_DIR . CACHE_DIR . 'update.json';
        $schema = LIB_DIR . 'Services/Update/template/schema.json';

        $container->singleton('update.exec.update', \Acms\Services\Update\Operations\Update::class);
        $container->singleton('update.exec.downgrade', \Acms\Services\Update\Operations\Downgrade::class);
        $container->singleton('update.lock', function () {
            return new Lock(SCRIPT_DIR . CACHE_DIR . 'system-update-lock');
        });
        $container->singleton('update.logger', \Acms\Services\Update\LoggerFactory::class);
        $container->singleton('update.check', function () use ($cache, $schema) {
            return new System\CheckForUpdate(config('system_update_repository'), $cache, $schema);
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
