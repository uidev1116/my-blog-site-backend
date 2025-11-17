<?php

namespace Acms\Modules\Get\V2\Category;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Category\CategoryHelper;
use Acms\Services\Facades\Database;

class Tree extends Base
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
            'categoryOrder' => $this->order ?: $config->get('category_list_order'),
            'displayCategoryWithoutEntry' => $config->get('category_list_amount_zero') === 'on',
            'displayEntryCount' => $config->get('category_list_amount') === 'on',
            'countEntryInSubcategories' => $config->get('category_list_count_entries_in_subcategories') === 'on',
            'categoryDisplayDepth' => (int) $config->get('category_list_level', 99),
            'searchTarget' => $config->get('category_list_field_search'),
            'displayCategoryField' => $config->get('category_list_field') === 'on',
            'displayGeolocation' => $config->get('category_list_geolocation_on') === 'on',
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
        $categoryQuery = $this->categoryHelper->buildCategoryListQuery(
            $this->bid,
            $this->cid,
            $this->categoryAxis(),
            $this->keyword,
            $this->Field,
            $this->start,
            $this->end,
            $this->config['searchTarget'],
            $this->config['categoryDisplayIndexingOnly'],
            $this->config['countEntryInSubcategories']
        );
        $all = Database::query($categoryQuery->get(dsn()), 'all');
        if (empty($all)) {
            return $vars;
        }
        $tree = $this->categoryHelper->buildTree($all, 0, 1, $this->config['categoryDisplayDepth']);
        if (!$this->config['displayCategoryWithoutEntry']) {
            $tree = $this->categoryHelper->removeEmptyCategories($tree);
        }
        $tree = $this->sort($tree, $this->config['categoryOrder']);

        $eagerLoadedField = null;
        if ($this->config['displayCategoryField']) {
            $ids = $this->categoryHelper->getCategoryIdsFromTree($tree);
            $eagerLoadedField = $this->categoryHelper->eagerLoadCategoryField($ids);
        }
        $vars['items'] = $this->categoryHelper->fixCategoryTreeData(
            $this->bid,
            $tree,
            $this->config['displayEntryCount'],
            $this->config['displayGeolocation'],
            $eagerLoadedField
        );
        return $vars;
    }

    /**
     * カテゴリツリーを並び替える
     *
     * @param array $tree
     * @param string $sortConfig
     * @return array
     */
    protected function sort(array $tree, string $sortConfig): array
    {
        [$target, $order] = explode('-', $sortConfig);
        switch ($target) {
            case 'amount':
                $key = 'category_entry_amount';
                break;
            case 'sort':
                $key = 'category_left';
                break;
            case 'code':
                $key = 'category_code';
                break;
            default:
                $key = 'category_id';
        }
        $tree = $this->categoryHelper->sortTree($tree, $key, $order === 'asc');

        return $tree;
    }
}
