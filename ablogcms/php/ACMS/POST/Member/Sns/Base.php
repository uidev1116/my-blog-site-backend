<?php

use Acms\Services\Facades\Session;
use Acms\Services\Facades\Login;

abstract class ACMS_POST_Member_Sns_Base extends ACMS_POST_Member
{
    /**
     * アクションを設定（signin|admin-login|signup|register）
     * @return string
     */
    abstract protected function getActionName(): string;

    /**
     * 認証URLを取得
     *
     * @return string
     */
    abstract protected function getAuthUrl(): string;

    /**
     * Main
     *
     * @return Field_Validation
     */
    public function post(): Field_Validation
    {
        $this->validate();
        $authUrl = $this->getAuthUrl();
        if (!empty($authUrl)) {
            $this->run($authUrl);
        }
        return $this->Post;
    }

    protected function validate()
    {
        if (config('snslogin') !== 'on') {
            die403();
        }
        $auth = config('snslogin_auth');
        if (!in_array($auth, Login::getAdminLoginAuth(), true)) {
            die403();
        }
    }

    /**
     * アクションを実行
     *
     * @param string $authUrl
     * @return void
     */
    protected function run(string $authUrl): void
    {
        $session = Session::handle();
        $session->set('sns_login_blog_id', BID);
        $session->set('sns_login_request_type', $this->getActionName());
        $session->save();

        $this->redirect($authUrl);
    }
}
