<?php

use Acms\Services\Facades\Login;

class ACMS_POST_User_Update extends ACMS_POST_User
{
    protected $user;
    protected $field;
    protected $preUser;
    protected $preField;
    protected $deleteField;

    private $diff = false;

    public function post()
    {
        $this->user = $this->extract('user');
        $this->preUser = loadUser(UID);
        $this->preField = loadUserField(UID);

        $this->validate();
        $this->field();

        if ($this->Post->isValidAll()) {
            $this->save();

            AcmsLogger::info('ユーザー「' . $this->user->get('name') . '」の情報を更新しました', [
                'uid' => UID,
                'user' => $this->user->_aryField,
                'field' => $this->field->_aryField,
            ]);

            Webhook::call(BID, 'user', ['user:updated'], UID);

            if ((editionIsProfessional() || editionIsEnterprise())) {
                $this->old();
                if ($this->diff) {
                    $this->send();
                }
            }
        } else {
            AcmsLogger::info('ユーザー「' . $this->preUser->get('name') . '」の情報更新に失敗しました', [
                'uid' => UID,
                'user' => $this->user->_aryV,
                'field' => $this->field->_aryV,
            ]);
        }
        return $this->Post;
    }

    protected function old()
    {
        $targetColumn = ['name', 'code', 'mail', 'mail_mobile', 'url'];

        foreach ($this->field->listFields() as $key) {
            if ($this->field->get($key) !== $this->preField->get($key)) {
                $this->field->setField('old_' . $key, $this->preField->get($key));
                $this->diff = true;
            }
        }
        foreach ($targetColumn as $column) {
            if ($this->user->get($column) !== $this->preUser->get($column)) {
                $this->field->setField('old_' . $column, $this->preUser->get($column));
                $this->diff = true;
            }
            $this->field->setField($column, $this->user->get($column));
        }
    }

    protected function send()
    {
        if (config('mail_update_user_enable') === 'on') {
            $this->mail(
                config('mail_update_user_tpl_subject'),
                config('mail_update_user_tpl_body'),
                config('mail_update_user_tpl_body_html'),
                $this->user->get('mail'),
                configArray('mail_update_user_from'),
                configArray('mail_update_user_bcc')
            );
        }

        if (config('mail_update_user_admin_enable') === 'on') {
            $this->mail(
                config('mail_update_user_admin_tpl_subject'),
                config('mail_update_user_admin_tpl_body'),
                config('mail_update_user_admin_tpl_body_html'),
                configArray('mail_update_user_admin_to'),
                $this->user->get('mail'),
                configArray('mail_update_user_admin_bcc')
            );
        }
    }

    protected function mail($subject, $body, $html, $to, $from, $bcc)
    {
        if (
            1
            && $to
            && $subjectTpl = findTemplate($subject)
            && $bodyTpl = findTemplate($body)
        ) {
            $this->field->set('uid', UID);
            $subject = Common::getMailTxt($subjectTpl, $this->field);
            $body = Common::getMailTxt($bodyTpl, $this->field);

            $to = is_array($to) ? implode(', ', $to) : $to;
            $from = is_array($from) ? implode(', ', $from) : $from;
            $bcc = is_array($bcc) ? implode(', ', $bcc) : $bcc;

            try {
                $mailer = Mailer::init();
                $mailer = $mailer->setFrom($from)
                    ->setTo($to)
                    ->setBcc($bcc)
                    ->setSubject($subject)
                    ->setBody($body);

                if ($bodyHtmlTpl = findTemplate($html)) {
                    $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $this->field);
                    $mailer = $mailer->setHtml($bodyHtml);
                }
                $mailer->send();
            } catch (Exception $e) {
                throw $e;
            }
        }
    }

    protected function validate()
    {
        $validator = new ACMS_Validator_User();

        $this->user->setMethod('status', 'in', ['open', 'close']);
        $this->user->setMethod('name', 'required');
        $this->user->setMethod('name', 'maxlength', '255');
        $this->user->setMethod('mail', 'required');
        $this->user->setMethod('mail', 'maxlength', '255');
        $this->user->setMethod('mail', 'email');
        $this->user->setMethod('mail', 'doubleMail', UID);
        $this->user->setMethod('code', 'maxlength', '64');
        $this->user->setMethod('code', 'doubleCode', UID);
        $this->user->setMethod('mail_magazine', 'in', ['on', 'off']);
        $this->user->setMethod('mail_mobile_magazine', 'in', ['on', 'off']);
        $this->user->setMethod('mail_mobile', 'maxlength', '255');
        $this->user->setMethod('mail_mobile', 'email');
        $this->user->setMethod('url', 'maxlength', '255');
        $this->user->setMethod('url', 'url');
        $this->user->setMethod('pass', 'password');
        $this->user->setMethod('auth', 'in', ['administrator', 'editor', 'contributor', 'subscriber']);
        $this->user->setMethod('indexing', 'in', ['on', 'off']);
        $this->user->setMethod('mode', 'in', ['debug', 'benchmark']);
        if ($this->user->get('code') !== '') {
            $this->user->setMethod('code', 'string', isValidCode($this->user->get('code')));
        }

        // 現在、読者かつ読者以外に変更しようとしているときだけ、ユーザー数の制限チェックを行う
        if (
            1
            && 'subscriber' != $this->user->get('auth')
            && '' != $this->user->get('auth')
            && 'subscriber' == ACMS_RAM::userAuth(UID)
        ) {
            $this->user->setMethod('user', 'limit', $this->isLimit());
        }

        $this->user->setMethod('login_expire', 'regex', '@\d\d\d\d-\d\d-\d\d@');
        $this->user->setMethod('login_anywhere', 'in', ['on', 'off']);
        $this->user->setMethod('login_anywhere', 'anywhere', !(1
            && 'on' == $this->user->get('login_anywhere')
            && !(1
                && (empty($this->user->get('code')) || $validator->doubleCode($this->user->get('code'), ['uid' => UID]))
                && $validator->doubleMail($this->user->get('mail'), ['uid' => UID])
            )
        ));
        $this->user->setMethod('user', 'operable', $this->isOperable());
        $this->user->validate($validator);
    }

    /**
     * ユーザーの更新権限があるかどうか
     *
     * @return bool
     **/
    protected function isOperable(): bool
    {
        if (empty(UID)) {
            return false;
        }

        if (!Login::isLoggedIn()) {
            return false;
        }

        if (!IS_LICENSED) {
            return false;
        }

        if (sessionWithAdministration()) {
            return true;
        }

        if (UID === SUID) {
            return true;
        }

        return false;
    }

    protected function field()
    {
        $this->deleteField = new Field();
        $this->field = $this->extract('field', new ACMS_Validator(), $deleteField);
    }

    protected function save()
    {
        $this->updateUser();
        $this->updateField();
        $this->updateFulltext();
    }

    protected function updateUser()
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newUpdate('user');
        $SQL->addUpdate('user_name', $this->user->get('name'));
        $SQL->addUpdate('user_code', strval($this->user->get('code')));
        $SQL->addUpdate('user_mail', $this->user->get('mail'));
        $SQL->addUpdate('user_mail_mobile', strval($this->user->get('mail_mobile')));
        $SQL->addUpdate('user_url', strval($this->user->get('url')));
        $SQL->addUpdate('user_updated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addUpdate('user_locale', $this->user->get('locale'));
        $SQL->addUpdate('user_mail_magazine', $this->user->get('mail_magazine'));
        $SQL->addUpdate('user_mail_mobile_magazine', $this->user->get('mail_mobile_magazine'));

        if (sessionWithAdministration()) {
            if (!!$this->user->get('status')) {
                $SQL->addUpdate('user_status', $this->user->get('status'));
            }
            if (!!$this->user->get('auth')) {
                $SQL->addUpdate('user_auth', $this->user->get('auth'));
            }
            if (!!$this->user->get('indexing')) {
                $SQL->addUpdate('user_indexing', $this->user->get('indexing'));
            }
            $SQL->addUpdate('user_mode', $this->user->get('mode'));
            if (
                1
                and $this->user->get('login_anywhere')
                and SBID == RBID
            ) {
                $SQL->addUpdate('user_login_anywhere', $this->user->get('login_anywhere'));
            }
            if (
                1
                and $this->user->get('global_auth') === 'on'
                and SBID == RBID
            ) {
                $SQL->addUpdate('user_global_auth', 'on');
            } else {
                $SQL->addUpdate('user_global_auth', 'off');
            }
            if ($this->user->get('login_terminal_restriction')) {
                $SQL->addUpdate('user_login_terminal_restriction', $this->user->get('login_terminal_restriction'));
            }
            if (!!$this->user->get('login_expire')) {
                $SQL->addUpdate('user_login_expire', $this->user->get('login_expire'));
            }
        }
        if ($this->user->get('pass')) {
            $SQL->addUpdate('user_pass', acmsUserPasswordHash($this->user->get('pass')));
            $SQL->addUpdate('user_pass_generation', PASSWORD_ALGORITHM_GENERATION);
        }
        if ($iconPath = Login::resizeUserIcon($this->user->get('icon@squarePath'))) {
            $SQL->addUpdate('user_icon', $iconPath);
            $this->user->set('icon', $iconPath);
        }
        $SQL->addWhereOpr('user_id', UID);
        $SQL->addWhereOpr('user_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        ACMS_RAM::user(UID, null);
        ACMS_RAM::cacheDelete(); // loadUser関数のキャッシュを削除

        //----------
        // geometry
        $this->saveGeometry('uid', UID, $this->extract('geometry'));
    }

    protected function updateField()
    {
        Common::saveField('uid', UID, $this->field, $this->deleteField);
    }

    protected function updateFulltext()
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newSelect('user');
        $SQL->addWhereOpr('user_id', UID);
        $SQL->addWhereOpr('user_blog_id', BID);
        if (!!($row = $DB->query($SQL->get(dsn()), 'row'))) {
            ACMS_RAM::user(UID, $row);
        }
        Common::saveFulltext('uid', UID, Common::loadUserFulltext(UID));
        $this->Post->set('edit', 'update');
    }
}
