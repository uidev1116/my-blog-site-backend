<?php

use Acms\Services\Facades\Login;
use Acms\Services\Facades\Tfa;

class ACMS_GET_Member_Tfa_Check extends ACMS_GET_Member_Signup
{
    /**
     * 初期処理
     *
     * @return void
     */
    protected function init(): void
    {
    }

    /**
     * テンプレート組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildTpl(Template $tpl): void
    {
        if (!Login::isLoggedIn() || !Tfa::isAvailable()) {
            return;
        }
        /** @var int|null $userId */
        $userId = UID;
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        assert(is_int($sessionUserId)); // ログインしていることが保証されている
        if ($userId !== null && $sessionUserId !== $userId) {
            return;
        }
        $vars = [];

        if (Tfa::hasValidSecretKey($sessionUserId)) {
            // 登録済み
            $vars['step'] = 'registered';
        } else {
            // 未登録 or 復号失敗 → 再登録に誘導
            $vars['step'] = 'unregistered';
        }
        $tpl->add(null, $vars);
    }
}
