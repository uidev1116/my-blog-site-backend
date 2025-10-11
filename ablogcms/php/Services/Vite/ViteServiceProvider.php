<?php

declare(strict_types=1);

namespace Acms\Services\Vite;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;

class ViteServiceProvider extends ServiceProvider
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
        $container->singleton('vite', Vite::class, [
            env('VITE_DEV_SERVER_URL'),
            env('VITE_MANIFEST_PATH'),
            env('VITE_ENVIRONMENT', 'production'),
        ]);
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
