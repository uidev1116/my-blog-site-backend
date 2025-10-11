<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Member_Signup_Confirm extends ACMS_POST_Member
{
    /**
     * @var boolean
     */
    protected $subscribeLoginAnywhere = false;

    /**
     * 会員登録の入力確認
     *
     * @return Field_Validation
     * @throws Exception
     */
    function post(): Field_Validation
    {
        $field = $this->extract('field', new ACMS_Validator());
        $user = $this->extract('user');
        if ('on' === config('subscribe_login_anywhere')) {
            $this->subscribeLoginAnywhere = true;
        }
        $this->validate($user);

        if (!$this->Post->isValidAll()) {
            $this->log($user, $field);
        }

        return $this->Post;
    }

    /**
     * ログをとる
     *
     * @param Field_Validation $user
     * @param Field_Validation $field
     * @return void
     */
    protected function log(Field_Validation $user, Field_Validation $field): void
    {
        $reasons = [];
        if (!$user->isValid('name', 'required')) {
            $reasons[] = '名前が指定されていません';
        }
        if (!$user->isValid('mail', 'required')) {
            $reasons[] = 'メールアドレスが指定されていません';
        }
        if (!$user->isValid('mail', 'email')) {
            $reasons[] = '不正なメールアドレスです';
        }
        if (!$user->isValid('pass', 'required')) {
            $reasons[] = 'パスワードが指定されていません';
        }
        if (!$user->isValid('pass', 'password')) {
            $reasons[] = 'パスワードのフォーマットが間違っています';
        }
        if (!$user->isValid('retype_pass', 'equalTo')) {
            $reasons[] = '入力されたパスワードが一致しません';
        }
        if (!$user->isValid('code', 'regex')) {
            $reasons[] = '不正なユーザーコードが指定されました';
        }
        if (!$user->isValid('mail_mobile', 'email')) {
            $reasons[] = '不正な形のモバイル用のメールアドレスです';
        }
        if (!$user->isValid('url', 'url')) {
            $reasons[] = '不正なURLが指定されました';
        }
        if (!$user->isValid('login', 'isOperable')) {
            $reasons[] = 'ログイン済み、もしくは会員機能が有効ではありません';
        }
        if (!$user->isValid('mail', 'doubleMail')) {
            $reasons[] = 'すでに存在するメールアドレスです';
        }
        if (!$user->isValid('mail', 'pseudoUserExists')) {
            $reasons[] = '仮登録ステータスのユーザーで利用されているメールアドレスです';
        }
        if (!$user->isValid('code', 'doubleCode')) {
            $reasons[] = 'すでに存在するユーザーコードです';
        }
        AcmsLogger::info('会員登録申請のバリデートに失敗しました', [
            'reasons' => $reasons,
            'user' => $user->_aryField,
            'field' => $field,
        ]);
    }

    /**
     * 会員情報をバリデート
     *
     * @param Field_Validation $user
     * @return void
     */
    protected function validate(Field_Validation $user): void
    {
        $user->reset();
        $user->setMethod('name', 'required');
        $user->setMethod('mail', 'required');
        $user->setMethod('mail', 'email');
        $user->setMethod('code', 'regex', REGEX_VALID_ID);
        $user->setMethod('mail_mobile', 'email');
        $user->setMethod('url', 'url');
        if (SUID || !Login::canMemberSignin() || config('subscribe') !== 'on') { // @phpstan-ignore-line
            $user->setMethod('login', 'isOperable', false);
            httpStatusCode('403 Forbidden');
        }

        if (config('email-auth-signin') !== 'on') {
            // パスワードなしのメール認証によるサインイン設定
            $user->setMethod('pass', 'required');
            $user->setMethod('pass', 'password');
            $user->setMethod('retype_pass', 'equalTo', 'pass');
        }

        if ($user->get('mail')) {
            $user->setMethod('mail', 'doubleMail', $this->doubleMail($user) === false);
            $user->setMethod('mail', 'pseudoUserExists', $this->pseudoUserExists($user) === false);
        }
        if ($user->get('code')) {
            $user->setMethod('code', 'doubleCode', $this->doubleCode($user) === false);
        }

        $user->validate(new ACMS_Validator());
    }

    /**
     * @param Field_Validation $user
     * @return bool
     */
    protected function doubleMail(Field_Validation $user): bool
    {
        $sql = SQL::newSelect('user');
        $sql->setSelect('user_id');
        $sql->addWhereOpr('user_mail', $user->get('mail'));
        $sql->addWhereOpr('user_status', 'pseudo', '<>');
        if ($this->subscribeLoginAnywhere === false) {
            $AnywhereOrBid = SQL::newWhere();
            $AnywhereOrBid->addWhereOpr('user_login_anywhere', 'on', '=', 'OR');
            $AnywhereOrBid->addWhereOpr('user_blog_id', BID, '=', 'OR');
            $sql->addWhere($AnywhereOrBid);
        }
        $sql->setLimit(1);
        return !!DB::query($sql->get(dsn()), 'one');
    }

    /**
     * 同一メールアドレスかつどこでもログインが有効な仮登録ユーザーが存在するか
     * （メールアドレス認証URLを更新するため、現在のブログは除外）
     *
     * @param Field_Validation $user
     * @return bool
     */
    protected function pseudoUserExists(Field_Validation $user): bool
    {
        $sql = SQL::newSelect('user');
        $sql->setSelect('user_id');
        $sql->addWhereOpr('user_mail', $user->get('mail'));
        $sql->addWhereOpr('user_status', 'pseudo');
        $sql->addWhereOpr('user_login_anywhere', 'on', '=');
        $sql->addWhereOpr('user_blog_id', BID, '<>');
        $sql->setLimit(1);

        return !!DB::query($sql->get(dsn()), 'one');
    }

    /**
     * @param Field_Validation $user
     * @return bool
     */
    protected function doubleCode(Field_Validation $user): bool
    {
        $sql = SQL::newSelect('user');
        $sql->setSelect('user_id');
        $sql->addWhereOpr('user_code', $user->get('code'));
        $sql->addWhereOpr('user_mail', $user->get('mail'), '<>');
        if ($this->subscribeLoginAnywhere === false) {
            $AnywhereOrBid = SQL::newWhere();
            $AnywhereOrBid->addWhereOpr('user_login_anywhere', 'on', '=', 'OR');
            $AnywhereOrBid->addWhereOpr('user_blog_id', BID, '=', 'OR');
            $sql->addWhere($AnywhereOrBid);
        }
        $sql->setLimit(1);
        return !!DB::query($sql->get(dsn()), 'one');
    }
}
