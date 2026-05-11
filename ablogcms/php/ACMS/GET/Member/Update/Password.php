<?php

use Acms\Services\Facades\Login;

class ACMS_GET_Member_Update_Password extends ACMS_GET_Member
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
        $userField = $this->Post->getChild('user');

        if ($this->Post->get('updated') === 'success') {
            $tpl->add('success');
        } else {
            if (!$this->Post->isNull()) {
                $userField->set('oldPass', $userField->get('oldPass'));
            }
            if ($message = config('password_validator_message')) {
                $vars['passwordPolicyMessage'] = $message;
            }
            $vars += $this->buildField($this->Post, $tpl);
            $tpl->add('notSuccessful', $vars);
        }
        $tpl->add(null, $vars);
    }
}
