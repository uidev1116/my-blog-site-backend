<?php

use Acms\Services\Facades\Login;

class ACMS_GET_Touch_UpdatePassword extends ACMS_GET
{
    public function get()
    {
        if (!Login::isLoggedIn()) {
            return '';
        }
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        assert(is_int($sessionUserId)); // ログインしていることが保証されている
        if (in_array(ACMS_RAM::userAuth($sessionUserId), Login::getSinginAuth(), true)) {
            if (config('email-auth-signin') !== 'on') {
                // パスワードなしのメール認証によるサインイン設定がオフの場合、パスワード設定を表示
                return $this->tpl;
            }
        } else {
            if (config('email-auth-login') !== 'on') {
                // パスワードなしのメール認証による管理ログイン設定がオフの場合、パスワード設定を表示
                return $this->tpl;
            }
        }
        return '';
    }
}
