<?php

declare(strict_types=1);

namespace Acms\Services\Category;

use SQL;
use Acms\Services\Facades\Database;

/**
 * カテゴリーのリポジトリ
 */
class CategoryRepository
{
    /**
     * カテゴリーが存在するかチェック
     *
     * @param int $categoryId カテゴリーID
     * @param int|null $blogId ブログID（指定された場合、そのブログに属するかもチェック）
     * @return bool 存在する場合true
     */
    public function exists(int $categoryId, ?int $blogId = null): bool
    {
        $sql = SQL::newSelect('category');
        $sql->setSelect('category_id');
        $sql->addWhereOpr('category_id', $categoryId);
        $sql->addWhereOpr('category_status', 'open');
        if ($blogId !== null) {
            $where = SQL::newWhere();
            $where->addWhereOpr('category_blog_id', $blogId, '=', 'OR');
            $where->addWhereOpr('category_scope', 'global', '=', 'OR');
            $sql->addWhere($where);
        }
        $sql->setLimit(1);
        return !!Database::query($sql->get(dsn()), 'one');
    }
}
