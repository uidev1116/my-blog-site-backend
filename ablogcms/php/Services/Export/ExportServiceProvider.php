<?php

namespace Acms\Services\Export;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Common\Lock;

class ExportServiceProvider extends ServiceProvider
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
        $container->singleton('export-repository-blog', \Acms\Services\Export\Repositories\BlogRepository::class);
        $container->singleton('export-repository-entry', \Acms\Services\Export\Repositories\EntryRepository::class);
        $container->singleton('export-repository-user', \Acms\Services\Export\Repositories\UserRepository::class);
        $container->singleton('export-repository-category', \Acms\Services\Export\Repositories\CategoryRepository::class);

        $container->singleton('export-writer-entry', \Acms\Services\Export\Writers\EntryWriter::class);
        $container->singleton('export-writer-user', \Acms\Services\Export\Writers\UserWriter::class);
        $container->singleton('export-writer-category', \Acms\Services\Export\Writers\CategoryWriter::class);

        $container->singleton('export-wxr', \Acms\Services\Export\Engines\WxrEngine::class);

        $container->singleton('export-helper', \Acms\Services\Export\Helper::class);

        $container->singleton('export-wxr-lock', function () {
            return new Lock(CACHE_DIR . 'export-wxr-lock');
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
