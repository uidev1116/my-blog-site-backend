<?php

namespace Acms\Services\User;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Session;
use Acms\Services\Facades\Database as DB;
use SQL;
use ACMS_RAM;

class Helper
{
    /**
     * 物理削除
     *
     * @param int $uid
     * @return void
     */
    public function physicalDelete(int $uid): void
    {
        $sql = SQL::newDelete('user');
        $sql->addWhereOpr('user_id', $uid);
        Database::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);
        ACMS_RAM::cacheDelete(); // loadUser関数のキャッシュを削除

        // 位置情報の削除
        $sql = SQL::newDelete('geo');
        $sql->addWhereOpr('geo_uid', $uid);
        Database::query($sql->get(dsn()), 'exec');
        $sql = SQL::newDelete('geo_rev');
        $sql->addWhereOpr('geo_uid', $uid);
        Database::query($sql->get(dsn()), 'exec');

        // カスタムフィールドの削除
        Common::saveField('uid', $uid);

        // フルテキストの削除
        Common::saveFulltext('uid', $uid);

        // ユーザーセッションから削除
        $userSessionSql = SQL::newDelete('user_session');
        $userSessionSql->addWhereOpr('user_session_uid', $uid);
        Database::query($userSessionSql->get(dsn()), 'exec');

        // 所属しているユーザーグループから削除
        $userGroupSql = SQL::newDelete('usergroup_user');
        $userGroupSql->addWhereOpr('user_id', $uid);
        Database::query($userGroupSql->get(dsn()), 'exec');

        // エントリーロックを解除
        $lockService = Application::make('entry.lock');
        $lockService->unlockByUser($uid);
    }

    /**
     * 論理削除
     *
     * @param int $uid
     * @return void
     */
    public function logicalDelete(int $uid): void
    {
        $email = ACMS_RAM::userMail($uid);
        $email = $email . '_withdrawal_' . date('Ymd-His');

        // 退会ステータスに変更
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_status', 'withdrawal');
        $sql->addUpdate('user_mail', $email);
        $sql->addUpdate('user_withdrawal_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $sql->addUpdate('user_twitter_id', '');
        $sql->addUpdate('user_google_id', '');
        $sql->addUpdate('user_line_id', '');
        $sql->addWhereOpr('user_id', $uid);
        Database::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);
        ACMS_RAM::cacheDelete(); // loadUser関数のキャッシュを削除

        // ユーザーセッションから削除
        $session = Session::handle();
        $session->destroy();

        $userSessionSql = SQL::newDelete('user_session');
        $userSessionSql->addWhereOpr('user_session_uid', $uid);
        Database::query($userSessionSql->get(dsn()), 'exec');
    }

    /**
     * エントリーを保持しているか判定
     *
     * @param int $uid
     * @return bool
     */
    public function entryExists(int $uid): bool
    {
        $sql = SQL::newSelect('entry');
        $sql->addWhereOpr('entry_user_id', $uid);
        $sql->setLimit(1);
        return !!DB::query($sql->get(dsn()));
    }

    /**
     * IDが一番小さい（古い）管理者アカウントを取得
     *
     * @return bool|string
     */
    public function getAdminUserWithMinId()
    {
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_status', 'open');
        $sql->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=');
        $sql->addWhereOpr('user_auth', 'administrator');
        $sql->setOrder('user_id', 'ASC');
        $sql->setLimit(1);

        return DB::query($sql->get(dsn()), 'row');
    }
}
