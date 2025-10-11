<?php

namespace Acms\Services\BlockEditor;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;

class BlockEditorServiceProvider extends ServiceProvider
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
        $container->singleton('block-editor', 'Acms\Services\BlockEditor\Helper');
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
