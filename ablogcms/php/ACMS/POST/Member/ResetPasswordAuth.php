<?php

use Acms\Services\Cache\Exceptions\NotFoundException;
use Acms\Services\Login\Exceptions\BadRequestException;
use Acms\Services\Login\Exceptions\ExpiredException;
use Acms\Services\Facades\Login;

class ACMS_POST_Member_ResetPasswordAuth extends ACMS_POST_Member
{
    use Acms\Services\Login\Traits\ValidateAuthUrl;

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
     * Run
     *
     * @return Field_Validation
     */
    public function post(): Field_Validation
    {
        $user = $this->extract('user');
        $login = $this->extract('login');
        try {
            $data = $this->validateAuthUrl();
        } catch (BadRequestException $e) {
            AcmsLogger::notice('不正なURLのため、パスワード再設定処理を中断しました', Common::exceptionArray($e));
            $login->setValidator('reset', 'badRequest', false);
            return $this->Post;
        } catch (ExpiredException $e) {
            AcmsLogger::notice('有効期限切れのURLのため、パスワード再設定処理を中断しました', Common::exceptionArray($e));
            $login->setValidator('reset', 'expired', false);
            return $this->Post;
        } catch (NotFoundException $e) {
            AcmsLogger::notice('アカウントが存在しないため、パスワード再設定処理を中断しました', Common::exceptionArray($e));
            $login->setValidator('reset', 'notFound', false);
            return $this->Post;
        }
        $uid = $this->findUser($data);
        $user->setMethod('reset', 'isOperable', !SUID && $uid > 0);
        $this->validate($user);

        if ($this->Post->isValidAll()) {
            $this->updatePassword($uid, $user->get('pass'));
            AcmsLogger::info('パスワードの再設定を行いました', [
                'uid' => $uid,
                'name' => ACMS_RAM::userName($uid),
            ]);
            if (Tfa::isAvailableAccount($uid)) {
                $login->set('reset', 'success');
                $login->set('tfa', 'on');
                return $this->Post;
            }
            // ログイン日時更新
            $sql = SQL::newUpdate('user');
            $sql->addUpdate('user_pass_reset', '');
            $sql->addUpdate('user_login_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $sql->addWhereOpr('user_id', $uid);
            DB::query($sql->get(dsn()), 'exec');

            generateSession($uid); // セッション生成
            $login->set('reset', 'success');
        } else {
            if (!$user->isValid('pass', 'required')) {
                AcmsLogger::info('パスワードが指定されていないため、パスワード再設定処理を中断しました', [
                    'uid' => $uid,
                    'email' => ACMS_RAM::userMail($uid),
                ]);
            }
            if (!$user->isValid('pass', 'password')) {
                AcmsLogger::info('不正なパスワード形式のため、パスワード再設定処理を中断しました', [
                    'uid' => $uid,
                    'email' => ACMS_RAM::userMail($uid),
                ]);
            }
            if (!$user->isValid('retype_pass', 'equalTo')) {
                AcmsLogger::info('パスワード入力が一致しないため、パスワード再設定処理を中断しました', [
                    'uid' => $uid,
                    'email' => ACMS_RAM::userMail($uid),
                ]);
            }
            if (!$user->isValid('reset', 'isOperable')) {
                AcmsLogger::info('パスワードをリセットできる権限がないため、パスワード再設定処理を中断しました', [
                    'uid' => $uid,
                    'email' => ACMS_RAM::userMail($uid),
                ]);
            }
        }
        return $this->Post;
    }

    /**
     * バリデーション
     *
     * @param Field_Validation $user
     * @return void
     */
    protected function validate(Field_Validation $user): void
    {
        if (!Login::canMemberSignin()) {
            $user->setMethod('resetPasswordAuth', 'operable', false);
            httpStatusCode('403 Forbidden');
        }
        $user->setMethod('pass', 'required');
        $user->setMethod('pass', 'password');
        $user->setMethod('retype_pass', 'equalTo', 'pass');
        $user->validate(new ACMS_Validator());
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
     * 認証URLからユーザーを特定
     *
     * @param array $context
     * @return int
     */
    protected function findUser(array $context): int
    {
        if (!isset($context['email'])) {
            return -1;
        }
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_status', 'open');
        $sql->addWhereOpr('user_mail', $context['email']);
        $sql->addWhereOpr('user_blog_id', BID);
        $sql->addWhereIn('user_auth', $this->limitedAuthority());
        $row = DB::query($sql->get(dsn()), 'row');
        if (empty($row)) {
            return -1;
        }
        return intval($row['user_id']);
    }

    /**
     * パスワードを更新
     *
     * @param int $uid
     * @param string $newPassword
     * @return void
     */
    protected function updatePassword(int $uid, string $newPassword): void
    {
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_pass', acmsUserPasswordHash($newPassword));
        $sql->addUpdate('user_pass_generation', PASSWORD_ALGORITHM_GENERATION);
        $sql->addWhereOpr('user_id', $uid);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);

        $this->removeToken(); // 使用済みのトークンを削除
    }
}
