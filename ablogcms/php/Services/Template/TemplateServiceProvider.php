<?php

namespace Acms\Services\Template;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Template\Acms\Engine as AcmsTemplateEngine;
use Acms\Services\Facades\Application;

class TemplateServiceProvider extends ServiceProvider
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
        $container->singleton('template.twig', 'Acms\Services\Template\Twig');
        $container->singleton('template.twig.data', 'Acms\Services\Template\Twig\GetModuleDataHolder');
        $container->singleton('template.acms.resolver', 'Acms\Services\Template\Acms\Resolver');
        $container->singleton('template.acms.engine', function () {
            $acmsResolver = Application::make('template.acms.resolver');
            return new AcmsTemplateEngine($acmsResolver);
        });
        $container->singleton('template.acms.cache', 'Acms\Services\Template\Acms\Cache');
        $container->bind('template.acms', function () {
            $acmsEngine = Application::make('template.acms.engine');
            $acmsResolver = Application::make('template.acms.resolver');
            $acmsCache = Application::make('template.acms.cache');
            return new Acms($acmsEngine, $acmsResolver, $acmsCache);
        });
        $container->singleton('template.acms.helper', 'Acms\Services\Template\Acms\Helper');
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
