<?php

namespace Acms\Services\Login\Traits;

use DB;
use SQL;
use ACMS_RAM;
use AcmsLogger;
use RuntimeException;
use Acms\Services\Facades\Session;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Webhook;

/**
 * SNS認証URLを作成
 */
trait SnsAuthCallback
{
    /**
     * 処理タイプ
     *
     * @var string
     */
    protected $type;

    /**
     * ターゲートブログID
     *
     * @var int
     */
    protected $targetBlogId;

    /**
     * SNSサービス名
     *
     * @return string
     */
    abstract protected function getServiceName(): string;

    /**
     * データベースにSNSのsubを登録するカラム名
     *
     * @return string
     */
    abstract protected function getKeyName(): string;

    /**
     * 認証してユーザー情報を取得
     *
     * @return array
     * @throws RuntimeException
     */
    abstract protected function oauth(): array;

    /**
     * APIレスポンスから、アカウント識別IDを取得
     *
     * @param array $data
     * @return string
     * @throws RuntimeException
     */
    abstract protected function getSubId(array $data): string;

    /**
     * APIレスポンスから、アカウント名を取得
     *
     * @param array $data
     * @return string
     * @throws RuntimeException
     */
    abstract protected function getUserName(array $data): string;

    /**
     * APIレスポンスから、Emailアドレスを取得
     *
     * @param array $data
     * @return string
     * @throws RuntimeException
     */
    abstract protected function getEmail(array $data): string;

    /**
     * APIレスポンスから、アカウントアイコンを取得
     *
     * @param array $data
     * @return string
     * @throws RuntimeException
     */
    abstract protected function getIcon(array $data): string;

    /**
     * Main
     * @return never
     */
    protected function oAuthCallbackProcess()
    {
        try {
            $this->type = $this->getType();
            $this->targetBlogId = $this->getTargetBlog();

            $data = $this->oauth();
            $this->runProcess($data);
        } catch (\Exception $e) {
            if ($this->type === 'register') {
                AcmsLogger::notice($this->getServiceName() . '連携登録に失敗しました', Common::exceptionArray($e));
            }
            if ($this->type === 'signin') {
                AcmsLogger::notice($this->getServiceName() . '認証でのサインインに失敗しました', Common::exceptionArray($e));
            }
            if ($this->type === 'admin-login') {
                AcmsLogger::notice($this->getServiceName() . '認証での管理ログインに失敗しました', Common::exceptionArray($e));
            }
            if ($this->type === 'signup') {
                AcmsLogger::notice($this->getServiceName() . '認証での会員登録に失敗しました', Common::exceptionArray($e));
            }
            $this->error();
        }
    }

    /**
     * 処理タイプをセッションから取得
     *
     * @return string
     */
    protected function getType(): string
    {
        $session = Session::handle();
        return $session->get('sns_login_request_type');
    }

    /**
     * ターゲットブログをセッションから取得
     *
     * @return int
     */
    protected function getTargetBlog(): int
    {
        $session = Session::handle();
        return intval($session->get('sns_login_blog_id', BID));
    }

    /**
     * セッションをクリーンアップ
     *
     * @return void
     */
    protected function cleanupSession(): void
    {
        $session = Session::handle();
        $session->delete('sns_login_request_type');
        $session->delete('sns_login_blog_id');
        $session->save();
    }

    /**
     * 各処理を開始
     *
     * @param array $data
     * @return never
     */
    protected function runProcess(array $data)
    {
        if (count($data) === 0) {
            throw new \InvalidArgumentException('data is empty.');
        }
        if ($this->type === 'register') {
            $this->register($data);
        }
        if ($this->type === 'signin') {
            $this->signin($data);
        }
        if ($this->type === 'admin-login') {
            $this->signin($data);
        }
        if ($this->type === 'signup') {
            $this->signup($data);
        }
        throw new \UnexpectedValueException(
            sprintf('Encountered an unrecognized oauth type: %s', $this->type)
        );
    }

    /**
     * 成功時
     *
     * @param int $uid
     * @return never
     */
    protected function success(int $uid = 0)
    {
        $this->cleanupSession();
        $session = Session::handle();
        $userLabel = '';
        if ($uid > 0) {
            $userLabel = '「' . ACMS_RAM::userName($uid) . '」が';
        }

        if ($this->type === 'register') {
            AcmsLogger::info($userLabel . $this->getServiceName() . '連携の登録をしました', [
                'uid' => $uid,
            ]);
            Webhook::call(BID, 'user', ['user:updated'], $uid);

            $session->set('oauth-register', 'success');
            $session->save();
            $this->redirect($this->targetBlogId, ['update-profile' => true]);
        }
        if ($this->type === 'signin') {
            AcmsLogger::info($userLabel . $this->getServiceName() . '認証でサインインしました', [
                'uid' => $uid,
            ]);
            Webhook::call(BID, 'user', ['user:login'], $uid);

            $this->redirect($this->targetBlogId, []);
        }
        if ($this->type === 'admin-login') {
            AcmsLogger::info($userLabel . $this->getServiceName() . '認証で管理ログインしました', [
                'uid' => $uid,
            ]);
            Webhook::call(BID, 'user', ['user:login'], $uid);

            $this->redirect($this->targetBlogId, []);
        }
        if ($this->type === 'signup') {
            AcmsLogger::info($userLabel . $this->getServiceName() . '認証で会員登録しました', [
                'uid' => $uid,
            ]);
            Webhook::call(BID, 'user', ['user:subscribe'], $uid);

            $this->redirect($this->targetBlogId, ['update-profile' => true]);
        }
        $this->redirect($this->targetBlogId, []);
    }

    /**
     * 失敗時
     *
     * @return never
     */
    protected function error()
    {
        $this->cleanupSession();
        $session = Session::handle();

        if ($this->type === 'register') {
            $session->set('oauth-register', 'error');
            $session->save();
            $this->redirect($this->targetBlogId, ['update-profile' => true]);
        }
        if ($this->type === 'signin') {
            $session->set('oauth-signin', 'error');
            $session->save();
            $this->redirect($this->targetBlogId, ['signin' => true]);
        }
        if ($this->type === 'admin-login') {
            $session->set('oauth-signin', 'error');
            $session->save();
            $this->redirect($this->targetBlogId, ['login' => true]);
        }
        if ($this->type === 'signup') {
            $session->set('oauth-signup', 'error');
            $session->save();
            $this->redirect($this->targetBlogId, ['signup' => true]);
        }
        $this->redirect($this->targetBlogId, []);
    }

    /**
     * サインイン
     *
     * @param array $data
     * @return never
     */
    protected function signin(array $data)
    {
        $sub = $this->getSubId($data);
        $userData = $this->findAccount($this->getKeyName(), $sub);
        if (empty($userData)) {
            throw new RuntimeException('アカウントが見つかりませんでした');
        }
        $uid = intval($userData['user_id']);
        $bid = intval($userData['user_blog_id']);

        if (
            1
            && ('on' === $data['user_login_anywhere'] || roleAvailableUser())
            && !isBlogAncestor(BID, $bid, true)
        ) {
            $this->targetBlogId = $bid;
        }
        generateSession($uid);
        $this->success($uid);
    }

    /**
     * 既存ユーザーに認証を登録
     *
     * @return never
     */
    protected function register(array $data)
    {
        $sub = $this->getSubId($data);
        $sql = SQL::newSelect('user');
        $sql->addSelect('user_id');
        $sql->addWhereOpr($this->getKeyName(), $sub);
        $all = DB::query($sql->get(dsn()), 'all');

        if (count($all) === 0) {
            $sql = SQL::newUpdate('user');
            $sql->addUpdate($this->getKeyName(), $sub);
            $sql->addWhereOpr('user_id', SUID);
            DB::query($sql->get(dsn()), 'exec');
            ACMS_RAM::cacheDelete();
            ACMS_RAM::user(SUID, null);

            $this->success(SUID);
        }
        $this->error();
    }

    /**
     * サインアップ
     *
     * @param array $data
     * @return never
     */
    protected function signup(array $data)
    {
        $config = Config::loadBlogConfigSet($this->targetBlogId);
        $sub = $this->getSubId($data);

        if ($config->get('snslogin') !== 'on') {
            throw new RuntimeException('SNSログイン機能が無効です');
        }
        // 重複チェック
        $all = $this->searchUserFromDB($this->getKeyName(), $sub);
        if (0 < count($all)) {
            throw new RuntimeException('すでに登録済みのユーザーです');
        }
        // ユーザーを作成
        $account = $this->extractAccountData($data);
        $uid = Login::addUserFromOauth($account);

        // ログイン
        generateSession($uid);
        $this->success(intval($uid));
    }

    /**
     * 指定された（SNSアカウント含む）ユーザー情報を取得
     *
     * @param string $key IDのタイプを指定 user_id | user_google_id | user_twitter_id
     * @param string $id
     * @return array
     */
    protected function searchUserFromDB(string $key, string $id): array
    {
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr($key, $id);
        $sql->addWhereOpr('user_status', 'open');
        $sql->addWhereIn('user_auth', $this->limitedAuthority());
        $sql->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=');

        return DB::query($sql->get(dsn()), 'all');
    }

    /**
     * 指定されたユーザーでログインできるか認証
     *
     * @param string $key IDのタイプを指定 user_id | user_google_id | user_twitter_id
     * @param string $id
     * @return null|array
     */
    protected function findAccount(string $key, string $id): ?array
    {
        if (SUID || !Login::accessRestricted(false)) {
            throw new RuntimeException('すでにログイン済みか、アクセスを制限されています');
        }
        $all = $this->searchUserFromDB($key, $id);
        if (empty($all) || 1 < count($all)) {
            throw new RuntimeException('アカウントが見つかりませんでした');
        }
        $row = $all[0];
        $uid = intval($row['user_id']);
        $bid = intval($row['user_blog_id']);

        if (!Login::checkAllowedDevice($row)) {
            throw new RuntimeException('このデバイスからアクセスを禁止されています');
        }
        if ($key !== 'user_id' && !snsLoginAuth($uid, $bid)) {
            throw new RuntimeException('SNSログイン機能を禁止されています');
        }
        if (roleAvailableUser($uid) && !roleUserLoginAuth($row)) {
            throw new RuntimeException('SNSログイン機能を禁止されています');
        }
        return $row;
    }

    /**
     * ユーザー作成用にgoogle認証からユーザー情報を抜き出し
     *
     * @param array $data
     * @return array
     */
    protected function extractAccountData($data): array
    {
        return [
            'bid' => $this->targetBlogId,
            'oauthType' => $this->getKeyName(),
            'sub' => $this->getSubId($data),
            'code' => $this->getSubId($data),
            'name' => $this->getUserName($data),
            'email' => $this->getEmail($data),
            'icon' => $this->getIcon($data),
        ];
    }

    /**
     * 権限を制限
     * @return array
     */
    protected function limitedAuthority(): array
    {
        if ($this->type === 'signin') {
            return Login::getSinginAuth();
        }
        if ($this->type === 'admin-login') {
            return Login::getAdminLoginAuth();
        }
        return Login::getSinginAuth();
    }

    /**
     * リダイレクト
     *
     * @param int $bid
     * @param array $params
     * @return never
     */
    protected function redirect(int $bid, array $params)
    {
        $params = array_merge([
            'protocol' => (SSL_ENABLE && ('on' == config('login_ssl'))) ? 'https' : 'http',
            'bid' => $bid,
        ], $params);
        $url = acmsLink($params, false);

        redirect($url);
    }
}
