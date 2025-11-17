<?php

use Acms\Services\Login\Exceptions\BadRequestException;
use Acms\Services\Login\Exceptions\ExpiredException;
use Acms\Services\Login\Exceptions\NotFoundException;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Database;

class ACMS_GET_Member_ResetPasswordAuth extends ACMS_GET_Member
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
     * テンプレート組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildTpl(Template $tpl): void
    {
        $vars = [];
        $data = [];
        $login = $this->Post->getChild('login');

        if ($login->get('reset') === 'success') {
            if ($login->get('tfa') === 'on') {
                $tpl->add(['tfa-on', 'success']);
            } else {
                $tpl->add(['tfa-off', 'success']);
            }
            $tpl->add('success');
            return;
        }


        if ($message = config('password_validator_message')) {
            $vars['passwordPolicyMessage'] = $message;
        }
        $vars += TemplateHelper::buildField($this->Post, $tpl);

        if ($this->shouldTryEmailAuth()) {
            try {
                $data = $this->validateAuthUrl();
                $this->findAccount($data);
                $tpl->add('emailAuthSuccess');
                $tpl->add('form', $vars);
                if ($this->Post->isValidAll() === false) {
                    $tpl->add('notSuccessful');
                }
                $tpl->add(null, $vars);
            } catch (BadRequestException $e) {
                Logger::notice('不正なURLのため、パスワード再設定処理を中断しました', Common::exceptionArray($e, $data));
                $tpl->add('badRequest');
                $tpl->add('notSuccessful');
            } catch (ExpiredException $e) {
                Logger::notice('有効期限切れのURLのため、パスワード再設定処理を中断しました', Common::exceptionArray($e, $data));
                $tpl->add('expired');
                $tpl->add('notSuccessful');
            } catch (NotFoundException $e) {
                Logger::notice('アカウントが存在しないため、パスワード再設定処理を中断しました', Common::exceptionArray($e, $data));
                $tpl->add('notFound');
                $tpl->add('notSuccessful');
            }
        } else {
            $tpl->add('form', $vars);
            $tpl->add(null, $vars);
        }
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
     * 対象アカウントの検索
     *
     * @param array $data
     * @return int
     * @throws NotFoundException
     */
    protected function findAccount(array $data): int
    {
        if (!isset($data['email'])) {
            throw new NotFoundException('Not found account.');
        }
        $sql = SQL::newSelect('user');
        $sql->setSelect('user_id');
        $sql->addWhereOpr('user_status', 'open');
        $sql->addWhereOpr('user_mail', $data['email']);
        $sql->addWhereIn('user_auth', $this->limitedAuthority());
        $sql->addWhereOpr('user_blog_id', BID);
        $uid = intval(Database::query($sql->get(dsn()), 'one'));
        if (empty($uid)) {
            throw new NotFoundException('Not found account.');
        }
        return $uid;
    }

    /**
     * メール認証によるパスワード再設定を試行するかどうかを判定
     *
     * @return bool
     */
    protected function shouldTryEmailAuth(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        if (!defined('IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE')) {
            return false;
        }
        if (IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE !== 1) {
            return false;
        }
        if (!$this->isAuthUrl()) {
            return false;
        }
        return true;
    }
}
