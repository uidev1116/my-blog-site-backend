<?php

namespace Acms\Modules\Get\V2\Category;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Category\CategoryHelper;

class Flat extends Base
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * スコープ設定
     *
     * @inheritDoc
     */
    protected $axis = [ // phpcs:ignore
        'cid' => 'descendant-or-self',
        'bid' => 'self',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Category\CategoryHelper
     */
    protected $categoryHelper;

    /**
     * コンフィグの取得
     *
     * @return array<string, mixed>
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            // カテゴリー系
            'categoryOrder' => $this->order !== '' ? $this->order : $config->get('category_flat_order'),
            'displayCategoryWithoutEntry' => $config->get('category_flat_amount_zero') === 'on',
            'displayEntryCount' => $config->get('category_flat_amount') === 'on',
            'countEntryInSubcategories' => $config->get('category_flat_count_entries_in_subcategories') === 'on',
            'categoryDisplayDepth' => (int) $config->get('category_flat_level', 99),
            'searchTarget' => $config->get('category_flat_field_search'),
            'displayCategoryField' => $config->get('category_flat_field') === 'on',
            'displayGeolocation' => $config->get('category_flat_geolocation_on') === 'on',
            'categoryDisplayIndexingOnly' => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        if (!$this->setConfigTrait()) {
            return [];
        }
        $this->categoryHelper = new CategoryHelper($this->getBaseParams([]));
        $vars = [
            'items' => [],
            'moduleFields' => $this->buildModuleField(),
        ];
        $params = [
            'bid' => $this->bid,
            'cid' => $this->cid,
            'categoryAxis' => $this->categoryAxis(),
            'keyword' => $this->keyword,
            'field' => $this->Field,
            'start' => $this->start,
            'end' => $this->end,
        ] + $this->config;
        $tree = $this->categoryHelper->buildCategoryTreeForOutput($params);
        if ($tree === []) {
            return $vars;
        }
        $eagerLoadedField = null;
        if ($this->config['displayCategoryField']) {
            $ids = $this->categoryHelper->getCategoryIdsFromTree($tree);
            $eagerLoadedField = $this->categoryHelper->eagerLoadCategoryField($ids);
        }
        $formattedTree = $this->categoryHelper->fixCategoryTreeData(
            $this->bid,
            $tree,
            $this->config['displayEntryCount'],
            $this->config['displayGeolocation'],
            $eagerLoadedField
        );
        $vars['items'] = $this->categoryHelper->flattenCategoryTreeData($formattedTree);
        return $vars;
    }
}
