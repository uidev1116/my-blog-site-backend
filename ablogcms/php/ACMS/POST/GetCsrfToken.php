<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Login;

class ACMS_POST_GetCsrfToken extends ACMS_POST
{
    public $isCacheDelete  = false;

    protected $isCSRF = false;

    public function post()
    {
        $token = '';
        if (Login::isLoggedIn()) {
            $token = Common::createCsrfToken();
        }
        Common::responseJson([
            'token' => $token,
        ]);
    }
}
