<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Webhook;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Tfa;

class ACMS_POST_Member_Signin extends ACMS_POST_Member
{
    /**
     * ロックのためのキー
     *
     * @var string
     */
    protected $lockKey;

    /**
     * Run
     *
     * @inheritDoc
     */
    public function post()
    {
        $loginField = $this->extract('login');
        $inputId = preg_replace("/(\s|　)/", "", $loginField->get('mail'));
        $inputPassword = preg_replace("/(\s|　)/", "", $loginField->get('pass'));
        $lockKey = md5('Member_Signin' . $inputId);

        // ユーザー決定前のバリデート
        if (!$this->passwordAuth()) {
            $loginField->setMethod('passwordSignin', 'enable', false);
        }
        $this->preValidate($loginField, $inputId, $lockKey);
        if (!$this->Post->isValidAll()) {
            return $this->Post;
        }

        // ユーザー検索
        $all = $this->find($inputId, $inputPassword);

        // ユーザーが見つからない or 複数見つかった
        if (empty($all) || 1 < count($all)) {
            $loginField->setValidator('pass', 'auth', false);
            AcmsLogger::info('ID・パスワードが一致しないため、ログイン処理を中止しました', [
                'id' => $inputId,
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

        // ２段階認証チェック
        if ($this->checkTowFactorAuthAction($loginField, $uid)) {
            return $this->Post;
        }

        // DB更新
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_pass_reset', '');
        $sql->addUpdate('user_login_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $sql->addWhereOpr('user_id', $uid);
        DB::query($sql->get(dsn()), 'exec');

        // セッション生成
        generateSession($uid);

        AcmsLogger::info('ユーザー「' . $user['user_name'] . '」がサインインしました', [
            'id' => $uid,
        ]);

        Webhook::call(BID, 'user', ['user:login'], $uid);

        // リダイレクト処理
        Login::loginRedirect($user, $loginField->get('redirect'));
    }

    /**
     * 2段階認証のアクション
     * 戻り値が true だと、そこで処理をやめる
     *
     * @param Field_Validation $loginField
     * @param int $uid
     * @return bool
     */
    protected function checkTowFactorAuthAction(Field_Validation $loginField, int $uid): bool
    {
        if (Tfa::isAvailableAccount($uid)) {
            $loginField->set('tfa', 'on'); // ２段階認証画面を表示
            return true;
        }
        return false;
    }

    /**
     * 権限の限定
     *
     * @return array
     */
    protected function limitedAuthority(): array
    {
        return Login::getSinginAuth();
    }

    /**
     * アクセス制限のチェック
     *
     * @return bool
     */
    protected function accessRestricted(): bool
    {
        return Login::accessRestricted(false);
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
     * @param string $id
     * @param string $password
     * @param mixed ...$args
     * @return array
     */
    protected function find(?string $id, ?string $password, ...$args): array
    {
        if (empty($id) || empty($password)) {
            return [];
        }
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_status', 'open');
        $codeOrMail = SQL::newWhere();
        $codeOrMail->addWhereOpr('user_code', $id, '=', 'OR');
        $codeOrMail->addWhereOpr('user_mail', $id, '=', 'OR');
        $sql->addWhere($codeOrMail);
        $anywhereOrBid = SQL::newWhere();
        $anywhereOrBid->addWhereOpr('user_login_anywhere', 'on', '=', 'OR');
        $anywhereOrBid->addWhereOpr('user_blog_id', BID, '=', 'OR');
        $sql->addWhere($anywhereOrBid);
        $sql->addWhereIn('user_auth', $this->limitedAuthority());
        $sql->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=');

        $all = DB::query($sql->get(dsn()), 'all');
        $all = array_filter($all, function ($user) use ($password) {
            return acmsUserPasswordVerify($password, $user['user_pass'], getPasswordGeneration($user));
        });
        return $all;
    }

    /**
     * ユーザー決定前のバリデート
     *
     * @param Field_Validation $loginField
     * @param mixed ...$args
     * @return void
     */
    protected function preValidate(Field_Validation $loginField, ...$args): void
    {
        $inputId = isset($args[0]) ? $args[0] : false;
        $lockKey = isset($args[1]) ? $args[1] : false;

        if ('on' <> config('subscribe') and !!$this->Get->get('subscribe')) {
            $this->Get->delete('subscribe');
        }

        // 機能無効
        if (!Login::canMemberSignin()) {
            $loginField->setMethod('pass', 'auth', false);
            httpStatusCode('403 Forbidden');
        }

        // access restricted
        if (SUID || !$this->accessRestricted()) {
            $loginField->setMethod('pass', 'auth', false);
            httpStatusCode('403 Forbidden');
        }

        // CSRF
        if (isCSRF()) {
            $loginField->setMethod('pass', 'auth', false);
            AcmsLogger::notice('Refererがサイトのドメインと違うため、ログイン処理を中止しました', [
                'id' => $inputId,
            ]);
        }

        // 連続施行
        if ($inputId && $lockKey) {
            $trialTime = intval(config('login_trial_time', 5));
            $trialNumber = intval(config('login_trial_number', 5));
            $lockTime = intval(config('login_lock_time', 5));
            $lock = Common::validateLockPost($lockKey, $trialTime, $trialNumber, $lockTime);
            if ($lock === false) {
                $loginField->setMethod('mail', 'lock', false);
                AcmsLogger::notice('ログイン試行回数を超えているため、ログインに失敗しました', [
                    'id' => $inputId,
                    'lockKey' => $lockKey,
                    'trialTime' => $trialTime,
                    'trialNumber' => $trialNumber,
                    'lockTime' => $lockTime,
                ]);
            }
        }
        $loginField->validate(new ACMS_Validator());
    }

    /**
     * ユーザー決定後のバリデート
     *
     * @param Field_Validation $loginField
     * @param array $user
     * @param mixed ...$args
     * @return void
     */
    protected function postValidate(Field_Validation $loginField, array $user, ...$args): void
    {
        if (!Login::checkAllowedDevice($user)) {
            $loginField->setValidator('mail', 'restriction', false);
            AcmsLogger::warning('許可されていない端末からのアクセスのため、ログイン処理を中止しました', [
                'id' => $user['user_id'],
                'code' => $user['user_code'],
                'name' => $user['user_name'],
                'mail' => $user['user_mail'],
            ]);
        }
        $loginField->validate(new ACMS_Validator());
    }
}
