<?php

use Acms\Modules\Get\Helpers\Category\CategoryHelper;
use Acms\Services\Facades\Template as TemplateHelper;

/**
 * V1 Category_Flat モジュール
 *
 * カテゴリーをフラットな構造で出力するモジュール。
 */
class ACMS_GET_Category_Flat extends ACMS_GET
{
    /**
     * @var array<'bid'|'cid', string>
     */
    public $_axis = [
        'cid' => 'descendant-or-self',
        'bid' => 'self',
    ];

    /**
     * @var array{
     *  fieldSearch: string,
     *  amount: string,
     *  countEntryInSubcategories: bool,
     *  amountZero: string,
     *  order: string,
     *  level: int,
     *  field: string,
     *  parent_loop_class: string,
     *  loop_class: string,
     *  geolocation: string,
     * }
     */
    protected $config;

    /**
     * @var \Acms\Modules\Get\Helpers\Category\CategoryHelper
     */
    protected $categoryHelper;

    /**
     * @return array{
     *  fieldSearch: string,
     *  amount: string,
     *  countEntryInSubcategories: bool,
     *  amountZero: string,
     *  order: string,
     *  level: int,
     *  field: string,
     *  parent_loop_class: string,
     *  loop_class: string,
     *  geolocation: string,
     * }
     */
    protected function initVars()
    {
        return [
            'fieldSearch' => config('category_flat_field_search'),
            'amount' => config('category_flat_amount'),
            'countEntryInSubcategories' => config('category_flat_count_entries_in_subcategories') === 'on',
            'amountZero' => config('category_flat_amount_zero'),
            'order' => $this->order !== '' && $this->order !== null ? $this->order : config('category_flat_order'),
            'level' => (int) config('category_flat_level', '99'),
            'field' => config('category_flat_field'),
            'parent_loop_class' => config('category_flat_parent_loop_class'),
            'loop_class' => config('category_flat_loop_class'),
            'geolocation' => config('category_flat_geolocation_on'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function get()
    {
        $this->config = $this->initVars();
        $this->categoryHelper = new CategoryHelper($this->getBaseParams([]));

        $tree = $this->categoryHelper->buildCategoryTreeForOutput([
            'bid' => $this->bid,
            'cid' => $this->cid,
            'categoryAxis' => $this->categoryAxis(),
            'keyword' => $this->keyword,
            'field' => $this->Field,
            'start' => $this->start,
            'end' => $this->end,
            'searchTarget' => $this->config['fieldSearch'],
            'categoryDisplayIndexingOnly' => true,
            'countEntryInSubcategories' => $this->config['countEntryInSubcategories'],
            'displayCategoryWithoutEntry' => $this->config['amountZero'] === 'on',
            'categoryDisplayDepth' => $this->config['level'] === 0 ? 99 : $this->config['level'],
            'categoryOrder' => $this->config['order'],
        ]);
        if ($tree === []) {
            return '';
        }

        $eagerLoadedField = null;
        if ($this->config['field'] === 'on') {
            $ids = $this->categoryHelper->getCategoryIdsFromTree($tree);
            $eagerLoadedField = $this->categoryHelper->eagerLoadCategoryField($ids);
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        TemplateHelper::buildModuleField($tpl, $this->bid, $this->showField);
        $loopIndex = 0;
        $this->addLoopItems($tpl, $tree, $eagerLoadedField, $loopIndex);

        return $tpl->render([
            'parent.loop.class' => $this->config['parent_loop_class'],
        ]);
    }

    /**
     * カテゴリーツリーを再帰的に走査し、`category:loop` を1件ずつ追加する。
     *
     * @param Template $tpl
     * @param array $tree CategoryHelper::buildCategoryTreeForOutput の戻り値
     * @param array<int, \Field>|null $eagerLoadedField カテゴリーIDをキーにしたカスタムフィールドの連想配列
     * @param int $index ループ全体の通し番号（参照渡し）。`glue` 出力に利用する。
     * @return void
     */
    protected function addLoopItems(Template $tpl, array $tree, ?array $eagerLoadedField, int &$index): void
    {
        foreach ($tree as $node) {
            $cid = (int) $node['category_id'];
            $depth = (int) $node['depth'];
            $vars = [
                'bid' => $this->bid,
                'cid' => $cid,
                'ccd' => $node['category_code'],
                'name' => $node['category_name'],
                'level' => $depth,
                'depth' => $depth,
                'url' => acmsLink([
                    'bid' => $this->bid,
                    'cid' => $cid,
                ]),
                'category:loop.class' => $this->config['loop_class'],
            ];
            if ($this->config['amount'] === 'on') {
                $vars['amount'] = (int) $node['category_entry_amount'];
                $vars['singleAmount'] = (int) $node['category_entry_amount'];
            }
            if (defined('CID') && intval(CID) === $cid) {
                $vars['selected'] = config('attr_selected');
            }
            if (isset($eagerLoadedField[$cid])) {
                $vars += TemplateHelper::buildField($eagerLoadedField[$cid], $tpl, ['category:loop']);
            }
            if ($this->config['geolocation'] === 'on') {
                $geo = loadGeometry('cid', $cid, null, $this->bid);
                $vars += TemplateHelper::buildField($geo, $tpl, ['category:loop'], 'geometry');
            }
            if ($index > 0) {
                $tpl->add(['glue', 'category:loop']);
            }
            $tpl->add('category:loop', $vars);
            $index++;

            if (isset($node['children']) && is_array($node['children']) && $node['children'] !== []) {
                $this->addLoopItems($tpl, $node['children'], $eagerLoadedField, $index);
            }
        }
    }
}
