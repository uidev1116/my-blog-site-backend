<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Modules\Get\Helpers\BaseHelper;
use ACMS_Filter;
use SQL;
use SQL_Select;

class TopicPathHelper extends BaseHelper
{
    /**
     * トピックパスのブログを取得するSQLを生成する
     *
     * @param int $bid
     * @param string $blogAxis
     * @param 'top' | 'bottom' $orderPosition
     * @param int $limit
     * @return SQL_Select
     */
    public function buildBlogQuery(int $bid, string $blogAxis, string $orderPosition, int $limit): SQL_Select
    {
        $sql = SQL::newSelect('blog');
        ACMS_Filter::blogTree(
            $sql,
            $this->bid,
            str_replace('descendant', 'ancestor', $blogAxis)
        );
        ACMS_Filter::blogStatus($sql);
        $sql->setOrder('blog_left', ('top' === $orderPosition) ? 'ASC' : 'DESC');

        // indexing
        $case = SQL::newCase();
        $case->add(SQL::newOpr('blog_id', $bid), 1);
        $case->add(SQL::newOpr('blog_indexing', 'on'), 1);
        $case->setElse(0);
        $sql->addWhere($case);

        // limit
        if ($limit > 0) {
            $sql->setLimit($limit);
        }
        return $sql;
    }

    /**
     * トピックパスのカテゴリーを取得するSQLを生成する
     *
     * @param int $cid
     * @param string $categoryAxis
     * @param 'top'|'bottom' $orderPosition
     * @param int $limit
     * @return SQL_Select
     */
    public function buildCategoryQuery(int $cid, string $categoryAxis, string $orderPosition, int $limit): SQL_Select
    {
        $sql = SQL::newSelect('category');
        ACMS_Filter::categoryTree(
            $sql,
            $cid,
            str_replace('descendant', 'ancestor', $categoryAxis)
        );
        ACMS_Filter::categoryStatus($sql);
        $sql->setOrder('category_left', ('top' === $orderPosition) ? 'ASC' : 'DESC');
        // indexing
        $case = SQL::newCase();
        $case->add(SQL::newOpr('category_id', $cid), 1);
        $case->add(SQL::newOpr('category_indexing', 'on'), 1);
        $case->setElse(0);
        $sql->addWhere($case);
        // limit
        if ($limit > 0) {
            $sql->setLimit($limit);
        }
        return $sql;
    }

    /**
     * トピックパスのエントリーを取得するSQLを生成する
     *
     * @param int $eid
     * @return SQL_Select
     */
    public function buildEntryQuery(int $eid): SQL_Select
    {
        $sql = SQL::newSelect('entry');
        $sql->addWhereOpr('entry_id', $eid);

        return $sql;
    }

    /**
     * トピックパスのブログリストを取得する
     *
     * @param array $blogs
     * @param 'top'|'bottom' $orderPosition
     * @param 'asc'|'desc' $order
     * @param string $rootLabel
     * @param boolean $showField
     * @param int $loop
     * @return array
     */
    public function getBlogList(array $blogs, string $orderPosition, string $order, string $rootLabel, bool $showField, int &$loop): array
    {
        if (
            ('top' === $orderPosition && 'desc' === $order) ||
            ('bottom' === $orderPosition && 'asc' === $order)
        ) {
            $blogs = array_reverse($blogs);
        }
        $response = [];
        foreach ($blogs as $i => $row) {
            if ($i === 0 && $rootLabel) {
                $row['blog_name'] = $rootLabel;
            }
            $bid = (int) $row['blog_id'];
            $response[] = [
                'name' => $row['blog_name'],
                'url' => acmsLink([
                    'bid' => $bid,
                ]),
                'sNum' => $loop,
                'fields' => $showField ? loadBlogField($bid) : null,
            ];
            $loop++;
        }
        return $response;
    }

    /**
     * トピックパスのカテゴリーリストを取得する
     *
     * @param array $categories
     * @param 'top'|'bottom' $orderPosition
     * @param 'asc'|'desc' $order
     * @param int $bid
     * @param boolean $showField
     * @param integer $loop
     * @return array
     */
    public function getCategoryList(array $categories, string $orderPosition, string $order, int $bid, bool $showField, int &$loop): array
    {
        if (
            ('top' === $orderPosition && 'desc' === $order) ||
            ('bottom' === $orderPosition && 'asc' === $order)
        ) {
            $categories = array_reverse($categories);
        }
        $response = [];
        foreach ($categories as $row) {
            $cid = (int) $row['category_id'];
            $response[] = [
                'name' => $row['category_name'],
                'url' => acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                ]),
                'sNum' => $loop,
                'fields' => $showField ? loadCategoryField($cid) : null,
            ];
            $loop++;
        }
        return $response;
    }

    public function getEntry(array $entry, bool $ignoreEmptyCode, int $bid, bool $showField, int &$loop): ?array
    {
        $response = null;
        if (!$entry['entry_code'] && $ignoreEmptyCode) {
            // ignore block
        } else {
            $eid = (int) $entry['entry_id'];
            $response = [
                'name' => $entry['entry_title'],
                'url' => acmsLink([
                    'bid' => $bid,
                    'eid' => $eid,
                ]),
                'sNum' => $loop,
                'fields' => $showField ? loadEntryField($eid) : null,
            ];
            $loop++;
        }
        return $response;
    }
}
