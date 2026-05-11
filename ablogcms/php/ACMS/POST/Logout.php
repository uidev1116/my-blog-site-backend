<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Logout extends ACMS_POST
{
    public $isCacheDelete  = false;

    public function post()
    {
        if (Login::isLoggedIn()) {
            /** @var int|null $sessionUserId */
            $sessionUserId = SUID;
            assert(is_int($sessionUserId)); // ログインしていることが保証されている
            $redirectUrl = Login::getLogoutRedirectUrl($sessionUserId);
            AcmsLogger::info('ユーザー「' . ACMS_RAM::userName($sessionUserId) . '」がログアウト処理をしました', [
                'suid' => $sessionUserId,
            ]);
            logout();

            $this->redirect($redirectUrl);
        }

        return $this->Post;
    }
}
