<?php

use Acms\Services\Validator\Signin as SigninValidator;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Mailer;

class ACMS_POST_Member_Update_Profile extends ACMS_POST_Member
{
    /**
     * Main
     * @return Field_Validation
     */
    public function post(): Field_Validation
    {
        $user = $this->extract('user');
        $deleteField = new Field();
        $field = $this->extract('field', new ACMS_Validator(), $deleteField);

        $preUser = loadUser(SUID);
        $preField = loadUserField(SUID);

        $this->validate($user);

        if ($this->Post->isValidAll()) {
            $this->save($user, $field, $deleteField);

            AcmsLogger::info('ユーザー「' . $preUser->get('name') . '」がユーザー情報を更新しました', [
                'uid' => SUID,
                'user' => $user->_aryField,
                'field' => $field->_aryField,
            ]);

            Webhook::call(BID, 'user', ['user:updated'], SUID);

            if (editionIsProfessional() || editionIsEnterprise()) {
                $user->set('mail', ACMS_RAM::userMail(SUID));
                $changed = $this->diff($user, $field, $preUser, $preField);
                if ($changed) {
                    $this->notify($user, $field);
                }
            }
        } else {
            AcmsLogger::info('ユーザー「' . $preUser->get('name') . '」の情報更新に失敗しました', [
                'uid' => SUID,
                'user' => $user->_aryV,
                'field' => $field->_aryV,
            ]);
        }
        return $this->Post;
    }

    /**
     * バリデーション
     * @param Field_Validation $user
     * @return void
     */
    protected function validate(Field_Validation $user): void
    {
        if (!Login::canMemberSignin()) {
            $user->setMethod('updateProfile', 'operable', false);
            httpStatusCode('403 Forbidden');
        }
        $user->setMethod('name', 'required');
        $user->setMethod('code', 'doubleCode', SUID);
        $user->setMethod('mail_magazine', 'in', ['on', 'off']);
        $user->setMethod('url', 'url');
        $user->setMethod('code', 'string', isValidCode($user->get('code')));
        $user->setMethod('user', 'operable', !!SUID);
        $user->validate(new SigninValidator());
    }

    /**
     * 更新を保存
     * @param Field_Validation $user
     * @param Field_Validation $field
     * @param Field $deleteField
     * @return void
     */
    protected function save(Field_Validation $user, Field_Validation $field, Field $deleteField): void
    {
        $this->updateUser($user);
        $this->updateField($field, $deleteField);
        $this->updateFulltext();
        $this->Post->set('updated', 'success');
    }

    /**
     * ユーザー情報を更新
     * @param Field_Validation $user
     * @return void
     */
    protected function updateUser(Field_Validation $user): void
    {
        $sql = SQL::newUpdate('user');
        if ($user->isExists('name')) {
            $sql->addUpdate('user_name', $user->get('name'));
        }
        if ($user->isExists('code')) {
            $sql->addUpdate('user_code', strval($user->get('code')));
        }
        if ($user->isExists('url')) {
            $sql->addUpdate('user_url', strval($user->get('url')));
        }
        if ($user->isExists('mail_magazine')) {
            $sql->addUpdate('user_mail_magazine', $user->get('mail_magazine'));
        }
        if ($user->isExists('mail_mobile_magazine')) {
            $sql->addUpdate('user_mail_mobile_magazine', $user->get('mail_mobile_magazine'));
        }
        $sql->addUpdate('user_updated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        if ($user->isExists('icon@squarePath') && $iconPath = Login::resizeUserIcon($user->get('icon@squarePath'))) {
            $sql->addUpdate('user_icon', $iconPath);
            $user->set('icon', $iconPath);
        }
        $sql->addWhereOpr('user_id', SUID);
        DB::query($sql->get(dsn()), 'exec');

        ACMS_RAM::cacheDelete();
        ACMS_RAM::user(SUID, null);
        $this->saveGeometry('uid', SUID, $this->extract('geometry'));
    }

    /**
     * フィールドを更新
     * @param Field_Validation $field
     * @param Field $deleteField
     * @return void
     */
    protected function updateField(Field_Validation $field, Field $deleteField): void
    {
        $field->set('updateField', 'on');
        Common::saveField('uid', SUID, $field, $deleteField);
    }

    /**
     * フルテキストを更新
     * @return void
     */
    protected function updateFulltext(): void
    {
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_id', SUID);
        if ($row = DB::query($sql->get(dsn()), 'row')) {
            ACMS_RAM::user(SUID, $row);
        }
        Common::saveFulltext('uid', SUID, Common::loadUserFulltext(SUID));
    }

    /**
     * 情報の更新があるかチェック
     * @param Field_Validation $user
     * @param Field_Validation $field
     * @param Field_Validation $preUser
     * @param Field $preField
     * @return bool
     */
    protected function diff(Field_Validation $user, Field_Validation $field, Field_Validation $preUser, Field $preField): bool
    {
        $diff = false;
        $columns = ['name', 'code', 'mail', 'mail_mobile', 'url'];

        foreach ($field->listFields() as $key) {
            if ($field->get($key) !== $preField->get($key)) {
                $field->setField('old_' . $key, $preField->get($key));
                $diff = true;
            }
        }
        foreach ($columns as $column) {
            if ($user->isExists($column) && $user->get($column) !== $preUser->get($column)) {
                $field->setField('old_' . $column, $preUser->get($column));
                $diff = true;
            }
            $field->setField($column, $user->get($column, $preUser->get($column)));
        }
        return $diff;
    }

    /**
     * ユーザー更新を通知
     * @param Field_Validation $user
     * @param Field_Validation $field
     * @return void
     * @throws Exception
     */
    protected function notify(Field_Validation $user, Field_Validation $field): void
    {
        if (config('mail_update_user_enable') === 'on') {
            $this->sendEmail(
                config('mail_update_user_tpl_subject'),
                config('mail_update_user_tpl_body'),
                config('mail_update_user_tpl_body_html'),
                $user->getArray('mail'),
                config('mail_update_user_from'),
                configArray('mail_update_user_bcc'),
                $field
            );
        }

        if (config('mail_update_user_admin_enable') === 'on') {
            $this->sendEmail(
                config('mail_update_user_admin_tpl_subject'),
                config('mail_update_user_admin_tpl_body'),
                config('mail_update_user_admin_tpl_body_html'),
                configArray('mail_update_user_admin_to'),
                config('mail_update_user_admin_from'),
                configArray('mail_update_user_admin_bcc'),
                $field
            );
        }
    }

    /**
     *
     * @param string $subject
     * @param string $body
     * @param string $html
     * @param array $to
     * @param string $from
     * @param array $bcc
     * @param Field_Validation $field
     * @return void
     * @throws Exception
     */
    protected function sendEmail(string $subject, string $body, string $html, array $to, string $from, array $bcc, Field_Validation $field): void
    {
        $subjectTpl = findTemplate($subject);
        $bodyTpl = findTemplate($body);

        if (empty($to) || empty($subjectTpl) || empty($bodyTpl)) {
            return;
        }
        $field->set('uid', SUID);
        $subject = Common::getMailTxt($subjectTpl, $field);
        $body = Common::getMailTxt($bodyTpl, $field);

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
                $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $field);
                $mailer = $mailer->setHtml($bodyHtml);
            }
            $mailer->send();
        } catch (Exception $e) {
            throw $e;
        }
    }
}
