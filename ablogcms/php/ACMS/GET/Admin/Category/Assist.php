<?php

use Acms\Services\Facades\Common;

class ACMS_GET_Admin_Category_Assist extends ACMS_GET_Admin
{
    /** @inheritDoc */
    public $_scope = [
        'cid' => 'global',
        'eid' => 'global',
        'keyword' => 'global',
    ];

    /**
     * 検索可能なカラム
     * @var string[]
     */
    protected $filterableColumns = [
        'category_name',
        'category_code',
    ];

    /** @inheritDoc */
    public function get()
    {
        if (!sessionWithContribution()) {
            return Common::responseJson([]);
        }
        $filterCid = 0;
        if ($this->Get->get('narrowDown') === 'true') {
            $filterCid = (int)config('entry_edit_category_filter', 0);
        }
        $order = 'sort-desc';
        $order2 = config('category_select_global_order');
        if ($order2 !== '') {
            $order = $order2;
        }
        $limit = (int)config('category_select_limit', 999);
        $query = $this->buildQuery($order, $filterCid, $limit);
        $list = $this->buildList($query, $filterCid);
        return Common::responseJson($list);
    }

    /**
     * クエリを組み立て
     * @param string $order
     * @param int $filterCid
     * @param int $limit
     * @return SQL_Select
     */
    protected function buildQuery(string $order, int $filterCid, int $limit = 999): SQL_Select
    {
        $sql = SQL::newSelect('category');
        $sql->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        ACMS_Filter::categoryTreeGlobal($sql, BID, true, null);
        if ($filterCid > 0) {
            ACMS_Filter::categoryTree($sql, $filterCid, 'descendant');
        }
        $sql->addGroup('category_id');
        $sql->addOrder('blog_left');
        $sql->setLimit($limit);

        if ($this->keyword !== '') {
            $columns = array_map(function ($column) {
                return "`{$column}`";
            }, $this->filterableColumns);

            $keywordSql = clone $sql;
            $keywordSql->addWhereOpr(
                SQL::newField(implode(',', $columns), null, false),
                '%' . addcslashes($this->keyword, '%_\\') . '%',
                'LIKE',
                'AND',
                null,
                'CONCAT'
            );
            $matchedCategories = DB::query($keywordSql->get(dsn()), 'all');
            $where = SQL::newWhere();
            $hasAncestors = false;
            foreach ($matchedCategories as $category) {
                $hasAncestors = true;
                $l = $category['category_left'];
                $r = $category['category_right'];
                $ancestorWhere = SQL::newWhere();
                $ancestorWhere->addWhereOpr('category_left', $l, '<=', 'AND');
                $ancestorWhere->addWhereOpr('category_right', $r, '>=', 'AND');
                $where->addWhere($ancestorWhere, 'OR');
            }
            if ($hasAncestors) {
                $sql->addWhere($where, 'AND');
            }
        }
        ACMS_Filter::categoryOrder($sql, $order);

        return $sql;
    }

    /**
     * リストを組み立て
     * @param SQL_Select $sql
     * @param int $filterCid
     * @return array{ label: string, value: string }[]
     */
    protected function buildList(SQL_Select $sql, int $filterCid): array
    {
        /** @var array{ label: string, value: string }[] */
        $list = [];
        $query = $sql->get(dsn());
        $statement = DB::query($query, 'exec');

        if ($row = DB::next($statement)) {
            /** @var array<int, array<int, array<string, mixed>>> */
            $categoryHierarchy = [];
            /** @var array<int, int> */
            $parentMap = [];
            /** @var array<int, array<string, mixed>> */
            $categoryData = [];
            do {
                $categoryId = intval($row['category_id']);
                $parentId = intval($row['category_parent']);
                if ($filterCid > 0 && $filterCid === $parentId) {
                    $parentId = 0;
                }
                $categoryHierarchy[$parentId][] = $row;
                $parentMap[$categoryId] = $parentId;
                $categoryData[$categoryId] = $row;
            } while (!!($row = DB::next($statement)));

            $stack = $categoryHierarchy[0];
            unset($categoryHierarchy[0]);

            while ($row = array_shift($stack)) {
                $categoryId = intval($row['category_id']);

                $blocks = [];
                $currentId = $categoryId;
                while ($currentId = $parentMap[$currentId]) {
                    array_unshift($blocks, $categoryData[$currentId]);
                    if (!isset($parentMap[$currentId])) {
                        break;
                    }
                    if ($parentMap[$currentId] === 0) {
                        break;
                    }
                }
                $blocks[] = $row;
                $label = '';
                foreach ($blocks as $i => $item) {
                    if ($i > 0) {
                        $label .= ' > ';
                    }
                    $label .= $item['category_name'];
                }
                $list[] = [
                    'label' => $label,
                    'value' => strval($categoryId),
                ];
                if (isset($categoryHierarchy[$categoryId])) {
                    while ($childRow = array_pop($categoryHierarchy[$categoryId])) {
                        array_unshift($stack, $childRow);
                    }
                    unset($categoryHierarchy[$categoryId]);
                }
            }
        }
        $currentCid = (int)$this->Get->get('currentCid');
        if ($currentCid > 0) {
            if (array_search(strval($currentCid), array_column($list, 'value'), true) === false) {
                $label = ACMS_RAM::categoryName($currentCid);
                if ($label !== null) {
                    $parentCid = $currentCid;
                    do {
                        $parentCid = ACMS_RAM::categoryParent($parentCid);
                        if ($parentCid === 0) {
                            break;
                        }
                        $label = ACMS_RAM::categoryName($parentCid) . ' > ' . $label;
                    } while (true);
                    $list[] = [
                        'label' => $label,
                        'value' => strval($currentCid),
                    ];
                }
            }
        }
        return $list;
    }
}
