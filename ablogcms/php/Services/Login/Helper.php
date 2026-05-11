<?php

namespace Acms\Services\Login;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Image;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Session;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Preview;
use Acms\Services\Login\Exceptions\BadRequestException;
use Acms\Services\Login\Exceptions\ExpiredException;
use DB;
use SQL;
use ACMS_RAM;
use Field;
use Field_Validation;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Uri\WhatWg\Url as WhatWgUrl;

class Helper
{
    /**
     * 認証系ページの定数をセット
     *
     * @param \Field $queryParameter
     * @return void
     */
    public function setConstantsAuthSystemPage(Field $queryParameter): void
    {
        define('IS_SYSTEM_LOGIN_PAGE', (int)$queryParameter->get('login'));
        define('IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE', (int)$queryParameter->get('admin-reset-password'));
        define('IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE', (int)$queryParameter->get('admin-reset-password-auth'));
        define('IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE', (int)$queryParameter->get('admin-tfa-recovery'));

        define('IS_SYSTEM_SIGNIN_PAGE', (int)$queryParameter->get('signin'));
        define('IS_SYSTEM_SIGNUP_PAGE', (int)$queryParameter->get('signup'));
        define('IS_SYSTEM_RESET_PASSWORD_PAGE', (int)$queryParameter->get('reset-password'));
        define('IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE', (int)$queryParameter->get('reset-password-auth'));
        define('IS_SYSTEM_TFA_RECOVERY_PAGE', (int)$queryParameter->get('tfa-recovery'));

        define('IS_UPDATE_PROFILE_PAGE', (int)$queryParameter->get('update-profile'));
        define('IS_UPDATE_PASSWORD_PAGE', (int)$queryParameter->get('update-password'));
        define('IS_UPDATE_EMAIL_PAGE', (int)$queryParameter->get('update-email'));
        define('IS_UPDATE_TFA_PAGE', (int)$queryParameter->get('update-tfa'));
        define('IS_WITHDRAWAL_PAGE', (int)$queryParameter->get('withdrawal'));
        define('IS_REVISION_PREVIEW_PAGE', $queryParameter->get('tpl') === 'ajax/revision/preview.html' ? 1 : 0);

        if (
            IS_SYSTEM_LOGIN_PAGE ||
            IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE ||
            IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE ||
            IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE ||
            IS_SYSTEM_SIGNIN_PAGE ||
            IS_SYSTEM_SIGNUP_PAGE ||
            IS_SYSTEM_RESET_PASSWORD_PAGE ||
            IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE ||
            IS_SYSTEM_TFA_RECOVERY_PAGE
        ) {
            define('IS_AUTH_SYSTEM_PAGE', 1);
        } else {
            define('IS_AUTH_SYSTEM_PAGE', 0);
        }

        if (
            IS_UPDATE_PROFILE_PAGE ||
            IS_UPDATE_PASSWORD_PAGE ||
            IS_UPDATE_EMAIL_PAGE ||
            IS_UPDATE_TFA_PAGE ||
            IS_WITHDRAWAL_PAGE
        ) {
            setConfig('cache', 'off');
        }
    }

    public function redirectToLoginPage(bool $isSignin = false): void
    {
        if (!is_ajax()) {
            // ajaxリクエストの場合はリダイレクト先としては扱わない
            $path = rtrim('/' . DIR_OFFSET, '/') . REQUEST_PATH;
            if (pathinfo($path, PATHINFO_EXTENSION) === '') {
                $path = rtrim($path, '/') . '/';
            }
            if (QUERY) {
                $path = $path . '?' . QUERY;
            }
            $phpSession = Session::handle();
            $phpSession->set('acms-login-redirect', $path);
            $phpSession->save();
        }

        $loginUrlData = [
            'bid' => BID,
        ];
        if ($isSignin) {
            $loginUrlData['signin'] = true;
        } else {
            $loginUrlData['login'] = true;
        }
        $loginUrl = acmsLink($loginUrlData);
        redirect($loginUrl);
    }

    /**
     * ログイン判定後の処理
     *
     * @return void
     */
    public function postLoginProcessing(): void
    {
        // ログアウトしていたら、ログイン中に追加されるCookieを削除（ログイン判定には使用しない）
        if (!$this->isLoggedIn()) {
            Login::removeExtraLoggedInCookie();
        }

        //----------------------------------------------
        // ログアウト時のみ表示できるページで、ログイン指定場合
        if ($this->isLoggedIn() && IS_AUTH_SYSTEM_PAGE) {
            httpStatusCode('303 Login With Session');
            header(PROTOCOL . ' ' . httpStatusCode());
            redirect(acmsLink([
                'bid' => BID,
            ]));
        }

        $isAuthRequiredPage = $this->isAuthRequiredPage();

        //--------------
        // session fail
        if ($isAuthRequiredPage && !$this->isLoggedIn()) {
            httpStatusCode('403 Forbidden');
            setConfig('cache', 'off');

            if (config('login_auto_redirect') === 'on') {
                $this->redirectToLoginPage();
            }
        }

        //--------------------------------------------------
        // 読者ユーザーの場合、特定の管理画面以外はアクセスさせない
        if ($this->isLoggedIn() && $isAuthRequiredPage && isSessionSubscriber()) {
            if (!in_array(ADMIN, configArray('subscriber_access_admin_page'), true)) {
                httpStatusCode('403 Forbidden');
                setConfig('cache', 'off');
            }
        }
    }

    /**
     * 現在のページが認証が必要なページかどうか判定
     *
     * @return bool
     */
    public function isAuthRequiredPage(): bool
    {
        if (!!ADMIN && Preview::isPreviewShareAdmin(ADMIN) === false) {
            return true;
        }
        if (IS_REVISION_PREVIEW_PAGE) {
            return true;
        }
        return false;
    }

    /**
     * ログイン中かどうか判定
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        if (!defined('SUID')) {
            return false;
        }
        /** @var int|null $suid */
        $suid = SUID;
        if (is_null($suid)) {
            return false;
        }
        if ($suid < 1) {
            return false;
        }
        return true;
    }

    /**
     * 管理者ログイン用: 現在のIPアドレスからアクセス可能か判断
     *
     * @return bool true: アクセス可能, false: アクセス不可能
     */
    public function canAccessAdminLoginFromCurrentIp(): bool
    {
        return $this->canAccessFromCurrentIp('login_white_hosts', 'login_black_hosts');
    }

    /**
     * 一般サインイン用: 現在のIPアドレスからアクセス可能か判断
     *
     * @return bool true: アクセス可能, false: アクセス不可能
     */
    public function canAccessSigninFromCurrentIp(): bool
    {
        return $this->canAccessFromCurrentIp('signin_white_hosts', 'signin_black_hosts');
    }

    /**
     * IPアドレスチェックの共通ロジック
     *
     * @param string $whiteListName
     * @param string $blackListName
     * @return bool
     */
    private function canAccessFromCurrentIp(string $whiteListName, string $blackListName): bool
    {
        $config = Config::loadBlogConfigSet(BID);

        $isAccessible = true;
        if ($hosts = $config->getArray($whiteListName)) {
            $isAccessible = false;
            foreach ($hosts as $ipband) {
                if (in_ipband(REMOTE_ADDR, $ipband)) {
                    $isAccessible = true;
                    break;
                }
            }
        }
        if ($isAccessible) {
            foreach ($config->getArray($blackListName) as $ipband) {
                if (in_ipband(REMOTE_ADDR, $ipband)) {
                    $isAccessible = false;
                    break;
                }
            }
        }
        return $isAccessible;
    }

    /**
     * 指定されたパスがブログトップからのパスで余分なパスがないかどうかを判定
     *
     * @param int $bid
     * @param string $segment
     * @return boolean
     */
    public function isExactPath(int $bid, string $segment): bool
    {
        $blogCode = ACMS_RAM::blogCode($bid) ?? '';
        return $this->matchExactPath($blogCode, $segment, REQUEST_PATH);
    }

    /**
     * ブログコードとセグメントから生成したパスが、リクエストパスと一致するかを判定
     *
     * @param string $blogCode
     * @param string $segment
     * @param string $requestPath
     * @return boolean
     */
    public function matchExactPath(string $blogCode, string $segment, string $requestPath): bool
    {
        $correctPath = implode('/', [$blogCode, $segment]);
        $correctPath = trim($correctPath, '/');
        $pattern = '/' . $correctPath;

        return $pattern === $requestPath;
    }

    /**
     * 認証前のログインページを表示できるかどうかを判定
     *
     * @param int $bid
     * @param string $segment
     * @return boolean
     */
    public function canLoginPage(int $bid, string $segment): bool
    {
        if (config('deny_blog_login') === 'on' && $bid !== RBID) { // @phpstan-ignore-line
            return false;
        }
        return $this->isValidAuthenticatedPath($bid, $segment);
    }

    /**
     * ログイン済みユーザーのページ（マイページ・管理画面）で正しいパスかどうかを判定
     * deny_blog_loginはログインページの制御なので、ログイン済み操作には適用しない
     *
     * @param int $bid
     * @param string $segment
     * @return boolean
     */
    public function isValidAuthenticatedPath(int $bid, string $segment): bool
    {
        if (config('fix_login_url') === 'on' && !$this->isExactPath($bid, $segment)) {
            return false;
        }
        return true;
    }

    /**
     * 会員サインインが可能かどうかを判定
     *
     * @return bool
     */
    public function canMemberSignin(): bool
    {
        if (defined('ACMS_POST') && ACMS_POST) {
            if (strpos(ACMS_POST, 'Member_Admin') === false) {
                return config('member_login_enable') === 'on';
            }
            return true;
        }
        return config('member_login_enable') === 'on';
    }

    /**
     * 認証系URL時のテンプレートを取得
     *
     * @return string|false
     */
    public function getAuthSystemTemplate()
    {
        /**
         * ログアウト時の管理ユーザー専用認証系画面
         */
        if (
            (IS_SYSTEM_LOGIN_PAGE || IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE || IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE || IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE)
            && !$this->canAccessAdminLoginFromCurrentIp()
        ) {
            // 管理者用の認証ページで、かつ現在のIPアドレスから管理画面へのアクセスが許可されていない → 404でアクセス拒否
            return tplConfig('tpl_404');
        }
        if (IS_SYSTEM_LOGIN_PAGE) {
            // 管理者ログインページ
            return $this->canLoginPage(BID, LOGIN_SEGMENT) ? tplConfig('tpl_login') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE) {
            // 管理者パスワードリセットページ
            return $this->canLoginPage(BID, ADMIN_RESET_PASSWORD_SEGMENT) ? tplConfig('tpl_admin-reset-password') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE) {
            // 管理者パスワードリセット認証ページ
            return $this->canLoginPage(BID, ADMIN_RESET_PASSWORD_AUTH_SEGMENT) ? tplConfig('tpl_admin-reset-password-auth') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE) {
            // 管理者2FA復旧ページ（2FA有効時のみ表示、無効なら404）
            if ($this->canLoginPage(BID, ADMIN_TFA_RECOVERY_SEGMENT) && config('two_factor_auth') === 'on') {
                return tplConfig('tpl_admin-tfa-recovery');
            }
            return tplConfig('tpl_404');
        }

        /**
         * ログアウト時の一般ユーザー専用認証系画面
         */
        if (
            (IS_SYSTEM_SIGNIN_PAGE || IS_SYSTEM_SIGNUP_PAGE || IS_SYSTEM_RESET_PASSWORD_PAGE || IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE || IS_SYSTEM_TFA_RECOVERY_PAGE)
            && !$this->canAccessSigninFromCurrentIp()
        ) {
            // 一般ユーザー用の認証ページで、かつ現在のIPアドレスからサインインが許可されていない → 404でアクセス拒否
            return tplConfig('tpl_404');
        }
        if (IS_SYSTEM_SIGNIN_PAGE) {
            // 一般ユーザーサインインページ（メンバーサインイン許可時のみ表示、それ以外は404）
            return  $this->canMemberSignin() && $this->canLoginPage(BID, SIGNIN_SEGMENT)
                ? tplConfig('tpl_signin') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_SIGNUP_PAGE) {
            // 一般ユーザーサインアップページ（メンバーサインイン許可かつサインアップ有効時のみ表示）
            $canSingnup = $this->canMemberSignin() && config('subscribe') === 'on' && $this->canLoginPage(BID, SIGNUP_SEGMENT);
            return $canSingnup ? tplConfig('tpl_signup') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_RESET_PASSWORD_PAGE) {
            // 一般ユーザーパスワードリセットページ（メンバーサインイン許可時のみ表示、それ以外は404）
            return $this->canMemberSignin() && $this->canLoginPage(BID, RESET_PASSWORD_SEGMENT)
                ? tplConfig('tpl_reset-password') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE) {
            // 一般ユーザーパスワードリセット認証ページ（メンバーサインイン許可時のみ表示、それ以外は404）
            return $this->canMemberSignin() && $this->canLoginPage(BID, RESET_PASSWORD_AUTH_SEGMENT)
                ? tplConfig('tpl_reset-password-auth') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_TFA_RECOVERY_PAGE) {
            // 一般ユーザー2FA復旧ページ（メンバーサインイン許可かつ2FA有効時のみ表示）
            $canTfaRecoveryPage = $this->canMemberSignin() && $this->canLoginPage(BID, TFA_RECOVERY_SEGMENT) && config('two_factor_auth') === 'on';
            return $canTfaRecoveryPage ? tplConfig('tpl_tfa-recovery') : tplConfig('tpl_404');
        }

        /**
         * シークレットブログ・シークレットカテゴリー
         * （ログイン時の認証画面より先に判定し、未ログイン時はサインイン/ログイン画面へ誘導する）
         */
        /** @var int $blogId */
        $blogId = BID;
        /** @var int|null $categoryId */
        $categoryId = defined('CID') ? CID : null;
        if (ACMS_RAM::blogStatus($blogId) === 'secret' || ($categoryId !== null && ACMS_RAM::categoryStatus($categoryId) === 'secret')) {
            if ($this->requiresAuthenticationForSecretContent(ADMIN)) { // リダイレクトループを防ぐ
                // 現在のブログまたはカテゴリーがシークレット設定
                httpStatusCode('403 Forbidden');
                setConfig('cache', 'off');

                if ($this->canAccessSigninFromCurrentIp()) {
                    // 現在のIPアドレスからサインインページへのアクセスが許可されている
                    if (config('redirect_login_page') === 'signin') {
                        // ログインページのリダイレクト設定がサインインページ
                        if ($this->isAuthRequiredPage()) {
                            $this->redirectToLoginPage(isSignin: true);
                        }
                        if ($this->canMemberSignin()) {
                            $this->redirectToLoginPage(isSignin: true);
                        }
                    } else {
                        // ログインページのリダイレクト設定が管理者ログインページ
                        $this->redirectToLoginPage(isSignin: false);
                    }
                }
            }
        }

        /**
         * ログイン時の認証画面
         */
        if (IS_UPDATE_PROFILE_PAGE) {
            // プロフィール更新ページ（ログイン中=SUIDありの場合のみ表示、ログアウト中は404）
            // @phpstan-ignore-next-line
            return $this->isLoggedIn() && $this->isExactPath(BID, PROFILE_UPDATE_SEGMENT) && BID === SBID
                ? tplConfig('tpl_update-profile') : tplConfig('tpl_404');
        }
        if (IS_UPDATE_PASSWORD_PAGE) {
            // パスワード更新ページ（ログイン中=SUIDありの場合のみ表示、ログアウト中は404）
            // @phpstan-ignore-next-line
            return $this->isLoggedIn() && $this->isExactPath(BID, PASSWORD_UPDATE_SEGMENT) && BID === SBID
                ? tplConfig('tpl_update-password') : tplConfig('tpl_404');
        }
        if (IS_UPDATE_EMAIL_PAGE) {
            // メールアドレス更新ページ（ログイン中=SUIDありの場合のみ表示、ログアウト中は404）
            // @phpstan-ignore-next-line
            return $this->isLoggedIn() && $this->isExactPath(BID, EMAIL_UPDATE_SEGMENT) && BID === SBID
                ? tplConfig('tpl_update-email') : tplConfig('tpl_404');
        }
        if (IS_UPDATE_TFA_PAGE) {
            // 2FA更新ページ（ログイン中=SUIDありの場合のみ表示、ログアウト中は404）
            // @phpstan-ignore-next-line
            return $this->isLoggedIn() && $this->isExactPath(BID, TFA_UPDATE_SEGMENT) && BID === SBID
                ? tplConfig('tpl_update-fta') : tplConfig('tpl_404');
        }
        if (IS_WITHDRAWAL_PAGE) {
            // 退会ページ（ログイン中=SUIDありの場合のみ表示、ログアウト中は404）
            // @phpstan-ignore-next-line
            return $this->isLoggedIn() && $this->isExactPath(BID, WITHDRAWAL_SEGMENT) && BID === SBID
                ? tplConfig('tpl_withdrawal') : tplConfig('tpl_404');
        }

        // 認証系URLに該当しない場合はfalse（通常のテンプレート処理へ）
        return false;
    }

    /**
     * 登録ユーザーを検索
     *
     * @param string $email
     * @return int
     */
    public function findUser($email, $bid)
    {
        $SQL = SQL::newSelect('user');
        $SQL->setSelect('user_id');
        $SQL->addWhereOpr('user_mail', $email);
        $SQL->addWhereOpr('user_blog_id', $bid);
        $SQL->setLimit(1);
        return intval(DB::query($SQL->get(dsn()), 'one'));
    }

    /**
     * @param \Field_Validation $user
     * @param bool $subscribeLoginAnywhere
     *
     * @return int $uid
     */
    public function createUser($user, $subscribeLoginAnywhere)
    {
        $uid = DB::query(SQL::nextval('user_id', dsn()), 'seq');
        $auth = config('subscribe_auth', 'subscriber');

        $SQL = SQL::newSelect('user');
        $SQL->setSelect('user_sort');
        $SQL->setOrder('user_sort', 'DESC');
        $SQL->addWhereOpr('user_blog_id', BID);
        $sort = intval(DB::query($SQL->get(dsn()), 'one')) + 1;

        $SQL = SQL::newInsert('user');
        $SQL->addInsert('user_id', $uid);
        $SQL->addInsert('user_sort', $sort);
        $SQL->addInsert('user_blog_id', BID);
        $SQL->addInsert('user_status', 'pseudo');
        $SQL->addInsert('user_name', $user->get('name'));
        $SQL->addInsert('user_mail', $user->get('mail'));
        $SQL->addInsert('user_mail_mobile', $user->get('mail_mobile'));
        if ($user->get('mail_magazine') === 'off') {
            $SQL->addInsert('user_mail_magazine', 'off');
        }
        if ($user->get('mail_mobile_magazine') === 'off') {
            $SQL->addInsert('user_mail_mobile_magazine', 'off');
        }
        $SQL->addInsert('user_code', $user->get('code'));
        $SQL->addInsert('user_url', $user->get('url'));
        $SQL->addInsert('user_auth', $auth);
        $SQL->addInsert('user_indexing', 'on');
        $SQL->addInsert('user_pass', acmsUserPasswordHash($user->get('pass')));
        $SQL->addInsert('user_pass_generation', PASSWORD_ALGORITHM_GENERATION);
        if ($subscribeLoginAnywhere) {
            $SQL->addInsert('user_login_anywhere', 'on');
        }
        $SQL->addInsert('user_generated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        DB::query($SQL->get(dsn()), 'exec');

        return $uid;
    }

    /**
     * 新しいユーザーをOAuth認証から作成
     *
     * @param array $data OAuth認証データ
     */
    public function addUserFromOauth($data): int
    {
        $SQL = SQL::newSelect('user');
        $SQL->setSelect('user_id');
        $SQL->addWhereOpr('user_mail', $data['email']);
        if (DB::query($SQL->get(dsn()), 'one')) {
            throw new \RuntimeException('すでに登録済みのメールアドレスです');
        }

        $SQL = SQL::newSelect('user');
        $SQL->setSelect('user_sort');
        $SQL->setOrder('user_sort', 'DESC');
        $SQL->addWhereOpr('user_blog_id', $data['bid']);
        $sort = intval(DB::query($SQL->get(dsn()), 'one')) + 1;
        $uid = DB::query(SQL::nextval('user_id', dsn()), 'seq');

        $SQL = SQL::newInsert('user');
        $SQL->addInsert('user_id', $uid);
        $SQL->addInsert('user_sort', $sort);
        $SQL->addInsert('user_generated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addInsert('user_blog_id', $data['bid']);
        $SQL->addInsert('user_code', $data['code']);
        $SQL->addInsert('user_status', config('subscribe_init_status', 'open'));
        $SQL->addInsert('user_name', $data['name']);
        $SQL->addInsert('user_pass', Common::genPass(16));
        $SQL->addInsert($data['oauthType'], $data['sub']);
        $SQL->addInsert('user_mail', $data['email']);
        $SQL->addInsert('user_mail_magazine', 'off');
        $SQL->addInsert('user_mail_mobile_magazine', 'off');
        $SQL->addInsert('user_icon', $data['icon']);
        $SQL->addInsert('user_auth', config('subscribe_auth', 'subscriber'));
        $SQL->addInsert('user_indexing', 'on');
        $SQL->addInsert('user_login_anywhere', 'off');
        $SQL->addInsert('user_login_expire', '9999-12-31');
        $SQL->addInsert('user_updated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        DB::query($SQL->get(dsn()), 'exec');

        return $uid;
    }

    /**
     * @param int $uid
     * @param \Field_Validation $user
     * @param bool $subscribeLoginAnywhere
     */
    public function updateUser($uid, $user, $subscribeLoginAnywhere = false)
    {
        $SQL = SQL::newUpdate('user');
        $SQL->addUpdate('user_name', $user->get('name'));
        $SQL->addUpdate('user_mail_mobile', $user->get('mail_mobile'));
        $SQL->addUpdate('user_code', $user->get('code'));
        $SQL->addUpdate('user_url', $user->get('url'));
        $SQL->addUpdate('user_pass', acmsUserPasswordHash($user->get('pass')));
        $SQL->addUpdate('user_generated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addWhereOpr('user_id', $uid);
        if ($subscribeLoginAnywhere) {
            $SQL->addUpdate('user_login_anywhere', 'on');
        }
        DB::query($SQL->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);
    }

    /**
     * @param array $context
     * @param int $lifetime
     * @return string
     */
    public function createTimedLinkParams($context, $lifetime)
    {
        $salt = Common::genPass(32); // 事前共有鍵
        $context['expire'] = REQUEST_TIME + $lifetime; // 有効期限
        $context = acmsSerialize($context);
        $prk = hash_hmac('sha256', Common::getCurrentSalt(), $salt);
        $derivedKey = hash_hmac('sha256', $prk, $context);
        $params = http_build_query([
            'key' => $derivedKey,
            'salt' => $salt,
            'context' => $context,
        ]);
        return $params;
    }

    /**
     * @param string $key
     * @param string $salt
     * @param string $context
     * @return array
     * @throws BadRequestException
     * @throws ExpiredException
     */
    public function validateTimedLinkParams($key, $salt, $context)
    {
        $prk = hash_hmac('sha256', Common::getCurrentSalt(), $salt);
        $prk2 = hash_hmac('sha256', Common::getPreviousSalt(), $salt);
        $derivedKey = hash_hmac('sha256', $prk, $context);
        $derivedKey2 = hash_hmac('sha256', $prk2, $context);
        if (!hash_equals($key, $derivedKey) && !hash_equals($key, $derivedKey2)) {
            throw new BadRequestException('Bad request.');
        }
        $context = acmsUnserialize($context);
        if (!isset($context['expire'])) {
            throw new BadRequestException('Bad request.');
        }
        if (REQUEST_TIME > $context['expire']) {
            throw new ExpiredException('Expired.');
        }
        return $context;
    }

    /**
     * ユーザーを有効化
     *
     * @param int $uid
     * @return bool
     */
    public function subscriberActivation($uid)
    {
        // enable account
        $sql = SQL::newUpdate('user');
        $sql->addUpdate('user_status', config('subscribe_init_status', 'open'));
        $sql->addUpdate('user_login_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $sql->addWhereOpr('user_id', $uid);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);

        return true;
    }

    /**
     * 一般サインインできる権限を取得
     *
     * @return string[]
     */
    public function getSinginAuth()
    {
        if (config('signin_page_auth') === 'contributor') {
            return ['contributor', 'subscriber'];
        }
        return ['subscriber'];
    }

    /**
     * 管理ログインできる権限を取得
     *
     * @return string[]
     */
    public function getAdminLoginAuth()
    {
        if (config('signin_page_auth') === 'contributor') {
            return ['administrator', 'editor'];
        }
        return ['administrator', 'editor', 'contributor'];
    }

    /**
     * ログアウト時のリダイレクト先URLを取得
     *
     * @param int $userId
     * @return string
     */
    public function getLogoutRedirectUrl(int $userId): string
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('Invalid user id.');
        }

        $logoutRedirectPage = config('logout_redirect_page', 'top');

        if ($logoutRedirectPage === 'top') {
            return acmsLink([
                'bid' => BID,
            ]);
        }

        if ($logoutRedirectPage === 'auth') {
            $auth = ACMS_RAM::userAuth($userId);
            if (in_array($auth, $this->getSinginAuth(), true)) {
                return acmsLink([
                    'bid' => BID,
                    'signin' => true,
                ]);
            };

            return acmsLink([
                'bid' => BID,
                'login' => true,
            ]);
        }

        return acmsLink([
            'bid' => BID,
        ]);
    }

    /**
     * ログイン許可端末のチェック
     *
     * @param array $user
     * @return bool
     */
    public function checkAllowedDevice(array $user): bool
    {
        if (
            1
            && $user['user_auth'] !== 'administrator'
            && isset($user['user_login_terminal_restriction'])
            && $user['user_login_terminal_restriction'] === 'on'
        ) {
            $cookie =& Field::singleton('cookie');
            if ($cookie->get('acms_config_login_terminal_restriction') !== sha1('permission' . UA)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 画像URIから画像を生成
     *
     * @param string $imageUri 画像URL
     * @return string 画像パス
     */
    public function userIconFromUri(string $imageUri): string
    {
        $imgPath = '';
        try {
            $rsrc = file_get_contents($imageUri);
            $imgPath = PublicStorage::archivesDir() . uniqueString() . '.jpg';
            PublicStorage::makeDirectory(dirname(ARCHIVES_DIR . $imgPath));
            if ($rsrc) {
                PublicStorage::put(ARCHIVES_DIR . $imgPath, $rsrc);
                $resizePath = PublicStorage::archivesDir() . 'square64-' . uniqueString() . '.jpg';
                Image::copyImage(ARCHIVES_DIR . $imgPath, ARCHIVES_DIR . $resizePath, 64, 64, 64);
                PublicStorage::remove(ARCHIVES_DIR . $imgPath);
                $imgPath = $resizePath;
            }
        } catch (\Exception $e) {
            // ToDo: ログ仕込み
        }
        return $imgPath;
    }

    /**
     * ログインリダイレクト処理
     *
     * @param array $user
     * @param ?string $fieldRedirectUrl
     */
    public function loginRedirect(array $user, $fieldRedirectUrl = null)
    {
        $redirectBid = BID;
        $bid = intval($user['user_blog_id']);
        if (('on' === $user['user_login_anywhere'] || roleAvailableUser()) && !isBlogAncestor(BID, $bid, true)) {
            $redirectBid = $bid;
        }

        // セッションに保存されたリダイレクト先
        $phpSession = Session::handle();
        $sessionRedirectUrl = $phpSession->get('acms-login-redirect');
        if ($sessionRedirectUrl) {
            $phpSession->delete('acms-login-redirect');
            $phpSession->save();
            redirect($sessionRedirectUrl);
        }

        // リダイレクト指定（パス指定であること）
        if ($fieldRedirectUrl && !preg_match('@^https?://@', $fieldRedirectUrl)) {
            if (preg_match('/^(.[^?]+)(.*)$/', $fieldRedirectUrl, $matches)) {
                $path = $matches[1];
                $query_hash = $matches[2];
            } else {
                $path = $fieldRedirectUrl;
                $query_hash = '';
            }
            $path = ltrim($path, '/');
            $url = (SSL_ENABLE ? 'https' : 'http') . '://'
                . HTTP_HOST . '/'
                . $path
                . (!empty($query_hash) ? $query_hash : '');

            // WHATWG パーサで host を ASCII IDN に正規化して抽出することで、
            // 日本語パスを含む同一ドメイン URL でもリダイレクトが正常に動作し、
            // かつパーサのレニエンシーを利用したオープンリダイレクト攻撃を防ぐ
            $redirect_host = WhatWgUrl::parse($url)?->getAsciiHost();
            if (strtolower(HTTP_HOST) === $redirect_host) {
                $url = htmlspecialchars_decode($url);
                redirect($url);
            }
        }

        // 現在のURLにログイン
        if (config('login_auto_redirect') === 'on') {
            $path = rtrim(DIR_OFFSET, '/') . REQUEST_PATH;
            $path = preg_replace('@' . LOGIN_SEGMENT . '$@', '', $path);
            $path = preg_replace('@' . SIGNIN_SEGMENT . '$@', '', $path);
            $query_hash = $_SERVER['QUERY_STRING'];
            $path = ltrim($path ?? '', '/');
            $url = (SSL_ENABLE ? 'https' : 'http') . '://'
                . HTTP_HOST . '/'
                . $path
                . (!empty($query_hash) ? '?' . $query_hash : '');
            redirect($url);
        }

        // 管理ページ内にリダイレクト
        $admin = config('login_admin_path');
        if ($admin && $user['user_auth'] !== 'subscriber') {
            $url = acmsLink([
                'protocol' => SSL_ENABLE ? 'https' : 'http',
                'bid' => $redirectBid,
                'admin' => $admin,
            ], false);
            redirect($url);
        }

        // 通常のブログのトップページにリダイレクト
        $url = acmsLink([
            'protocol' => SSL_ENABLE ? 'https' : 'http',
            'bid' => $redirectBid,
            'query' => [],
        ]);
        redirect($url);
    }

    /**
     * ユーザーアイコンのサイズを変更
     *
     * @param string $squarePath
     * @return string
     */
    public function resizeUserIcon(string $squarePath): ?string
    {
        if (empty($squarePath)) {
            return null;
        }
        $path = normalSizeImagePath($squarePath);
        $size = intval(config('user_icon_size', 255));
        $iconPath = trim(dirname($path), '/') . '/square-' . LocalStorage::mbBasename($path);
        Image::copyImage(ARCHIVES_DIR . $squarePath, ARCHIVES_DIR . $iconPath, $size, $size, $size);

        return $iconPath;
    }

    /**
     * ログインしている場合、権限のCookieを追加
     *
     * @param int $uid
     * @return void
     */
    public function addExtraLoggedInCookie(int $uid): void
    {
        if (config('extra_logged_in_cookie') !== 'on') {
            return;
        }
        $name = config('extra_logged_in_cookie_name', 'acms-logged-in');
        acmsSetCookie($name, ACMS_RAM::userAuth($uid));
    }

    /**
     * ログインしていない時、追加されるCookieを削除
     *
     * @return void
     */
    public function removeExtraLoggedInCookie(): void
    {
        if (config('extra_logged_in_cookie') !== 'on') {
            return;
        }
        $name = config('extra_logged_in_cookie_name', 'acms-logged-in');
        $cookie = Application::getCookieParameter();
        if ($cookie->get($name)) {
            acmsSetCookie($name, null, REQUEST_TIME - 1);
        }
    }

    /**
     * ログインセッションに付随するクライアント情報を更新
     *
     * @param int $uid
     * @return void
     */
    public function updateSessionClientInfo(int $uid): void
    {
        $session = Session::handle();
        $sessionId = $session->getSessionId();

        $sql = SQL::newDelete('user_session');
        $sql->addWhereOpr('user_session_uid', $uid);
        if ($host = getCookieHost()) {
            $sql->addWhereOpr('user_session_host', $host);
        }
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newInsert('user_session');
        $sql->addInsert('user_session_uid', $uid);
        if ($host = getCookieHost()) {
            $sql->addInsert('user_session_host', $host);
        }
        $sql->addInsert('user_session_address', REMOTE_ADDR);
        $sql->addInsert('user_session_id', $sessionId);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * シークレットコンテンツへのアクセスに認証が必要か判定
     *
     * シークレットブログ/カテゴリーにアクセスする際、
     * 以下のいずれかに該当する場合は認証（ログイン/サインイン）が必要:
     * - プレビュー共有URLではない
     * - ログインしていない
     * - 認証系ページの場合
     * - 拡張機能による特殊な認証をおこなるためのページの場合
     *
     * @param string $admin 管理画面パス
     * @return bool true: 認証が必要, false: アクセス可能（認証不要）
     */
    private function requiresAuthenticationForSecretContent(string $admin): bool
    {
        if (Preview::isPreviewShareAdmin($admin)) {
            // プレビュー共有URLの場合は認証不要
            return false;
        }

        if ($this->isLoggedIn()) {
            // ログイン済みの場合は認証不要
            return false;
        }
        if (defined('IS_AUTH_SYSTEM_PAGE') && IS_AUTH_SYSTEM_PAGE) {
            // 認証系ページの場合は認証不要
            return false;
        }

        if (defined('IS_OTHER_LOGIN') && IS_OTHER_LOGIN) {
            // 拡張機能による特殊な認証をおこなるためのページの場合は認証不要
            return false;
        }

        // 上記のいずれにも該当しない場合は認証が必要
        return true;
    }
}
