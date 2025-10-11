<?php

namespace Acms\Services\Api;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;

class ApiServiceProvider extends ServiceProvider
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
        $container->bind('api-v1-get', 'Acms\Services\Api\EngineV1');
        $container->bind('api-v2-get', 'Acms\Services\Api\EngineV2');
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
