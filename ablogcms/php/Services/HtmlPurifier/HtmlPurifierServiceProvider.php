<?php

namespace Acms\Services\HtmlPurifier;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;

class HtmlPurifierServiceProvider extends ServiceProvider
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
        $container->singleton('html-purifier', 'Acms\Services\HtmlPurifier\Helper');
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
