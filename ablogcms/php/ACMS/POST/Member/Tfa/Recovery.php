<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Member_Tfa_Recovery extends ACMS_POST_Member_Signin
{
    /**
     * Run
     *
     * @return Field_Validation
     */
    function post(): Field_Validation
    {
        $loginField = $this->extract('login', new ACMS_Validator());
        $inputMail = preg_replace("/(\s|　)/", "", $loginField->get('mail'));
        $inputPassword = preg_replace("/(\s|　)/", "", $loginField->get('pass'));
        $recoveryCode = preg_replace("/(\s|　)/", "", $loginField->get('recovery'));
        $lockKey = md5('Login_Tfa_Recovery' . $inputMail);

        // ユーザー決定前のバリデート
        $this->preValidate($loginField, $inputMail, $recoveryCode, $lockKey);
        if (!$this->Post->isValidAll()) {
            return $this->Post;
        }

        // ユーザー検索
        $all = $this->find($inputMail, $inputPassword);

        // ユーザーが見つからない or 複数見つかった
        if (empty($all) || 1 < count($all)) {
            $loginField->setValidator('pass', 'auth', false);
            Common::logLockPost($lockKey);
            AcmsLogger::notice('ユーザーが存在しない、または特定できないため、リカバリーコードを使った2段階認証の無効化を中断しました', [
                'id' => $inputMail,
                'recoveryCode' => $recoveryCode,
            ]);
            return $this->Post;
        }

        // リカバリーコードを検証
        $all = array_filter($all, function ($user) use ($recoveryCode) {
            return acmsUserPasswordVerify($recoveryCode, $user['user_tfa_recovery'], 3);
        });

        // ユーザーが見つからない or 複数見つかった
        if (empty($all) or 1 < count($all)) {
            $loginField->setValidator('recovery', 'auth', false);
            Common::logLockPost($lockKey);
            AcmsLogger::info('リカバリーコードが間違っているため、2段階認証の無効化を中断しました', [
                'id' => $inputMail,
                'recoveryCode' => $recoveryCode,
            ]);
            return $this->Post;
        }

        $row = $all[0];
        $uid = intval($row['user_id']);

        if ($uid > 0) {
            $this->invalidateTfa($uid);
            $loginField->set('tfaRecovery', 'success');
        }

        return $this->Post;
    }

    /**
     * 2段階認証を無効化
     * @param int $uid
     * @return void
     */
    protected function invalidateTfa(int $uid): void
    {
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_tfa_secret', null);
        $sql->addUpdate('user_tfa_secret_iv', null);
        $sql->addUpdate('user_tfa_recovery', null);
        $sql->addWhereOpr('user_id', $uid);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);

        AcmsLogger::info('2段階認証をリカバリーコードを使って、無効化しました', [
            'uid' => $uid,
            'name' => ACMS_RAM::userName($uid),
        ]);
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
        $inputMail = isset($args[0]) ? $args[0] : false;
        $recoveryCode = isset($args[1]) ? $args[1] : false;
        $lockKey = isset($args[2]) ? $args[2] : false;

        if (!Login::canMemberSignin()) {
            AcmsLogger::info('会員ログイン機能が無効のため、リカバリーコードを使った2段階認証の無効化を中断しました', [
                'id' => $inputMail,
                'recoveryCode' => $recoveryCode,
            ]);
            $loginField->setMethod('pass', 'auth', false);
        }
        if (SUID) {
            AcmsLogger::info('すでにログイン中のため、リカバリーコードを使った2段階認証の無効化を中断しました', [
                'uid' => SUID,
                'name' => ACMS_RAM::userName(SUID),
            ]);
            $loginField->setMethod('pass', 'auth', false);
        }
        if (!$this->accessRestricted()) {
            AcmsLogger::notice('接続元IPアドレスがホワイト・ブラックリスト設定に当てはまるため、リカバリーコードを使った2段階認証の無効化を中断しました', [
                'id' => $inputMail,
                'recoveryCode' => $recoveryCode,
            ]);
            $loginField->setMethod('pass', 'auth', false);
        }
        //------
        // CSRF
        if (isCSRF()) {
            AcmsLogger::notice('Referrerによって外部からのリクエストと判断されたためリカバリーコードを使った2段階認証の無効化を中断しました', [
                'id' => $inputMail,
                'recoveryCode' => $recoveryCode,
            ]);
            $loginField->setMethod('pass', 'auth', false);
        }
        //---------
        // 連続試行
        if ($inputMail) {
            $trialTime = intval(config('login_trial_time', 5));
            $trialNumber = intval(config('login_trial_number', 5));
            $lockTime = intval(config('login_lock_time', 5));
            $lock = Common::validateLockPost($lockKey, $trialTime, $trialNumber, $lockTime);
            if ($lock === false) {
                AcmsLogger::notice($trialTime . '分の間に' . $trialNumber . '回の2段階認証の無効化に失敗したため、' . $lockTime . '分間アカウントをロックしました', [
                    'id' => $inputMail,
                ]);
                $loginField->setMethod('mail', 'lock', false);
            }
        }
        $loginField->validate(new ACMS_Validator());
    }
}
