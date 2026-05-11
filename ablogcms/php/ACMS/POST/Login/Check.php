<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Preview;

class ACMS_POST_Login_Check extends ACMS_POST
{
    /**
     * @var bool
     */
    public $isCacheDelete = false;

    /**
     * @var bool
     */
    protected $isCSRF = false;

    /**
     * @var bool
     */
    protected $checkDoubleSubmit = false;

    /**
     * @inheritDoc
     */
    public function post()
    {
        Common::responseJson([
            'isLogin' => $this->isLoggedIn(),
        ]);
    }

    /**
     * ログイン中かどうか判定
     * フロントエンドで表示制御に利用するため、プレビュー中は false を返す
     *
     * @return bool
     */
    private function isLoggedIn(): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        return Login::isLoggedIn();
    }
}
