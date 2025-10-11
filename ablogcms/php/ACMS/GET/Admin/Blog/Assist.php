<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Auth;

class ACMS_GET_Admin_Blog_Assist extends ACMS_GET_Admin
{
    /** @inheritDoc */
    public $_scope = [
        'bid' => 'global',
        'keyword' => 'global',
    ];

    /**
     * 検索可能なカラム
     * @var string[]
     */
    protected $filterableColumns = [
        'blog_code',
        'blog_name',
    ];

    /** @inheritDoc */
    public function get()
    {
        if (!sessionWithContribution()) {
            return Common::responseJson([]);
        }
        $order = 'sort-desc';
        $order2 = config('blog_select_global_order');
        if ($order2 !== '') {
            $order = $order2;
        }
        $limit = (int)config('blog_select_limit', 999);
        $query = $this->buildQuery($order, $limit);
        $list = $this->buildList($query);
        return Common::responseJson($list);
    }

    /**
     * クエリを組み立て
     * @param string $order ソート順
     * @param int $limit 取得制限数
     * @return SQL_Select
     */
    protected function buildQuery(string $order, int $limit = 999): SQL_Select
    {
        /** @var int|null $suid */
        $suid = SUID;
        if ($suid === null) {
            throw new \RuntimeException('Authorized user is not found');
        }
        $sql = SQL::newSelect('blog');
        ACMS_Filter::blogTree($sql, BID, 'self-or-descendant');
        $sql->addWhereIn('blog_id', Auth::getAuthorizedBlog($suid));
        $sql->addGroup('blog_id');
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
            $matchedBlogs = DB::query($keywordSql->get(dsn()), 'all');
            $where = SQL::newWhere();
            $hasAncestors = false;
            foreach ($matchedBlogs as $blog) {
                $hasAncestors = true;
                $left = $blog['blog_left'];
                $right = $blog['blog_right'];
                $ancestorWhere = SQL::newWhere();
                $ancestorWhere->addWhereOpr('blog_left', $left, '<=', 'AND');
                $ancestorWhere->addWhereOpr('blog_right', $right, '>=', 'AND');
                $where->addWhere($ancestorWhere, 'OR');
            }
            if ($hasAncestors) {
                $sql->addWhere($where, 'AND');
            }
        }
        ACMS_Filter::blogOrder($sql, $order);

        return $sql;
    }

    /**
     * ブログリストを構築
     * @param SQL_Select $sql
     * @return array<int, array{label: string, value: string}> ブログ選択肢の配列
     */
    protected function buildList(SQL_Select $sql): array
    {
        /** @var array<int, array{label: string, value: string}> */
        $list = [];
        $query = $sql->get(dsn());
        $statement = DB::query($query, 'exec');
        if ($row = DB::next($statement)) {
            /** @var array<int, array<int, array<string, mixed>>> */
            $blogHierarchy = [];
            /** @var array<int, int> */
            $parentMap = [];
            /** @var array<int, array<string, mixed>> */
            $blogData = [];
            do {
                $blogId = intval($row['blog_id']);
                $parentId = intval($row['blog_parent']);
                $blogHierarchy[$parentId][] = $row;
                $parentMap[$blogId] = $parentId;
                $blogData[$blogId] = $row;
            } while (!!($row = DB::next($statement)));

            $rootParentId = min(array_keys($blogHierarchy));
            if (!isset($blogHierarchy[$rootParentId])) {
                return $list;
            }
            $stack = $blogHierarchy[$rootParentId];
            unset($blogHierarchy[$rootParentId]);

            while ($row = array_shift($stack)) {
                $blogId = intval($row['blog_id']);

                $path = [];
                $currentId = $blogId;
                while ($currentId = $parentMap[$currentId]) {
                    array_unshift($path, $blogData[$currentId]);
                    if (!isset($parentMap[$currentId])) {
                        break;
                    }
                    if ($parentMap[$currentId] === $rootParentId) {
                        break;
                    }
                }
                $path[] = $row;
                $label = '';
                foreach ($path as $blog) {
                    if ($blog['blog_parent'] > $rootParentId) {
                        $label .= ' > ';
                    }
                    $label .= $blog['blog_name'];
                }
                $list[] = [
                    'label' => $label,
                    'value' => strval($blogId),
                ];
                if (isset($blogHierarchy[$blogId])) {
                    while ($childRow = array_pop($blogHierarchy[$blogId])) {
                        array_unshift($stack, $childRow);
                    }
                    unset($blogHierarchy[$blogId]);
                }
            }
        }
        $currentBid = (int)$this->Get->get('currentBid');
        if ($currentBid > 0) {
            if (array_search(strval($currentBid), array_column($list, 'value'), true) === false) {
                $rootParentId = ACMS_RAM::blogParent(BID);
                $label = ACMS_RAM::blogName($currentBid);
                if ($label !== null) {
                    $parentId = $currentBid;
                    do {
                        $parentId = ACMS_RAM::blogParent($parentId);
                        if ($parentId === $rootParentId) {
                            break;
                        }
                        $label = ACMS_RAM::blogName($parentId) . ' > ' . $label;
                    } while (true);
                    $list[] = [
                        'label' => $label,
                        'value' => strval($currentBid),
                    ];
                }
            }
        }
        return $list;
    }
}
