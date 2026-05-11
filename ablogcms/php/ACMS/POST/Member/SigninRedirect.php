<?php

use Acms\Services\Facades\Session;
use Acms\Services\Facades\Common;

class ACMS_POST_Member_SigninRedirect extends ACMS_POST_Member
{
    /**
     * 正常なルートからのPOSTかどうかをチェック
     *
     * @inheritDoc
     */
    protected function isValidPostRoute(): bool
    {
        return true;
    }

    /**
     * キャッシュ削除をオフ
     *
     * @var bool
     */
    public $isCacheDelete = false;

    /**
     * CSRF対策
     *
     * @var bool
     */
    protected $isCSRF = false;

    /**
     * 二重送信対策
     *
     * @var bool
     */
    protected $checkDoubleSubmit = false;

    /**
     * Main
     */
    public function post()
    {
        $post = new Field($this->Post);
        $redirectUrl = acmsLink(Common::getUriObject($post), true, true);

        $phpSession = Session::handle();
        $phpSession->set('acms-login-redirect', $redirectUrl);
        $phpSession->save();

        $signinPageLink = acmsLink([
            'bid' => BID,
            'signin' => true,
        ]);
        redirect($signinPageLink);
    }
}
