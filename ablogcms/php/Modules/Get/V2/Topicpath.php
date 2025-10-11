<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\TopicPathHelper;
use Acms\Services\Facades\Database;

class Topicpath extends Base
{
    /**
     * 階層の設定
     *
     * @inheritDoc
     */
    protected $axis = [
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    /**
     * スコープ設定
     *
     * @inheritDoc
     */
    protected $scopes = [
        'uid' => 'global',
        'cid' => 'global',
        'eid' => 'global',
        'keyword' => 'global',
        'tag' => 'global',
        'field' => 'global',
        'date' => 'global',
        'start' => 'global',
        'end' => 'global',
        'page' => 'global',
    ];

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $config = $this->loadModuleConfig();
        $topicPathHelper = new TopicPathHelper($this->getBaseParams([]));

        $vars = [
            'items' => [],
            'moduleFields' => $this->buildModuleField(),
        ];
        $loop = 1;

        // blog
        if ('0' !== strval($config->get('mo_topicpath_blog_limit'))) {
            $orderPosition = $config->get('mo_topicpath_blog_base') === 'top' ? 'top' : 'bottom';
            $blogQuery = $topicPathHelper->buildBlogQuery(
                $this->bid,
                $this->blogAxis(),
                $orderPosition,
                (int) $config->get('mo_topicpath_blog_limit')
            );
            $blogs = Database::query($blogQuery->get(dsn()), 'all');
            $blogOrder = $config->get('mo_topicpath_blog_order') === 'asc' ? 'asc' : 'desc';
            $blogData = $topicPathHelper->getBlogList(
                $blogs,
                $orderPosition,
                $blogOrder,
                $config->get('mo_topicpath_root_label'),
                $config->get('mo_topicpath_blog_field') === 'on',
                $loop
            );
            $vars['items'] = array_merge($vars['items'], $this->buildFieldData($blogData));
        }
        // category
        if ($this->cid && strval($config->get('mo_topicpath_category_limit')) !== '0') {
            $orderPosition = $config->get('mo_topicpath_category_base') === 'top' ? 'top' : 'bottom';
            $categoryQuery = $topicPathHelper->buildCategoryQuery(
                $this->cid,
                $this->categoryAxis(),
                $orderPosition,
                (int) $config->get('mo_topicpath_category_limit')
            );
            $coategories = Database::query($categoryQuery->get(dsn()), 'all');
            $categoryOrder = $config->get('mo_topicpath_category_order') === 'asc' ? 'asc' : 'desc';
            $categoryData = $topicPathHelper->getCategoryList(
                $coategories,
                $orderPosition,
                $categoryOrder,
                $this->bid,
                $config->get('mo_topicpath_category_field') === 'on',
                $loop
            );
            $vars['items'] = array_merge($vars['items'], $this->buildFieldData($categoryData));
        }
        // entry
        if ($this->eid && $config->get('mo_topicpath_entry') === 'on') {
            $entryQuery = $topicPathHelper->buildEntryQuery($this->eid);
            $entry = Database::query($entryQuery->get(dsn()), 'row');
            $entryData = $topicPathHelper->getEntry(
                $entry,
                'on' === $config->get('mo_topicpath_ignore_ecdempty'),
                $this->bid,
                $config->get('mo_topicpath_entry_field') === 'on',
                $loop
            );
            if ($entryData) {
                $vars['items'][] = $this->buildFieldData([$entryData])[0];
            }
        }
        return $vars;
    }

    /**
     * フィールドデータを構築する
     *
     * @param array $data
     * @return array
     */
    protected function buildFieldData(array $data): array
    {
        $response = [];
        foreach ($data as $item) {
            if ($item['fields']) {
                $item['fields'] = $this->buildFieldTrait($item['fields']);
            }
            $response[] = $item;
        }
        return $response;
    }
}
