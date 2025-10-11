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

    /**
     * ログイン判定後の処理
     *
     * @return void
     */
    public function postLoginProcessing(): void
    {
        // ログアウトしていたら、ログイン中に追加されるCookieを削除（ログイン判定には使用しない）
        if (!SUID) {
            Login::removeExtraLoggedInCookie();
        }

        //----------------------------------------------
        // ログアウト時のみ表示できるページで、ログイン指定場合
        if (SUID && IS_AUTH_SYSTEM_PAGE) {
            httpStatusCode('303 Login With Session');
            header(PROTOCOL . ' ' . httpStatusCode());
            redirect(acmsLink([
                'bid' => BID,
            ]));
        }

        $isAuthRequiredPage = $this->isAuthRequiredPage();

        //--------------
        // session fail
        if ($isAuthRequiredPage && !SUID) {
            httpStatusCode('403 Forbidden');
            setConfig('cache', 'off');

            if (config('login_auto_redirect') === 'on') {
                $path = rtrim('/' . DIR_OFFSET, '/') . REQUEST_PATH;
                if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
                    $path = rtrim($path, '/') . '/';
                }
                if (QUERY) {
                    $path = $path . '?' . QUERY;
                }
                $phpSession = Session::handle();
                $phpSession->set('acms-login-redirect', $path);
                $phpSession->save();

                $signinPageLink = acmsLink([
                    'bid' => BID,
                    'login' => true,
                ]);
                redirect($signinPageLink);
            }
        }

        //--------------------------------------------------
        // 読者ユーザーの場合、特定の管理画面以外はアクセスさせない
        if (SUID && $isAuthRequiredPage && isSessionSubscriber()) {
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
     * 現在のセッションがログイン状態かどうか判定
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        if (Preview::isPreviewMode()) {
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
     * ホワイトリストとブラックリストを確認して、認証できるアクセスか判断
     *
     * @param bool $isAdmin
     * @return bool
     */
    public function accessRestricted(bool $isAdmin = true): bool
    {
        $config = Config::loadBlogConfigSet(BID);
        $whiteListName = $isAdmin ? 'login_white_hosts' : 'signin_white_hosts';
        $blackListName = $isAdmin ? 'login_black_hosts' : 'signin_black_hosts';

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
     * 会員サインインが可能かどうかを判定
     *
     * @return bool
     */
    public function canMemberSignin(): bool
    {
        if (ACMS_POST) {
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
     * @return string|bool
     */
    public function getAuthSystemTemplate()
    {
        /**
         * ログアウト時の管理ユーザー専用認証系画面
         */
        if (
            (IS_SYSTEM_LOGIN_PAGE || IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE || IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE || IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE)
            && !$this->accessRestricted(true)
        ) {
            return tplConfig('tpl_404');
        }
        if (IS_SYSTEM_LOGIN_PAGE) {
            return tplConfig('tpl_login');
        }
        if (IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE) {
            return tplConfig('tpl_admin-reset-password');
        }
        if (IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE) {
            return tplConfig('tpl_admin-reset-password-auth');
        }
        if (IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE) {
            return config('two_factor_auth') === 'on' ? tplConfig('tpl_admin-tfa-recovery') : tplConfig('tpl_404');
        }

        /**
         * ログアウト時の一般ユーザー専用認証系画面
         */
        if (
            (IS_SYSTEM_SIGNIN_PAGE || IS_SYSTEM_SIGNUP_PAGE || IS_SYSTEM_RESET_PASSWORD_PAGE || IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE || IS_SYSTEM_TFA_RECOVERY_PAGE)
            && !$this->accessRestricted(false)
        ) {
            return tplConfig('tpl_404');
        }
        if (IS_SYSTEM_SIGNIN_PAGE) {
            return  $this->canMemberSignin() ? tplConfig('tpl_signin') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_SIGNUP_PAGE) {
            $canSingnup = $this->canMemberSignin() && config('subscribe') === 'on';
            return $canSingnup ? tplConfig('tpl_signup') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_RESET_PASSWORD_PAGE) {
            return $this->canMemberSignin() ? tplConfig('tpl_reset-password') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE) {
            return $this->canMemberSignin() ? tplConfig('tpl_reset-password-auth') : tplConfig('tpl_404');
        }
        if (IS_SYSTEM_TFA_RECOVERY_PAGE) {
            return $this->canMemberSignin() && config('two_factor_auth') === 'on' ? tplConfig('tpl_tfa-recovery') : tplConfig('tpl_404');
        }

        /**
         * ログイン時の認証画面
         */
        if (IS_UPDATE_PROFILE_PAGE) {
            return !!SUID ? tplConfig('tpl_update-profile') : tplConfig('tpl_404');
        }
        if (IS_UPDATE_PASSWORD_PAGE) {
            return !!SUID ? tplConfig('tpl_update-password') : tplConfig('tpl_404');
        }
        if (IS_UPDATE_EMAIL_PAGE) {
            return !!SUID ? tplConfig('tpl_update-email') : tplConfig('tpl_404');
        }
        if (IS_UPDATE_TFA_PAGE) {
            return !!SUID ? tplConfig('tpl_update-fta') : tplConfig('tpl_404');
        }
        if (IS_WITHDRAWAL_PAGE) {
            return !!SUID ? tplConfig('tpl_withdrawal') : tplConfig('tpl_404');
        }

        /**
         * シークレットブログ・シークレットエントリー
         */
        if (ACMS_RAM::blogStatus(BID) === 'secret' || (CID && ACMS_RAM::categoryStatus(CID) === 'secret')) {
            // @phpstan-ignore-next-line
            if (!Preview::isPreviewShareAdmin(ADMIN) && !SUID && (!defined('IS_OTHER_LOGIN') || !IS_OTHER_LOGIN)) {
                if ($this->accessRestricted(false)) {
                    if (config('redirect_login_page') === 'signin') {
                        if ($this->isAuthRequiredPage()) {
                            return tplConfig('tpl_signin');
                        }
                        if ($this->canMemberSignin()) {
                            return tplConfig('tpl_signin');
                        }
                        return tplConfig('tpl_404');
                    } else {
                        return tplConfig('tpl_login');
                    }
                }
                return tplConfig('tpl_404');
            }
        }
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
        if (
            1
            && ('on' == $user['user_login_anywhere'] || roleAvailableUser())
            && !isBlogAncestor(BID, $bid, true)
        ) {
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

            $redirect_host = parse_url($url, PHP_URL_HOST);
            if (HTTP_HOST === $redirect_host) {
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
            'protocol' => (SSL_ENABLE and ('on' == config('login_ssl'))) ? 'https' : 'http',
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
}
