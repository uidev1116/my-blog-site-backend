<?php

use Acms\Modules\Get\Helpers\Category\CategoryHelper;
use Acms\Services\Facades\Database;

/**
 * Todo: array_serachの第3引数をtrueにする（型チェックのため）
 */
class ACMS_GET_Category_List extends ACMS_GET
{
    public $_axis  = [
        'cid'   => 'descendant-or-self',
        'bid'   => 'descendant-or-self',
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
            'fieldSearch' => config('category_list_field_search'),
            'amount' => config('category_list_amount'),
            'countEntryInSubcategories' => config('category_list_count_entries_in_subcategories') === 'on',
            'amountZero' => config('category_list_amount_zero'),
            'order' => config('category_list_order'),
            'level' => (int)config('category_list_level'),
            'field' => config('category_list_field'),
            'parent_loop_class' => config('category_list_parent_loop_class'),
            'loop_class' => config('category_list_loop_class'),
            'geolocation' => config('category_list_geolocation_on'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function get()
    {
        $this->setConfig();
        $categoryIds = [];
        $this->categoryHelper = new CategoryHelper($this->getBaseParams([]));

        $searchType = in_array($this->config['fieldSearch'], ['entry', 'category'], true) ? $this->config['fieldSearch'] : 'category';
        $categoryQuery = $this->categoryHelper->buildCategoryListQuery(
            $this->bid,
            $this->cid,
            $this->categoryAxis(),
            $this->keyword,
            $this->Field,
            $this->start,
            $this->end,
            $searchType,
            true,
            $this->config['countEntryInSubcategories']
        );
        $all = Database::query($categoryQuery->get(dsn()), 'all');
        if (count($all) === 0) {
            return '';
        }
        $All = [];
        //-------------
        // restructure
        foreach ($all as $row) {
            $cid = intval($row['category_id']);
            $categoryIds[] = $cid;
            foreach ($row as $key => $val) {
                $All[$key][$cid] = $val;
            }
            $All['all_amount'][$cid] = intval($All['category_entry_amount'][$cid]);
        }
        $All['all_amount'][0] = 0;

        //--------------------------
        // indexing ( swap parent )
        while (!!($cid = intval(array_search('off', $All['category_indexing'], true)))) {
            // @phpstan-ignore-next-line
            while (!!($_cid = intval(array_search($cid, $All['category_parent'])))) {
                $All['category_parent'][$_cid]  = $All['category_parent'][$cid];
            }
            foreach ($All as $key => $val) {
                unset($val[$cid]);
                $All[$key]  = $val;
            }
        }

        //---------------
        // eager loading
        $eagerLoadingCategoryFields = false;
        if ($this->config['field'] === 'on') {
            $eagerLoadingCategoryFields = eagerLoadField($categoryIds, 'cid');
        }

        //------
        // sort
        foreach ($All as $key => $val) {
            ksort($val);
            asort($val);
            $All[$key]  = $val;
        }

        //------------
        // all amount
        arsort($All['category_left']);
        foreach ($All['category_left'] as $cid => $kipple) {
            $pid    = intval($All['category_parent'][$cid]);
            if (!isset($All['all_amount'][$pid])) {
                $pid = 0;
            }
            $All['all_amount'][$pid] += intval($All['all_amount'][$cid]);
        }
        unset($All['all_amount'][0]);
        asort($All['all_amount']);
        asort($All['category_left']);

        //-----------------------------
        // amount zero ( swap parent )
        if ($this->config['amountZero'] !== 'on') {
            while (!!($cid = array_search(0, $All['all_amount'], true))) {
                // @phpstan-ignore-next-line
                while (!!($_cid = intval(array_search($cid, $All['category_parent'])))) {
                    $All['category_parent'][$_cid]  = $All['category_parent'][$cid];
                }
                foreach ($All as $key => $val) {
                    unset($val[$cid]);
                    $All[$key]  = $val;
                }
            }
        }

        //-------
        // order
        $s      = explode('-', $this->config['order']);
        $order  = isset($s[0]) ? $s[0] : 'id';
        $isDesc = isset($s[1]) ? ('desc' == $s[1]) : false;
        switch ($order) {
            case 'amount':
                $key    = 'all_amount';
                break;
            case 'sort':
                $key    = 'category_left';
                break;
            case 'code':
                $key    = 'category_code';
                break;
            default:
                $key    = 'category_id';
        }
        if ($isDesc) {
            arsort($All[$key]);
        }

        $Map    = [];
        foreach ($All[$key] as $cid => $kipple) {
            $Map[$cid]  = intval($All['category_parent'][$cid]);
        }

        //-------
        // stack
        $root = $this->cid ? ACMS_RAM::categoryParent($this->cid) : 0;
        $stack  = [$root];

        //-------------------------
        // restructure (ancestors)
        $Map    = $this->getAncestorsMap($Map, $root);

        if (empty($Map)) {
            return '';
        }

        //-------
        // tpl
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);
        $Tpl->add('ul#front');
        $Tpl->add('category:loop');

        //-------
        // level
        $level  = $this->config['level'];
        if ($level === 0) {
            $level = 1000;
        }
        $level--;

        //------------
        // descendant
        $descendant = $this->categoryAxis() === 'descendant';
        $countValues = array_count_values($Map);
        $indexValues = [];
        while (count($stack)) {
            $pid = array_pop($stack);
            if (!isset($indexValues[$pid])) {
                $indexValues[$pid] = 0;
            }
            $count = isset($countValues[$pid]) ? $countValues[$pid] : 0;
            // @phpstan-ignore-next-line
            while (!!($cid = array_search($pid, $Map))) {
                unset($Map[$cid]);

                if (!isset($All['category_id'][$cid])) {
                    // @phpstan-ignore-next-line
                    if (!!array_search($cid, $Map)) {
                        $stack[] = $pid;
                        $stack[] = $cid;
                        $level++;
                        continue 2;
                    }
                }

                $depth  = count($stack) + 1;

                if (isset($All['category_id'][$cid])) {
                    $url    = acmsLink([
                        'bid'   => $this->bid,
                        'cid'   => $cid,
                    ]);
                    $vars   = [
                        'bid'       => $this->bid,
                        'cid'       => $cid,
                        'ccd'       => $All['category_code'][$cid],
                        'name'      => $All['category_name'][$cid],
                        'amount'    => $All['all_amount'][$cid],
                        'singleAmount'  => $All['category_entry_amount'][$cid],
                        'level'     => $depth,
                        'url'       => $url,
                        'category:loop.class' => $this->config['loop_class'],
                    ];

                    if ($this->config['geolocation'] === 'on') {
                        $Geo = loadGeometry('cid', $cid, null, $this->bid);
                        if ($Geo) {
                            $vars   += $this->buildField($Geo, $Tpl, null, 'geometry');
                        }
                    }

                    if ($this->config['amount'] !== 'on') {
                        unset($vars['amount']);
                    }
                    if (CID == $cid) {
                        $vars['selected']   = config('attr_selected');
                    }

                    //-------
                    // field
                    if ($eagerLoadingCategoryFields && isset($eagerLoadingCategoryFields[$cid])) {
                        $vars += $this->buildField($eagerLoadingCategoryFields[$cid], $Tpl);
                    }

                    $Tpl->add(['li#front', 'category:loop'], $vars);

                    //------
                    // glue
                    $indexValues[$pid]++;
                    if ($indexValues[$pid] < $count) {
                        $Tpl->add(['glue', 'category:loop']);
                    }

                    $Tpl->add('category:loop', $vars);
                } else {
                    $Tpl->add(['li#front', 'category:loop']);
                    $Tpl->add('category:loop');
                }

                if ($level >= $depth) {
                    // @phpstan-ignore-next-line
                    if (!!array_search($cid, $Map)) {
                        $Tpl->add(['ul#front', 'category:loop']);
                        $Tpl->add('category:loop');
                        $stack[] = $pid;
                        $stack[] = $cid;
                        continue 2;
                    }
                }

                $Tpl->add(['li#rear', 'category:loop']);
                $Tpl->add('category:loop');
            }

            if (!$descendant || count($stack) !== 1) {
                if (count($stack) > 0) {
                    $Tpl->add(['ul#rear:glue', 'ul#rear', 'category:loop']);
                }
                $Tpl->add(['ul#rear', 'category:loop']);
                $Tpl->add('category:loop');
                if (!empty($stack)) {
                    $Tpl->add(['li#rear', 'category:loop']);
                    $Tpl->add('category:loop');
                }
            }
        }

        $rootVars = $this->getRootVars();
        $Tpl->add(null, $rootVars);

        return $Tpl->get();
    }

    protected function getAncestorsMap($Map, $root, &$i = 0)
    {
        foreach ($Map as $c => $p) {
            if (!isset($Map[$p]) && $p !== $root) {
                $Front      = [];
                $Back       = [];
                $Tmp[$p]    = ACMS_RAM::categoryParent($p);

                $j = 0;
                foreach ($Map as $c_ => $p_) {
                    if ($j < $i) {
                        $Front[$c_] = $p_;
                    }
                    $j++;
                }
                $j = 0;
                foreach ($Map as $c_ => $p_) {
                    if ($j >= $i) {
                        $Back[$c_] = $p_;
                    }
                    $j++;
                }
                $Map = $Front + $this->getAncestorsMap($Tmp, $root, $k) + $Back;
                $i   += $k;
            }
            $i++;
        }
        return $Map;
    }

    /**
     * コンフィグのセット
     *
     * @return void
     */
    protected function setConfig(): void
    {
        $this->config = $this->initVars();
    }

    /**
     * ルート変数を取得
     *
     * @return array<string, mixed>
     */
    public function getRootVars(): array
    {
        return [
            'parent.loop.class' => $this->config['parent_loop_class'],
        ];
    }
}
