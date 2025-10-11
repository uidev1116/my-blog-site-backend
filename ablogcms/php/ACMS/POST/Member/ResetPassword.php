<?php

use Acms\Services\Validator\Signin as SigninValidator;
use Acms\Services\Facades\Login;

class ACMS_POST_Member_ResetPassword extends ACMS_POST_Member
{
    use Acms\Services\Login\Traits\CreateAuthUrl;

    /**
     * トークンのキーを取得
     *
     * @return string
     */
    protected function getTokenKey(): string
    {
        return 'reset-password';
    }

    /**
     * トークンのタイプを取得
     *
     * @return string
     */
    protected function getTokenType(): string
    {
        return 'reset-password';
    }

    /**
     * 認証メールの件名テンプレートを取得
     *
     * @return string
     */
    protected function getSubjectMailTemplate(): string
    {
        return findTemplate(config('mail_reset_password_tpl_subject'));
    }

    /**
     * 認証メールの本文テンプレートを取得
     *
     * @return string
     */
    protected function getBodyMailTemplate(): string
    {
        return findTemplate(config('mail_reset_password_tpl_body'));
    }

    /**
     *　認証メールの本文（HTML）テンプレートを取得
     *
     * @return string
     */
    protected function getBodyHtmlMailTemplate(): string
    {
        return findTemplate(config('mail_reset_password_tpl_body_html'));
    }

    /**
     * 認証メールの送信元アドレスを取得
     *
     * @return string
     */
    protected function getFromAddress(): string
    {
        return config('mail_reset_password_from');
    }

    /**
     * 認証メールのBCCアドレスを取得
     *
     * @return string
     */
    protected function getBccAddress(): string
    {
        return implode(', ', configArray('mail_reset_password_bcc'));
    }

    /**
     * パスワードを使った認証かチェック
     *
     * @return bool
     */
    protected function passwordAuth(): bool
    {
        return config('email-auth-signin') !== 'on';
    }

    /**
     * Main
     *
     * @return Field_Validation
     * @throws Exception
     */
    public function post(): Field_Validation
    {
        $this->Post->set('login', ['mail', 'To']);
        $this->Post->set('To', '&mail;');
        $inputField = $this->extract('login');
        if (!$this->passwordAuth()) {
            $inputField->setMethod('resetPassword', 'operable', false);
        }
        $this->validate($inputField);

        if (!$this->Post->isValidAll()) {
            if (!$inputField->isValid('login', 'sessionAlready')) {
                AcmsLogger::notice('すでにログイン中のため、パスワード再設定処理を中断しました', [
                    'email' => $inputField->get('mail'),
                ]);
            }
            if (!$inputField->isValid('mail', 'required')) {
                AcmsLogger::info('メールアドレスが指定されていないため、パスワード再設定処理を中断しました');
            }
            if (!$inputField->isValid('mail', 'exist')) {
                AcmsLogger::notice('存在しないメールアドレスが指定されたため、パスワード再設定処理を中断しました', [
                    'email' => $inputField->get('mail'),
                ]);
            }
            if (!$inputField->isValid('mail', 'confirmed')) {
                AcmsLogger::notice('有効化されていないメールアドレスのため、パスワード再設定処理を中断しました', [
                    'email' => $inputField->get('mail'),
                ]);
            }
            return $this->Post;
        }

        // 認証URL
        $token = $this->createToken();
        $lifetime = intval(config('password_reset_url_lifetime', 30)) * 60;
        $data = [
            'email' => $inputField->get('mail'),
        ];
        $authUrl = $this->getAuthUrl($token, $data, $lifetime);

        // メール送信
        $isSend = $this->send($inputField->get('mail'), $inputField, $authUrl);

        if ($isSend) {
            AcmsLogger::info('パスワード再設定メールを送信しました', $data);
        } else {
            // メール送信失敗
            $inputField->setMethod('mail', 'send', false);
            $inputField->validate(new SigninValidator());

            AcmsLogger::warning('パスワード再設定メールの送信に失敗しました', $data);
        }
        return $this->Post;
    }

    /**
     * 認証URLを取得
     *
     * @param string $token
     * @param array $data
     * @param int $lifetime
     * @return string
     */
    protected function getAuthUrl(string $token, array $data, int $lifetime): string
    {
        return $this->createAuthUrl([
            'bid' => BID,
            'reset-password-auth' => true,
        ], $token, $data, $lifetime);
    }

    /**
     * バリデーション
     *
     * @param Field_Validation $inputField
     * @return void
     */
    protected function validate(Field_Validation $inputField): void
    {
        if (!Login::canMemberSignin()) {
            $inputField->setMethod('resetPassword', 'operable', false);
            httpStatusCode('403 Forbidden');
        }
        $inputField->setMethod('login', 'sessionAlready', !SUID);
        $inputField->setMethod('mail', 'required');
        $inputField->setMethod('mail', 'exist');
        $inputField->setMethod('mail', 'confirmed');
        $inputField->validate(new SigninValidator());
    }
}
