<?php

declare(strict_types=1);

namespace Acms\Services\Entry;

use SQL;
use Acms\Services\Facades\Database as DB;

/**
 * エントリーのリポジトリ
 */
class EntryRepository
{
    /**
     * 次の表示順を取得
     *
     * @param int $blogId
     *
     * @return int
     **/
    public function nextSort(int $blogId): int
    {
        $sql = SQL::newSelect('entry');
        $sql->setSelect('entry_sort');
        $sql->addWhereOpr('entry_blog_id', $blogId);
        $sql->setOrder('entry_sort', 'DESC');
        $sql->setLimit(1);
        return intval(DB::query($sql->get(dsn()), 'one')) + 1;
    }

    /**
     * 次のユーザー絞り込み時の表示順を取得
     *
     * @param int $userId
     * @param int $blogId
     * @return int
     **/
    public function nextUserSort(int $userId, int $blogId): int
    {
        $sql = SQL::newSelect('entry');
        $sql->setSelect('entry_user_sort');
        $sql->addWhereOpr('entry_user_id', $userId);
        $sql->addWhereOpr('entry_blog_id', $blogId);
        $sql->setOrder('entry_user_sort', 'DESC');
        $sql->setLimit(1);
        return intval(DB::query($sql->get(dsn()), 'one')) + 1;
    }

    /**
     * 次のカテゴリー絞り込み時の表示順を取得
     *
     * @param int|null $categoryId
     * @param int $blogId
     *
     * @return int
     **/
    public function nextCategorySort(?int $categoryId, int $blogId): int
    {
        $sql = SQL::newSelect('entry');
        $sql->setSelect('entry_category_sort');
        $sql->addWhereOpr('entry_category_id', $categoryId);
        $sql->addWhereOpr('entry_blog_id', $blogId);
        $sql->setOrder('entry_category_sort', 'DESC');
        $sql->setLimit(1);
        return intval(DB::query($sql->get(dsn()), 'one')) + 1;
    }
}
