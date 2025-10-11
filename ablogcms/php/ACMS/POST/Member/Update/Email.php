<?php

use Acms\Services\Validator\Signin as SigninValidator;
use Acms\Services\Facades\Login;

class ACMS_POST_Member_Update_Email extends ACMS_POST_Member
{
    use Acms\Services\Login\Traits\CreateAuthUrl;

    /**
     * トークンのキーを取得
     *
     * @return string
     */
    protected function getTokenKey(): string
    {
        return 'update-email-address';
    }

    /**
     * トークンのタイプを取得
     *
     * @return string
     */
    protected function getTokenType(): string
    {
        return 'update-email-address';
    }

    /**
     * 認証メールの件名テンプレートを取得
     *
     * @return string
     */
    protected function getSubjectMailTemplate(): string
    {
        return findTemplate(config('mail_update_email_tpl_subject'));
    }

    /**
     * 認証メールの本文テンプレートを取得
     *
     * @return string
     */
    protected function getBodyMailTemplate(): string
    {
        return findTemplate(config('mail_update_email_tpl_body'));
    }

    /**
     *　認証メールの本文（HTML）テンプレートを取得
     *
     * @return string
     */
    protected function getBodyHtmlMailTemplate(): string
    {
        return findTemplate(config('mail_update_email_tpl_body_html'));
    }

    /**
     * 認証メールの送信元アドレスを取得
     *
     * @return string
     */
    protected function getFromAddress(): string
    {
        return config('mail_update_email_from');
    }

    /**
     * 認証メールのBCCアドレスを取得
     *
     * @return string
     */
    protected function getBccAddress(): string
    {
        return implode(', ', configArray('mail_update_email_bcc'));
    }

    /**
     * @return Field_Validation
     * @throws Exception
     */
    public function post(): Field_Validation
    {
        $inputField = $this->extract('user');
        $this->validate($inputField);

        if (!$this->Post->isValidAll()) {
            if (!$inputField->isValid('mail', 'required')) {
                AcmsLogger::info('メールアドレスが指定されていないため、メールアドレス変更の認証メール送信を中断しました');
            }
            if (!$inputField->isValid('mail', 'email')) {
                AcmsLogger::info('不正なメールアドレスのため、メールアドレス変更の認証メール送信を中断しました');
            }
            if (!$inputField->isValid('mail', 'doubleMail')) {
                AcmsLogger::notice('すでに登録済みのアドレスのため、メールアドレス変更の認証メール送信を中断しました');
            }
            return $this->Post;
        }

        // 認証URL
        $token = $this->createToken();
        $lifetime = intval(config('password_reset_url_lifetime', 30)) * 60;
        $data = [
            'email' => $inputField->get('mail'),
        ];
        $authUrl = $this->createAuthUrl([
            'bid' => BID,
            'update-email' => true,
        ], $token, $data, $lifetime);

        // メール送信
        $isSend = $this->send($inputField->get('mail'), $inputField, $authUrl);

        if ($isSend) {
            // メール送信成功
            $this->Post->set('sent', 'success');
            AcmsLogger::info('メールアドレス変更のための、認証メールを送信しました', $data);
        } else {
            // メール送信失敗
            $inputField->setMethod('mail', 'send', false);
            $inputField->validate(new SigninValidator());
            AcmsLogger::warning('メールアドレス変更のための、認証メール送信に失敗しました', $data);
        }
        return $this->Post;
    }

    /**
     * バリデーション
     *
     * @param Field_Validation $inputField
     */
    protected function validate(Field_Validation $inputField): void
    {
        if (!Login::canMemberSignin()) {
            $inputField->setMethod('updateEmail', 'operable', false);
            httpStatusCode('403 Forbidden');
        }
        $inputField->setMethod('mail', 'required');
        $inputField->setMethod('mail', 'email');
        if (!!$inputField->get('mail')) {
            $inputField->setMethod('mail', 'doubleMail');
        }
        $inputField->validate(new SigninValidator());
    }
}
