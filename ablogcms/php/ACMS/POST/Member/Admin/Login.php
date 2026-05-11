<?php

class ACMS_POST_Member_Admin_Login extends ACMS_POST_Member_Signin
{
    /**
     * 正常なルートからのPOSTかどうかをチェック
     *
     * @inheritDoc
     */
    protected function isValidPostRoute(): bool
    {
        return Login::canLoginPage(BID, LOGIN_SEGMENT);
    }

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
     * アクセス制限のチェック
     *
     * @return bool
     */
    protected function canAccessFromCurrentIp(): bool
    {
        return Login::canAccessAdminLoginFromCurrentIp();
    }

    /**
     * パスワードを使った認証かチェック
     *
     * @return bool
     */
    protected function passwordAuth(): bool
    {
        return config('email-auth-login') !== 'on';
    }
}
