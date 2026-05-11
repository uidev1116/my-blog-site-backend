<?php

class ACMS_POST_Member_Admin_ResetPasswordAuth extends ACMS_POST_Member_ResetPasswordAuth
{
    /**
     * 正常なルートからのPOSTかどうかをチェック
     *
     * @inheritDoc
     */
    protected function isValidPostRoute(): bool
    {
        return Login::canLoginPage(BID, ADMIN_RESET_PASSWORD_AUTH_SEGMENT);
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
}
