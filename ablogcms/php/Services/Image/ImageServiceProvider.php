<?php

namespace Acms\Services\Image;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;

class ImageServiceProvider extends ServiceProvider
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
        $container->singleton('image', 'Acms\Services\Image\Helper');
        $container->singleton('image.engine.gd', 'Acms\Services\Image\Engine\GdEngine');
        $container->singleton('image.engine.imagick', 'Acms\Services\Image\Engine\ImagickEngine');
        $container->singleton('image.engine', 'Acms\Services\Image\Factory');
        $container->singleton('image.optimizer', 'Acms\Services\Image\ImagerOptimizer');
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
