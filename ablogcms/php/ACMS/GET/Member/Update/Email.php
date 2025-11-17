<?php

use Acms\Services\Login\Exceptions\BadRequestException;
use Acms\Services\Login\Exceptions\ExpiredException;

class ACMS_GET_Member_Update_Email extends ACMS_GET_Member
{
    use Acms\Services\Login\Traits\ValidateAuthUrl;

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
     * 初期処理
     *
     * @return void
     */
    protected function init(): void
    {
        if (!SUID) {
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
        $data = [];

        if ($this->shouldTryEmailAuth()) {
            // メールアドレス認証画面
            try {
                $data = $this->validateAuthUrl();
                $this->updateAddress(SUID, $data['email']);
                $tpl->add('successUpdate');
                AcmsLogger::info('メールアドレス変更に成功しました', $data);

                Webhook::call(BID, 'user', ['user:updated'], SUID);
            } catch (BadRequestException $e) {
                $tpl->add('badRequest');
                AcmsLogger::notice('不正なURLのため、メールアドレス変更に失敗しました', Common::exceptionArray($e, $data));
            } catch (ExpiredException $e) {
                $tpl->add('expired');
                AcmsLogger::notice('有効期限切れのURLのため、メールアドレス変更に失敗しました', Common::exceptionArray($e, $data));
            }
        } else {
            // メールアドレスの確認メール送信画面
            $sent = false;
            if ($this->Post->isNull()) {
            } else {
                if ($this->Post->isValidAll()) {
                    if ($this->Post->get('sent') === 'success') {
                        $sent = true;
                        $tpl->add('successSent');
                    }
                }
            }
            if (!$sent) {
                $tpl->add('beforeSend');
                $vars += $this->buildField($this->Post, $tpl, 'form');
                $tpl->add('form', $vars);
            }
        }
        $tpl->add(null, $vars);
    }

    /**
     * メールアドレスを更新
     *
     * @param int $uid
     * @param string $email
     * @return void
     */
    protected function updateAddress(int $uid, string $email): void
    {
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_mail', $email);
        $sql->addWhereOpr('user_id', $uid);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);

        $this->removeToken();
    }

    /**
     * メール認証によるメールアドレス変更を試行するかどうか
     *
     * @return bool
     */
    protected function shouldTryEmailAuth(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        if (!defined('IS_UPDATE_EMAIL_PAGE')) {
            return false;
        }
        if (IS_UPDATE_EMAIL_PAGE !== 1) {
            return false;
        }
        if (!$this->isAuthUrl()) {
            return false;
        }
        return true;
    }
}
