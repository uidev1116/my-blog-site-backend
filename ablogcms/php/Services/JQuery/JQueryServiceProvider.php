<?php

namespace Acms\Services\JQuery;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;

class JQueryServiceProvider extends ServiceProvider
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
        $container->singleton('jquery', 'Acms\Services\JQuery\Helper');
    }
}
