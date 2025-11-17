<?php

use Acms\Services\Login\Exceptions\BadRequestException;
use Acms\Services\Login\Exceptions\ExpiredException;

class ACMS_GET_Member_Signin extends ACMS_GET_Member
{
    use Acms\Services\Login\Traits\ValidateAuthUrl;

    /**
     * トークンのキーを取得
     *
     * @return string
     */
    protected function getTokenKey(): string
    {
        return 'email-signin';
    }

    /**
     * トークンのタイプを取得
     *
     * @return string
     */
    protected function getTokenType(): string
    {
        return 'email-signin';
    }

    /**
     * テンプレート組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildTpl(Template $tpl): void
    {
        $block = 'signin';
        $login = $this->Post->getChild('login');

        /**
         * メール認証によるサインイン
         */
        if ($this->shouldTryEmailAuth()) {
            $this->emailAuth($tpl);
        }

        /**
         * 2段階認証
         */
        if ($login->get('tfa') === 'on') {
            $block = 'tfa';
        }

        /**
         * コード確認
         */
        if ($login->get('verifyCodeProcess') === 'on') {
            $block = 'verifyCode';
        }

        $vars = [
            'trialTime' => config('login_trial_time', 5),
            'trialNumber' => config('login_trial_number', 5),
            'lockTime' => config('login_lock_time', 5),
        ];
        if ($this->Post->isNull()) {
        } else {
            if ($this->Post->isValidAll()) {
                // メール認証メール送信成功
                if ($this->Post->get('sent') === 'success') {
                    $tpl->add(['successSent', $block]);
                }
            } else {
                // なにかしら失敗
            }
        }
        if (config('subscribe') === 'on') {
            $tpl->add(['subscribeLink', $block]);
        }
        $vars += $this->buildField($this->Post, $tpl, $block, 'signin');
        $vars['email_auth_login'] = config('email-auth-login') === 'on' ? 'on' : 'off';
        $vars['email_auth_signin'] = config('email-auth-signin') === 'on' ? 'on' : 'off';

        $tpl->add($block, $vars);
    }

    /**
     * メール認証によるサインイン
     *
     * @param Template $tpl
     * @return void
     */
    protected function emailAuth(Template $tpl): void
    {
        $data = [];

        // メールアドレス認証画面
        try {
            $data = $this->validateAuthUrl();
            if (!isset($data['uid'])) {
                throw new BadRequestException('uid情報がないため、不正なリクエストと判断しました');
            }
            $uid = intval($data['uid']);

            // DB更新
            $sql = SQL::newUpdate('user');
            $sql->addUpdate('user_pass_reset', '');
            $sql->addUpdate('user_login_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $sql->addWhereOpr('user_id', $uid);
            DB::query($sql->get(dsn()), 'exec');

            // セッション生成
            generateSession($uid);
            $this->removeToken();

            AcmsLogger::info('ユーザー「' . ACMS_RAM::userName($uid) . '」がサインインしました', [
                'id' => $uid,
            ]);

            Webhook::call(BID, 'user', ['user:login'], $uid);

            // リダイレクト処理
            Login::loginRedirect(ACMS_RAM::user($uid), '');
        } catch (BadRequestException $e) {
            $tpl->add('badRequest');
            AcmsLogger::notice('不正なURLのため、メール認証サインインに失敗しました', Common::exceptionArray($e, $data));
        } catch (ExpiredException $e) {
            $tpl->add('expired');
            AcmsLogger::notice('有効期限切れのURLのため、メール認証サインインに失敗しました', Common::exceptionArray($e, $data));
        }
    }

    /**
     * メール認証によるサインインを試行するかどうかを判定
     *
     * @return bool
     */
    protected function shouldTryEmailAuth(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        if (!defined('IS_SYSTEM_SIGNIN_PAGE')) {
            return false;
        }
        if (IS_SYSTEM_SIGNIN_PAGE !== 1) {
            return false;
        }
        if (!$this->isAuthUrl()) {
            return false;
        }
        if (config('email-auth-signin') !== 'on') {
            return false;
        }
        return true;
    }
}
