<?php

class ACMS_POST_Member_Admin_Tfa_Recovery extends ACMS_POST_Member_Tfa_Recovery
{
    /**
     * 正常なルートからのPOSTかどうかをチェック
     *
     * @inheritDoc
     */
    protected function isValidPostRoute(): bool
    {
        return Login::canLoginPage(BID, ADMIN_TFA_RECOVERY_SEGMENT);
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
}
