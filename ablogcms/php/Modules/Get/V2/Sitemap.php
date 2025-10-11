<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\SitemapHelper;
use Field_Search;

class Sitemap extends Base
{
    /**
     * @inheritDoc
     */
    protected $axis = [
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $config = $this->loadModuleConfig();

        $blogIndexing = $config->get('sitemap_blog_indexing') === 'on';
        $blogOrder = $config->get('sitemap_blog_order', 'id-asc');
        $blogFieldSearch = new Field_Search($config->get('sitemap_blog_field'));

        $categoryIndexing = $config->get('sitemap_category_indexing') === 'on';
        $categoryOrder = $config->get('sitemap_category_order', 'id-desc');
        $categoryFieldSearch = new Field_Search($config->get('sitemap_category_field'));

        $entryIndexing = $config->get('sitemap_entry_indexing') === 'on';
        $entryOrder = $config->get('sitemap_entry_order', 'id-desc');
        $entryLimit = (int) $config->get('sitemap_entry_limit', 5000);
        $entryFieldSearch = new Field_Search($config->get('sitemap_entry_field'));

        $sitemapHelper = new SitemapHelper($this->getBaseParams([]));
        $items = $sitemapHelper->getSitemap(
            $blogIndexing,
            $blogOrder,
            $blogFieldSearch,
            $categoryIndexing,
            $categoryOrder,
            $categoryFieldSearch,
            $entryIndexing,
            $entryOrder,
            $entryLimit,
            $entryFieldSearch
        );

        return [
            'items' => $items,
        ];
    }
}
