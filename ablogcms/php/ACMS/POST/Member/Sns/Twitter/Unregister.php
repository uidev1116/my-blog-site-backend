<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Member_Sns_Twitter_Unregister extends ACMS_POST_Member
{
    /**
     * 正常なルートからのPOSTかどうかをチェック
     *
     * @inheritDoc
     */
    protected function isValidPostRoute(): bool
    {
        return Login::isValidAuthenticatedPath(BID, PROFILE_UPDATE_SEGMENT);
    }

    /**
     * Main
     *
     * @return Field_Validation
     */
    public function post(): Field_Validation
    {
        if (!Login::isLoggedIn()) {
            return $this->Post;
        }
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        assert(is_int($sessionUserId)); // ログインしていることが保証されている
        $SQL = SQL::newUpdate('user');
        $SQL->addUpdate('user_twitter_id', '');
        $SQL->addWhereOpr('user_id', $sessionUserId);
        DB::query($SQL->get(dsn()), 'exec');
        ACMS_RAM::cacheDelete();
        ACMS_RAM::user($sessionUserId, null);

        $session = Session::handle();
        $session->set('oauth-unregister', 'success');
        $session->save();

        AcmsLogger::info('Twitter認証を解除しました');

        return $this->Post;
    }
}
