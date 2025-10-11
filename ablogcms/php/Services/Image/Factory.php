<?php

namespace Acms\Services\Image;

use Acms\Contracts\Factory as BaseFactory;
use Acms\Services\Facades\Application;

class Factory extends BaseFactory
{
    /**
     * Factory
     *
     * @return mixed
     */
    public function createInstance()
    {
        /** @var \Acms\Services\Image\Contracts\ImageEngine */
        $engine = (class_exists('Imagick') && config('image_magick') === 'on') ?
            Application::make('image.engine.imagick') : Application::make('image.engine.gd');
        $engine->setImageQuality((int) config('image_jpeg_quality', 75));
        if (config('img_optimizer') !== 'off') {
            $engine->setOptimizer(Application::make('image.optimizer'));
        }
        return $engine;
    }
}
