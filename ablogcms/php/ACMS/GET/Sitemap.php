<?php

use Acms\Modules\Get\Helpers\SitemapHelper;

class ACMS_GET_Sitemap extends ACMS_GET
{
    public $_axis = [
        'bid'   => 'descendant-or-self',
        'cid'   => 'descendant-or-self',
    ];

    function get()
    {
        $tpl = new Template($this->tpl);

        $blogIndexing = config('sitemap_blog_indexing') === 'on';
        $blogOrder = config('sitemap_blog_order', 'id-asc');
        $blogFieldSearch = new Field_Search(config('sitemap_blog_field'));

        $categoryIndexing = config('sitemap_category_indexing') === 'on';
        $categoryOrder = config('sitemap_category_order', 'id-desc');
        $categoryFieldSearch = new Field_Search(config('sitemap_category_field'));

        $entryIndexing = config('sitemap_entry_indexing') === 'on';
        $entryOrder = config('sitemap_entry_order', 'id-desc');
        $entryLimit = (int) config('sitemap_entry_limit', 5000);
        $entryFieldSearch = new Field_Search(config('sitemap_entry_field'));

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

        foreach ($items as $item) {
            $tpl->add('url:loop', [
                'loc' => $item['loc'],
                'lastmod' => $item['lastmod'] ?? null,
            ]);
        }
        return $tpl->get();
    }
}
