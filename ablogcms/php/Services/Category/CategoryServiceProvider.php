<?php

namespace Acms\Services\Category;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;

class CategoryServiceProvider extends ServiceProvider
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
        $container->singleton('category', 'Acms\Services\Category\Helper');
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
