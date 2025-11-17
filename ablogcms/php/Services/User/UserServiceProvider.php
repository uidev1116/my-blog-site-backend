<?php

namespace Acms\Services\User;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Common\Lock;

class UserServiceProvider extends ServiceProvider
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
        $container->singleton('user', 'Acms\Services\User\Helper');
        $container->singleton('user.import.csv-lock', function () {
            return new Lock(CACHE_DIR . 'user-import-csv-lock');
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
