<?php

use Acms\Services\Facades\Common;

class ACMS_POST_Member_Admin_LoginWithEmail extends ACMS_POST_Member_Admin_Login
{
    use Acms\Services\Login\Traits\CreateAuthUrl;
    use Acms\Services\Login\Traits\VeryfyCode;

    /**
     * 確認コードのタイプを取得
     *
     * @return string
     */
    protected function getVerifyCodeType(): string
    {
        return 'email-login-with-code';
    }

    /**
     * トークンのキーを取得
     *
     * @return string
     */
    protected function getTokenKey(): string
    {
        return 'email-login';
    }

    /**
     * トークンのタイプを取得
     *
     * @return string
     */
    protected function getTokenType(): string
    {
        return 'email-login';
    }

    /**
     * 認証メールの件名テンプレートを取得
     *
     * @return string
     */
    protected function getSubjectMailTemplate(): string
    {
        return findTemplate(config('mail_auth_login_tpl_subject'));
    }

    /**
     * 認証メールの本文テンプレートを取得
     *
     * @return string
     */
    protected function getBodyMailTemplate(): string
    {
        return findTemplate(config('mail_auth_login_tpl_body'));
    }

    /**
     *　認証メールの本文（HTML）テンプレートを取得
     *
     * @return string
     */
    protected function getBodyHtmlMailTemplate(): string
    {
        return findTemplate(config('mail_auth_login_tpl_body_html'));
    }

    /**
     * 認証メールの送信元アドレスを取得
     *
     * @return string
     */
    protected function getFromAddress(): string
    {
        return config('mail_auth_login_from');
    }

    /**
     * 認証メールのBCCアドレスを取得
     *
     * @return string
     */
    protected function getBccAddress(): string
    {
        return '';
    }

    /**
     * Run
     *
     * @inheritDoc
     */
    public function post()
    {
        $loginField = $this->extract('login');
        $email = preg_replace("/(\s|　)/", "", $loginField->get('mail'));
        $lockKey = md5('LoginWithEmail' . $email);

        if ($this->passwordAuth()) {
            $loginField->setMethod('mailAuthSignin', 'enable', false);
        }

        // ユーザー決定前のバリデート
        $this->preValidate($loginField, $email, $lockKey);
        if (!$this->Post->isValidAll()) {
            return $this->Post;
        }

        // ユーザー検索
        $all = $this->find($email, '');

        // ユーザーが見つからない or 複数見つかった
        if (empty($all) || 1 < count($all)) {
            $loginField->setValidator('mail', 'notFound', false);
            AcmsLogger::info('メールアドレスが一致しないため、ログイン処理を中止しました', [
                'email' => $email,
            ]);
            Common::logLockPost($lockKey);
            return $this->Post;
        }

        // 一意のユーザー
        $user = $all[0];
        $uid = intval($user['user_id']);

        // ユーザー検索後のバリデート
        $this->postValidate($loginField, $user);
        if (!$this->Post->isValidAll()) {
            return $this->Post;
        }

        // 認証URL
        $token = $this->createToken();
        $lifetime = intval(config('email_auth_singin_url_lifetime', 30)) * 60;
        $data = [
            'uid' => $uid,
            'email' => $email,
        ];
        $authUrl = $this->createAuthUrl([
            'bid' => BID,
            'login' => true,
        ], $token, $data, $lifetime);

        // 確認コード
        $code = $this->createVerifyCode($email, $lifetime);
        $loginField->set('verifyCode', $code);

        // メール送信
        $isSend = $this->send($email, $loginField, $authUrl);

        if ($isSend) {
            // メール送信成功
            $this->Post->set('sent', 'success');
            $loginField->set('verifyCodeProcess', 'on');
            AcmsLogger::info('管理ログインのための認証メールを送信しました', $data);
        } else {
            // メール送信失敗
            $loginField->setMethod('mail', 'send', false);
            $loginField->validate(new ACMS_Validator());
            AcmsLogger::warning('管理ログインのための認証メール送信に失敗しました', $data);
        }
        return $this->Post;
    }

    /**
     * @param string $id
     * @param string $password
     * @param mixed ...$args
     * @return array
     */
    protected function find(?string $id, ?string $password, ...$args): array
    {
        if (empty($id)) {
            return [];
        }
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_status', 'open');
        $sql->addWhereOpr('user_mail', $id);
        $anywhereOrBid = SQL::newWhere();
        $anywhereOrBid->addWhereOpr('user_login_anywhere', 'on', '=', 'OR');
        $anywhereOrBid->addWhereOpr('user_blog_id', BID, '=', 'OR');
        $sql->addWhere($anywhereOrBid);
        $sql->addWhereIn('user_auth', $this->limitedAuthority());
        $sql->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=');

        $all = DB::query($sql->get(dsn()), 'all');

        return $all;
    }
}
