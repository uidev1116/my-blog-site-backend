<?php

class ACMS_GET_Member_Admin_ResetPasswordAuth extends ACMS_GET_Member_ResetPasswordAuth
{
    /**
     * 権限の限定
     *
     * @return array
     */
    protected function limitedAuthority(): array
    {
        return Login::getAdminLoginAuth();
    }

    /**
     * メール認証によるパスワード再設定を試行するかどうかを判定
     *
     * @return bool
     */
    protected function shouldTryEmailAuth(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        if (!defined('IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE')) {
            return false;
        }
        if (IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE !== 1) {
            return false;
        }
        if (!$this->isAuthUrl()) {
            return false;
        }
        return true;
    }
}
