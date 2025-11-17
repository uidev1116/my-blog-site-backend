<?php

namespace Acms\Modules\Get\Helpers\Category;

use Acms\Modules\Get\Helpers\BaseHelper;
use Field;
use ACMS_RAM;
use ACMS_Filter;
use SQL;
use SQL_Select;
use Field_Search;

class CategoryHelper extends BaseHelper
{
    use \Acms\Traits\Utilities\FieldTrait;
    use \Acms\Traits\Utilities\EagerLoadingTrait;

    /**
     * カテゴリーへのアクセス権限を確認する
     *
     * @param int $cid
     * @return boolean
     */
    public function canAccessCategory(int $cid): bool
    {
        if (!$cid) {
            return false;
        }
        $status = ACMS_RAM::categoryStatus((int) $this->cid);

        if ($status === 'open') {
            return true;
        }
        if (sessionWithAdministration() && 'close' === $status) {
            return true;
        }
        if (sessionWithSubscription() && 'secret'  === $status) {
            return true;
        }
        return false;
    }

    /**
     * カテゴリー一覧を取得するSQLを生成する
     *
     * @param int $bid
     * @param int|null $cid
     * @param string $categoryAxis
     * @param string|null $keyword
     * @param Field_Search|null $field
     * @param string|null $start
     * @param string|null $end
     * @param 'entry'|'category' $searchType
     * @param bool $indexing
     * @param bool $countEntryInSubcategories
     * @return SQL_Select
     */
    public function buildCategoryListQuery(int $bid, ?int $cid, string $categoryAxis, ?string $keyword, ?Field_Search $field, ?string $start, ?string $end, string $searchType, bool $indexing, bool $countEntryInSubcategories): SQL_Select
    {
        $sql = SQL::newSelect('category', 'c');
        $sql->addSelect('category_id', null, 'c');
        $sql->addSelect('category_code', null, 'c');
        $sql->addSelect('category_name', null, 'c');
        $sql->addSelect('category_parent', null, 'c');
        $sql->addSelect('category_left', null, 'c');
        $sql->addSelect('category_indexing', null, 'c');
        if ($countEntryInSubcategories) {
            $sql->addSelect(SQL::newOpr(
                SQL::newFunction(SQL::newFunction('main.entry_id', 'DISTINCT'), 'COUNT'),
                SQL::newFunction(SQL::newFunction('sub.entry_id', 'DISTINCT'), 'COUNT'),
                '+'
            ), 'category_entry_amount');
        } else {
            $sql->addSelect(SQL::newFunction(SQL::newFunction('main.entry_id', 'DISTINCT'), 'COUNT'), 'category_entry_amount');
        }
        $sql->addLeftJoin('blog', 'blog_id', 'category_blog_id', 'b', 'c');

        $mainEntry = SQL::newSelect('entry');
        $mainEntry->addSelect('entry_id', null);
        $mainEntry->addSelect('entry_category_id', null);
        ACMS_Filter::entrySession($mainEntry);
        if ($start && $end) {
            ACMS_Filter::entrySpan($mainEntry, $start, $end);
        }
        $mainEntry->addWhereOpr('entry_blog_id', $bid);
        $sql->addLeftJoin($mainEntry, 'entry_category_id', 'category_id', 'main', 'c');

        if ($countEntryInSubcategories) {
            $subEntry = SQL::newSelect('entry');
            $subEntry->addSelect('entry_id', null);
            $subEntry->addSelect('entry_category_id', null);
            ACMS_Filter::entrySession($subEntry);
            if ($start && $end) {
                ACMS_Filter::entrySpan($subEntry, $start, $end);
            }
            $subEntry->addWhereOpr('entry_blog_id', $bid);
            $sql->addLeftJoin('entry_sub_category', 'entry_sub_category_id', 'category_id', 'esc', 'c');
            $sql->addLeftJoin($subEntry, 'entry_id', 'entry_sub_category_eid', 'sub', 'esc');
        }

        ACMS_Filter::blogTree($sql, $bid, 'ancestor-or-self');
        if ($cid) {
            ACMS_Filter::categoryTree($sql, $cid, $categoryAxis);
        }
        ACMS_Filter::categoryStatus($sql);
        if ($keyword) {
            ACMS_Filter::categoryKeyword($sql, $keyword);
        }
        if ($field) {
            if ($searchType === 'entry') {
                ACMS_Filter::entryField($sql, $field, 'main');
            } else {
                ACMS_Filter::categoryField($sql, $field);
            }
        }
        if (!$cid && $categoryAxis === 'self') {
            $sql->addWhereOpr('category_parent', 0);
        }
        if ($indexing) {
            $sql->addWhereOpr('category_indexing', 'on');
        }
        $where = SQL::newWhere();
        $where->addWhereOpr('category_blog_id', $bid, '=', 'OR');
        $where->addWhereOpr('category_scope', 'global', '=', 'OR');
        $sql->addWhere($where);

        $sql->addGroup('category_id', 'c');
        $sql->addGroup('category_code', 'c');
        $sql->addGroup('category_name', 'c');
        $sql->addGroup('category_parent', 'c');
        $sql->addGroup('category_left', 'c');
        $sql->addGroup('category_indexing', 'c');

        return $sql;
    }

    /**
     * カテゴリツリーを構築する
     *
     * @param array $categories
     * @param integer $parentId
     * @return array
     */
    public function buildTree(array $categories, $parentId = 0, int $depth = 0, int $maxDepth = 99): array
    {
        $tree = [];
        foreach ($categories as $category) {
            $parentCategoryId = (int) $category['category_parent'];
            if ($parentCategoryId === $parentId) {
                // 現在の深さが最大深さ未満の場合のみ再帰を実行
                if ($depth < $maxDepth) {
                    $category['children'] = $this->buildTree($categories, (int) $category['category_id'], $depth + 1, $maxDepth);
                } else {
                    $category['children'] = []; // 最大深さに達した場合、空の配列を設定
                }
                $category['depth'] = $depth;
                $tree[] = $category;
            }
        }
        return $tree;
    }

    /**
     * カテゴリツリーから空のカテゴリを削除する
     *
     * @param array $categories
     * @return array
     */
    public function removeEmptyCategories(array $categories): array
    {
        $filtered = [];
        foreach ($categories as $category) {
            // 子カテゴリの再帰的なフィルタリング
            if (isset($category['children']) && is_array($category['children'])) {
                $category['children'] = $this->removeEmptyCategories($category['children']);
            }
            // 現在のカテゴリまたは子カテゴリに有効なエントリがある場合、リストに追加
            // 末端でcategory_entry_amountが0のアイテムは除外する
            if ($category['category_entry_amount'] > 0 || (isset($category['children']) && is_array($category['children']))) {
                $filtered[] = $category;
            }
        }
        return $filtered;
    }

    public function sortTree($tree, string $key, bool $isAsc = true): array
    {
        // 現在の階層を指定されたキーでソート
        if ($isAsc) {
            usort($tree, function ($a, $b) use ($key) {
                return $a[$key] <=> $b[$key];
            });
        } else {
            usort($tree, function ($a, $b) use ($key) {
                return $b[$key] <=> $a[$key];
            });
        }
        // 各ノードの子要素がある場合、再帰的にソート
        foreach ($tree as &$node) {
            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = $this->sortTree($node['children'], $key, $isAsc);
            }
        }
        return $tree;
    }

    /**
     * カテゴリツリーのデータを整形する
     *
     * @param integer $bid
     * @param array $categories
     * @param boolean $showEntryCount
     * @param boolean $showGeolocation
     * @param Field[]|null $eagerLoadedField
     * @return array
     */
    public function fixCategoryTreeData(int $bid, array $categories, bool $showEntryCount, bool $showGeolocation, ?array $eagerLoadedField): array
    {
        $fixed = [];
        foreach ($categories as $category) {
            $cid = (int) $category['category_id'];
            $fixedCategory = [
                'cid' => $cid,
                'code' => $category['category_code'],
                'name' => $category['category_name'],
                'depth' => $category['depth'],
                'url' => acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                ]),
                'fields' => null,
            ];
            if ($showEntryCount) {
                $fixedCategory['amount'] = (int) $category['category_entry_amount'];
            }
            if (isset($eagerLoadedField[$cid])) {
                $cFields = $this->buildFieldTrait($eagerLoadedField[$cid]);
                $fixedCategory['fields'] = $cFields ? $cFields : null;
            }
            if ($showGeolocation) {
                $geo = loadGeometry('cid', $cid, null, $bid);
                $geoData = $this->buildFieldTrait($geo);
                $fixedCategory['geolocation'] = $geoData ? $geoData : null;
            }
            if (isset($category['children']) && is_array($category['children']) && $category['children']) {
                $fixedCategory['children'] = $this->fixCategoryTreeData($bid, $category['children'], $showEntryCount, $showGeolocation, $eagerLoadedField);
            }
            $fixed[] = $fixedCategory;
        }
        return $fixed;
    }

    /**
     * カテゴリフィールドを一括で取得する
     *
     * @param int[] $categoryIds
     * @return array
     */
    public function eagerLoadCategoryField(array $categoryIds): array
    {
        return $this->eagerLoadFieldTrait($categoryIds, 'cid');
    }

    /**
     * カテゴリツリーからカテゴリIDのリストを取得する
     *
     * @param array $categories
     * @return int[]
     */
    public function getCategoryIdsFromTree(array $categories): array
    {
        $ids = [];
        foreach ($categories as $category) {
            $ids[] = (int) $category['category_id'];
            if (isset($category['children']) && is_array($category['children']) && $category['children']) {
                $ids = array_merge($ids, $this->getCategoryIdsFromTree($category['children']));
            }
        }
        return $ids;
    }
}
