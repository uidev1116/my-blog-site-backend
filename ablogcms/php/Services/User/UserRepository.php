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

    /**
     * 指定ブログの主たる管理者ユーザーレコードを取得
     *
     * 指定された `$blogId` に所属する管理者のうち、`user_status = 'open'` かつ
     * `user_login_expire` が今日以降の有効なユーザーから、`user_id` が最小のレコードを返す
     * （最も古くから登録されている管理者）。該当ユーザーが存在しない場合は null を返す。
     *
     * プレビュー共有 URL など、ルートブログの代表管理者を取得したい用途では
     * 呼び出し側で `RBID`（ルートブログ ID）を渡す。
     *
     * @param int $blogId 対象ブログの ID
     * @return array<string, mixed>|null
     */
    public function findPrimaryAdmin(int $blogId): ?array
    {
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_status', 'open');
        $sql->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=');
        $sql->addWhereOpr('user_auth', 'administrator');
        $sql->addWhereOpr('user_blog_id', $blogId);
        $sql->setOrder('user_id', 'ASC');
        $sql->setLimit(1);

        $row = Database::query($sql->get(dsn()), 'row');
        if (!is_array($row)) {
            return null;
        }
        return $row;
    }
}
