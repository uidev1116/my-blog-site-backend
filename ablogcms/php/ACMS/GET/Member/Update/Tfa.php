<?php

use Acms\Services\Facades\Login;

class ACMS_GET_Member_Update_Tfa extends ACMS_GET_Member
{
    /**
     * 初期処理
     *
     * @return void
     */
    protected function init(): void
    {
        if (!Login::isLoggedIn()) {
            page404();
        }
        if (UID && UID !== SUID) { // @phpstan-ignore-line
            page404();
        }
        if (!Tfa::isAvailable()) {
            page404();
        }
    }

    /**
     * テンプレート組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildTpl(Template $tpl): void
    {
        $vars = [];
        $block = '';
        $tfaField = $this->Post->getChild('tfa');

        if ($recoveryCode = $this->Post->get('recoveryCode')) {
            $vars['recoveryCode'] = $recoveryCode;
        }

        if (Tfa::hasValidSecretKey(SUID)) {
            // 登録済み
            $block = 'step#registered';
        } else {
            // 未登録 or 復号失敗 → 再登録に誘導
            $block = 'step#unregistered';

            $secret = $tfaField->get('secret');
            if (empty($secret)) {
                $secret = Tfa::createSecret();
            }
            $vars += [
                'secret' => $secret,
                'qr-image' => Tfa::getSecretForQRCode($secret, ACMS_RAM::userName(SUID)),
                'secret-txt' => Tfa::getSecretForManual($secret)
            ];
        }
        if (!$this->Post->isNull() && $this->Post->isValidAll()) {
            if ($this->Post->get('register') === 'success') {
                $tpl->add(['success', $block], $vars);
            } elseif ($this->Post->get('unregister') === 'success') {
                $tpl->add(['success', $block], $vars);
            }
        }
        $vars += $this->buildField($this->Post, $tpl, $block);
        $tpl->add($block, $vars);
    }
}
