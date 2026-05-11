<?php

class ACMS_POST_Member_Sns_Twitter_Register extends ACMS_POST_Member_Sns_Twitter_Signin
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
     * アクションを設定（signin|admin-login|signup|register）
     * @return string
     */
    protected function getActionName(): string
    {
        return 'register';
    }
}
