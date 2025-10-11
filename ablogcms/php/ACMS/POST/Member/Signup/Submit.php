<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Login;

class ACMS_POST_Member_Signup_Submit extends ACMS_POST_Member_Signup_Confirm
{
    use Acms\Services\Login\Traits\CreateAuthUrl;

    /**
     * 管理者宛の場合true
     *
     * @var bool
     */
    protected $toAdmin = false;

    /**
     * トークンのキーを取得
     *
     * @return string
     */
    protected function getTokenKey(): string
    {
        return 'signup-confirmation';
    }

    /**
     * トークンのタイプを取得
     *
     * @return string
     */
    protected function getTokenType(): string
    {
        return 'signup-confirmation';
    }

    /**
     * 認証メールの件名テンプレートを取得
     *
     * @return string
     */
    protected function getSubjectMailTemplate(): string
    {
        if ($this->toAdmin) {
            return findTemplate(config('mail_subscribe_admin_tpl_subject'));
        }
        return findTemplate(config('mail_subscribe_tpl_subject'));
    }

    /**
     * 認証メールの本文テンプレートを取得
     *
     * @return string
     */
    protected function getBodyMailTemplate(): string
    {
        if ($this->toAdmin) {
            return findTemplate(config('mail_subscribe_admin_tpl_body'));
        }
        return findTemplate(config('mail_subscribe_tpl_body'));
    }

    /**
     *　認証メールの本文（HTML）テンプレートを取得
     *
     * @return string
     */
    protected function getBodyHtmlMailTemplate(): string
    {
        if ($this->toAdmin) {
            return findTemplate(config('mail_subscribe_admin_tpl_body_html'));
        }
        return findTemplate(config('mail_subscribe_tpl_body_html'));
    }

    /**
     * 認証メールの送信元アドレスを取得
     *
     * @return string
     */
    protected function getFromAddress(): string
    {
        if ($this->toAdmin) {
            return config('mail_subscribe_admin_from');
        }
        return config('mail_subscribe_from');
    }

    /**
     * 認証メールのBCCアドレスを取得
     *
     * @return string
     */
    protected function getBccAddress(): string
    {
        if ($this->toAdmin) {
            return implode(', ', configArray('mail_subscribe_admin_bcc'));
        }
        return implode(', ', configArray('mail_subscribe_bcc'));
    }

    /**
     * 会員登録
     *
     * @return Field_Validation
     * @throws Exception
     */
    function post(): Field_Validation
    {
        $inputField = $this->extract('field', new ACMS_Validator());
        $inputUserField = $this->extract('user');
        if ('on' === config('subscribe_login_anywhere')) {
            $this->subscribeLoginAnywhere = true;
        }
        $this->validate($inputUserField);
        if (!$this->Post->isValidAll()) {
            $this->log($inputUserField, $inputField);
            return $this->Post;
        }

        $uid = Login::findUser($inputUserField->get('mail'), BID);
        $token = $this->createToken();

        if (empty($uid)) {
            if (config('email-auth-signin') === 'on') {
                // パスワードなしのメール認証によるサインイン設定の場合、パスワードをランダムで生成
                $inputField->set('pass', uniqueString());
            }
            // ユーザー作成
            $uid = Login::createUser($inputUserField, $this->subscribeLoginAnywhere);

            Webhook::call(BID, 'user', ['user:subscribe'], $uid);
        } else {
            // すでに同じメールアドレスのユーザーがいれば、更新して承認リンクを再発行する
            Login::updateUser($uid, $inputUserField);
        }

        Common::saveField('uid', $uid, $inputField);
        Common::saveFulltext('uid', $uid, Common::loadUserFulltext($uid));

        if (config('subscribe_activation') === 'off') {
            // メールアドレスの有効性を確認しないので、そのままログインさせる
            AcmsLogger::info('メールアドレスの有効性を確認せずにログインしました', [
                'uid' => $uid,
                'email' => $inputUserField->get('mail'),
            ]);
            Login::subscriberActivation($uid);

            if (ACMS_RAM::userStatus($uid) === 'open' && strtotime(ACMS_RAM::userLoginExpire($uid) ?? '') > REQUEST_TIME) {
                generateSession($uid);

                $url = acmsLink([
                    'bid' => BID,
                ], false);

                $this->redirect($url);
            }
        } else {
            // メールを送信し、メールアドレスの有効性を確認する
            $lifetime = intval(config('user_activation_url_lifetime', 30)) * 60;
            $data = [
                'uid' => $uid,
                'email' => $inputUserField->get('mail'),
            ];
            $authUrl = $this->createAuthUrl([
                'bid' => BID,
                'signup' => true,
            ], $token, $data, $lifetime);

            $lifetime = intval(config('user_activation_url_lifetime', 30)) * 60;
            $inputUserField->set('uid', $uid);
            $isSend = $this->sendAuthenticationEmail($inputUserField, $inputField, $authUrl);

            if ($isSend) {
                // メール送信成功
                $this->Post->set('sent', 'success');

                AcmsLogger::info('会員登録申請メールを送信しました', $data);
            } else {
                // メール送信失敗
                $inputUserField->setMethod('mail', 'send', false);
                $inputUserField->validate(new ACMS_Validator());

                AcmsLogger::warning('会員登録申請メールの送信に失敗しました', $data);
            }
        }
        return $this->Post;
    }

    /**
     * メール認証メールを送信
     *
     * @param Field_Validation $inputUserField
     * @param Field_Validation $inputField
     * @param string $authUrl
     * @return bool
     * @throws Exception
     */
    protected function sendAuthenticationEmail(Field_Validation $inputUserField, Field_Validation $inputField, string $authUrl): bool
    {
        $inputField->setField('uid', $inputUserField->get('uid'));
        $inputField->setField('name', $inputUserField->get('name'));
        $inputField->setField('mail', $inputUserField->get('mail'));
        $inputField->setField('code', $inputUserField->get('code'));
        $inputField->setField('mail_mobile', $inputUserField->get('mail_mobile'));
        $inputField->setField('url', $inputUserField->get('url'));
        $inputField->setField('subscribeUrl', $authUrl);

        // 会員向けメール送信
        $isSend = $this->send($inputField->get('mail'), $inputField, $authUrl);

        // 管理者向けメール送信
        if ($adminTo = implode(', ', configArray('mail_subscribe_admin_to'))) {
            $this->toAdmin = true;
            $this->send($adminTo, $inputField, $authUrl);
        }

        return $isSend;
    }
}
