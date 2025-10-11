<?php

namespace Acms\Services\Export\Repositories;

use ACMS_Filter;
use SQL;
use SQL_Select;

class UserRepository
{
    /**
     * ユーザー一覧を取得クエリを取得
     *
     * @param int $bid
     * @param bool $includeChildBlogs
     * @return SQL_Select
     */
    public function getUsersQuery(int $bid, bool $includeChildBlogs): SQL_Select
    {
        $sql = SQL::newSelect('user');
        $sql->addLeftJoin('blog', 'user_blog_id', 'blog_id');
        if ($includeChildBlogs) {
            ACMS_Filter::blogTree($sql, $bid, 'descendant-or-self');
        } else {
            $sql->addWhereOpr('user_blog_id', $bid);
        }
        $sql->setOrder('user_id', 'ASC');

        return $sql;
    }
}
