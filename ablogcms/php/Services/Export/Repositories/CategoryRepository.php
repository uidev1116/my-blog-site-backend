<?php

namespace Acms\Services\Export\Repositories;

use ACMS_Filter;
use SQL;
use SQL_Select;

class CategoryRepository
{
    /**
     * カテゴリー一覧を取得クエリを取得
     *
     * @param int $bid
     * @param bool $includeChildBlogs
     * @return SQL_Select
     */
    public function getCategoriesQuery(int $bid, bool $includeChildBlogs): SQL_Select
    {
        $sql = SQL::newSelect('category');
        $sql->addLeftJoin('blog', 'category_blog_id', 'blog_id');

        if ($includeChildBlogs) {
            $filterSql = SQL::newWhere();
            ACMS_Filter::blogTree($filterSql, $bid, 'descendant-or-self');

            $filterSql2 = SQL::newWhere();
            ACMS_Filter::blogTree($filterSql2, $bid, 'ancestor-or-self');
            $filterSql2Where = SQL::newWhere();
            $filterSql2Where->addWhereOpr('category_blog_id', $bid, '=', 'OR');
            $filterSql2Where->addWhereOpr('category_scope', 'global', '=', 'OR');
            $filterSql2->addWhere($filterSql2Where);

            $where = SQL::newWhere();
            $where->addWhere($filterSql, 'OR');
            $where->addWhere($filterSql2, 'OR');

            $sql->addWhere($where);
        } else {
            ACMS_Filter::blogTree($sql, $bid, 'ancestor-or-self');
            $where = SQL::newWhere();
            $where->addWhereOpr('category_blog_id', $bid, '=', 'OR');
            $where->addWhereOpr('category_scope', 'global', '=', 'OR');
            $sql->addWhere($where);
        }
        $sql->setOrder('category_id', 'ASC');

        return $sql;
    }
}
