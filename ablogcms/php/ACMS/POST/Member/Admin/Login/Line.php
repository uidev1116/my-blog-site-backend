<?php

class ACMS_POST_Member_Admin_Login_Line extends ACMS_POST_Member_Sns_Base
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
     * アクションを設定（signin|admin-login|signup|register）
     * @return string
     */
    protected function getActionName(): string
    {
        return 'admin-login';
    }

    /**
     * 認証URLを取得
     *
     * @return string
     */
    protected function getAuthUrl(): string
    {
        $googleApi = App::make('line-login');
        return $googleApi->getAuthUrl();
    }
}
