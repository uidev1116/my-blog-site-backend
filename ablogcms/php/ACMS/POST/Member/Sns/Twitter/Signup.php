<?php

class ACMS_POST_Member_Sns_Twitter_Signup extends ACMS_POST_Member_Sns_Twitter_Signin
{
    /**
     * 正常なルートからのPOSTかどうかをチェック
     *
     * @inheritDoc
     */
    protected function isValidPostRoute(): bool
    {
        return Login::canLoginPage(BID, SIGNUP_SEGMENT);
    }

    /**
     * アクションを設定（signin|admin-login|signup|register）
     * @return string
     */
    protected function getActionName(): string
    {
        return 'signup';
    }
}
