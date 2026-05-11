<?php

declare(strict_types=1);

namespace Acms\Services\User;

use SQL;
use Acms\Services\Facades\Database;

/**
 * ユーザーのリポジトリ
 */
class UserRepository
{
    /**
     * ユーザーが存在するかチェック
     *
     * @param int $userId ユーザーID
     * @param int|null $blogId ブログID（指定された場合、そのブログに属するかもチェック）
     * @return bool 存在する場合true
     */
    public function exists(int $userId, ?int $blogId = null): bool
    {
        $sql = SQL::newSelect('user');
        $sql->setSelect('user_id');
        $sql->addWhereOpr('user_id', $userId);
        if ($blogId !== null) {
            $sql->addWhereOpr('user_blog_id', $blogId);
        }
        $sql->setLimit(1);
        return !!Database::query($sql->get(dsn()), 'one');
    }
}
