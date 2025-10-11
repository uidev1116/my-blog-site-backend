<?php

namespace Acms\Services\Unit;

use Acms\Contracts\ServiceProvider;
use Acms\Services\Container;
use Acms\Services\Facades\Application;
use Acms\Services\Unit\Services\JsonProcessor;
use Acms\Services\Unit\Services\Processors\BlockEditorJsonProcessor;
use Acms\Services\Unit\Services\Processors\RichEditorJsonProcessor;

class UnitServiceProvider extends ServiceProvider
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
        $container->singleton('unit-registry', 'Acms\Services\Unit\Registry');
        $container->singleton('unit-repository', function () {
            $registry = Application::make('unit-registry');
            return new Repository($registry);
        });
        $container->bind('unit-rendering-front', 'Acms\Services\Unit\Rendering\Front');
        $container->bind('unit-rendering-edit', 'Acms\Services\Unit\Rendering\Edit');

        // JSONプロセッサーの登録
        $container->singleton('unit-json-processor', function () {
            $processor = new JsonProcessor();

            // ブロックエディタプロセッサーを登録
            $processor->registerProcessor(new BlockEditorJsonProcessor());

            // リッチエディタプロセッサーを登録
            $processor->registerProcessor(new RichEditorJsonProcessor());

            return $processor;
        });
    }

    /**
     * initialize service
     *
     * @return void
     */
    public function init()
    {
        Application::bootstrap('unit-registry', function ($registry) {
            $registry->bind('block-editor', 'Acms\Services\Unit\Models\BlockEditor');
            $registry->bind('media', 'Acms\Services\Unit\Models\Media');
            $registry->bind('table', 'Acms\Services\Unit\Models\Table');
            $registry->bind('quote', 'Acms\Services\Unit\Models\Embed');
            $registry->bind('video', 'Acms\Services\Unit\Models\Video');
            $registry->bind('map', 'Acms\Services\Unit\Models\Map');
            $registry->bind('osmap', 'Acms\Services\Unit\Models\OsMap');
            $registry->bind('eximage', 'Acms\Services\Unit\Models\ExImage');
            $registry->bind('module', 'Acms\Services\Unit\Models\Module');
            $registry->bind('code', 'Acms\Services\Unit\Models\Code');
            $registry->bind('break', 'Acms\Services\Unit\Models\NewPage');
            $registry->bind('html', 'Acms\Services\Unit\Models\Html');
            $registry->bind('wysiwyg', 'Acms\Services\Unit\Models\Wysiwyg');
            $registry->bind('group', 'Acms\Services\Unit\Models\Group');
            $registry->bind('custom', 'Acms\Services\Unit\Models\Custom');
            $registry->bind('text', 'Acms\Services\Unit\Models\Text');
            $registry->bind('markdown', 'Acms\Services\Unit\Models\Markdown');
            $registry->bind('file', 'Acms\Services\Unit\Models\File');
            $registry->bind('image', 'Acms\Services\Unit\Models\Image');
            $registry->bind('rich-editor', 'Acms\Services\Unit\Models\RichEditor');
            $registry->bind('youtube', 'Acms\Services\Unit\Models\YouTube');
        });
    }
}
