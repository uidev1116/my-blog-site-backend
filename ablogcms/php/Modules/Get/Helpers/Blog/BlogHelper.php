<?php

namespace Acms\Modules\Get\Helpers\Blog;

use Acms\Modules\Get\Helpers\BaseHelper;
use ACMS_Filter;
use Field_Search;
use SQL;
use SQL_Select;

class BlogHelper extends BaseHelper
{
    /**
     * ブログリストクエリ組み立て
     *
     * @param integer $bid
     * @param string|null $keyword
     * @param Field_Search|null $field
     * @param string $order
     * @param integer $limit
     * @param boolean $geoLocation
     * @return SQL_Select
     */
    public function buildBlogListQuery(int $bid, ?string $keyword, ?Field_Search $field, string $order, int $limit, bool $geoLocation = false): SQL_Select
    {
        $sql = SQL::newSelect('blog');
        if ($geoLocation) {
            $sql->addLeftJoin('geo', 'geo_bid', 'blog_id');
            $sql->addSelect('*');
            $sql->addSelect('geo_geometry', 'longitude', null, 'ST_X');
            $sql->addSelect('geo_geometry', 'latitude', null, 'ST_Y');
        }
        $this->filterBlogQuery($sql, $bid, $keyword, $field);
        $this->orderBlogQuery($sql, $order);
        $this->limitBlogQuery($sql, $limit);

        return $sql;
    }

    /**
     * 絞り込みクエリ組み立て
     *
     * @param SQL_Select $sql
     * @param integer $bid
     * @param string|null $keyword
     * @param Field_Search|null $field
     * @return void
     */
    public function filterBlogQuery(SQL_Select $sql, int $bid, ?string $keyword, ?Field_Search $field): void
    {
        $sql->addWhereOpr('blog_parent', $bid);
        $sql->addWhereOpr('blog_indexing', 'on');
        ACMS_Filter::blogStatus($sql);
        if ($keyword) {
            ACMS_Filter::blogKeyword($sql, $keyword);
        }
        if ($field) {
            ACMS_Filter::blogField($sql, $field);
        }
    }

    /**
     * orderクエリ組み立て
     *
     * @param SQL_Select $sql
     * @param string $order
     * @return void
     */
    public function orderBlogQuery(SQL_Select $sql, string $order): void
    {
        ACMS_Filter::blogOrder($sql, $order);
    }

    /**
     * limitクエリ組み立て
     *
     * @param SQL_Select $sql
     * @param int $limit
     * @return void
     */
    public function limitBlogQuery(SQL_Select $sql, int $limit): void
    {
        $sql->setLimit($limit);
    }
}
