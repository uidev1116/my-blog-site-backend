<?php

class ACMS_POST_Form_Submit extends ACMS_POST_Form
{
    /**
     * 標準のチェックではなく、カスタマイズされたチェックをするためオフに設定
     *
     * @var bool
     */
    protected $isCSRF = false;

    /**
     * Run
     *
     * @inheritDoc
     */
    public function post()
    {
        // フォーム情報のロード
        $id = $this->Post->get('id');
        $info = $this->loadForm($id);
        if (empty($info)) {
            AcmsLogger::critical('フォームID「' . $id . '」が存在しないため、フォーム送信の処理を中断しました');
            $this->Post->set('step', 'forbidden');
            return $this->Post;
        }

        if (config('form_csrf_enable', 'on') !== 'off') {
            // CSRF対策
            if (!$this->checkCsrfToken()) {
                AcmsLogger::notice('フォームID「' . $id . '」で、CSRFトークンが一致しないため、処理を中断しました');
                $this->Post->set('step', 'forbidden');
                return $this->Post;
            }
            // 2重送信対策
            if (!$this->checkDoubleSubmit()) {
                AcmsLogger::notice('フォームID「' . $id . '」で、二重送信を検知したため処理を中断しました');
                $this->Post->set('step', 'repeated');
                return $this->Post;
            }
        }
        if (isCSRF()) {
            AcmsLogger::notice('フォームID「' . $id . '」で、Referrerから外部からのリクエストと判断されたため処理を中断しました');
            $this->Post->set('step', 'forbidden');
            return $this->Post;
        }

        $Form = $info['data'];
        $Mail =& $this->mergeMainConfig($Form->getChild('mail'));

        // サーバサイドのバリデーションを実装
        $Option = new Field();
        $Option->overload($Form->getChild('option'));
        $dup = $this->buildOptions($Option);

        // POSTの整形
        $Field = $this->extract('field');
        if (!empty($dup)) {
            list($fd, $mail) = $dup;
            $Field->setMethod($fd, 'duplication', $this->mailToDouble($info['id'], $mail));
        }

        // バリデート
        $Field->validate(new ACMS_Validator());
        if (!$this->Post->isValidAll()) {
            AcmsLogger::notice('フォームID「' . $id . '」で、バリデートに失敗したため、送信処理を中断しました');
            return $this->Post;
        }

        // メール送信前フック
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            /** @var bool $abort */
            $abort = false;
            $Hook->call('beforeSendAutoReply', [$this, &$abort, $Mail, $Field]);
            if ($abort) {
                AcmsLogger::notice('フォームID「' . $id . '」で、beforeSendAutoReplyフックによりメール送信が中止されました');
                return $this->Post;
            }
        }

        try {
            // 連番の更新
            $this->updateCount($info['id'], $Field);

            // メール送信
            $admin_log = $this->sendToAdministrator($Mail, $Field);
            $log = $this->sendAutoReply($Mail, $Field);
        } catch (Exception $e) {
            $this->Post->set('step', 'forbidden');
            AcmsLogger::warning('フォームID「' . $id . '」でメール送信に失敗しました', Common::exceptionArray($e, ['message' => $e->getMessage()]));
            return $this->Post;
        }

        try {
            // ログを記憶
            if (!($info['log'] === '0')) {
                $this->updateLog($info, $log, $admin_log, $Field);
            }
        } catch (Exception $e) {
            $this->Post->set('step', 'forbidden');
            AcmsLogger::warning('フォームID「' . $id . '」で送信結果のDB保存に失敗しました', Common::exceptionArray($e, ['message' => $e->getMessage()]));
            return $this->Post;
        }

        return $this->Post;
    }

    /**
     * 連番の更新
     *
     * @param int $fmid
     * @param Field & $Field
     */
    protected function updateCount($fmid, &$Field)
    {
        if (!!$fmid) {
            $DB = DB::singleton(dsn());
            $SQL = SQL::newUpdate('form');
            $SQL->addWhereOpr('form_id', $fmid);
            $SQL->setUpdate(
                'form_current_serial',
                SQL::newFunction(SQL::newOpr('form_current_serial', 1, '+'), 'LAST_INSERT_ID')
            );
            $Field->set('serialNumber', $DB->query($SQL->get(dsn()), 'seq'));
        } else {
            $Field->set('serialNumber', '');
        }
    }

    /**
     * メール情報のマージ
     * ConfigとPostのメール情報をマージする
     *
     * @param Field $Config
     * @return Field & $Mail
     */
    protected function & mergeMainConfig($Config)
    {
        $this->Post->set('acms_field_mail', [
            'To', 'SubjectTpl', 'BodyTpl', 'BodyHTMLTpl', 'Charset', 'CharsetHTML', 'Obfuscation', 'Sender', 'From', 'Reply-To', 'Cc', 'Bcc',
            'AdminTo', 'AdminSubjectTpl', 'AdminBodyTpl', 'AdminFrom', 'AdminReply-To', 'AdminCc', 'AdminBcc',
        ]);
        $Mail = $this->extract('acms_field_mail');
        foreach ($Mail->listFields() as $fd) {
            if ($Config->get('template' . $fd) === 'forbidden') {
                $Mail->set($fd, '');
            }
        }
        if ($Config->get('InvalidMultipleAddress') === '1') {
            foreach (['To', 'From', 'Reply-To', 'Cc', 'Bcc', 'AdminTo', 'AdminFrom', 'AdminReply-To', 'AdminCc', 'AdminBcc'] as $field) {
                $txt = $Mail->get($field);
                $emails = preg_split('/,/', $txt);
                if (is_array($emails) && count($emails) > 1) {
                    $Mail->set($field, $emails[0]);
                }
            }
        }
        $Mail->overload($Config);

        if (!!$Mail->get('Obfuscation')) {
            foreach (['To', 'Sender', 'From', 'Reply-To', 'Cc', 'Bcc', 'AdminTo', 'AdminFrom', 'AdminReply-To', 'AdminCc', 'AdminBcc'] as $field) {
                $values = [];
                foreach ($Mail->getArray($field) as $value) {
                    $values[] = base64_decode($value); // @phpstan-ignore-line
                }
                $Mail->set($field, $values);
            }
        }

        // AdminFrom | AdminReply-To が空の場合Toの値を設定
        if (!$Mail->get('AdminFrom')) {
            $Mail->set('AdminFrom', $Mail->get('To'));
        }
        if (!$Mail->get('AdminReply-To')) {
            $Mail->set('AdminReply-To', $Mail->get('To'));
        }

        return $Mail;
    }

    /**
     * 自動返信メール送信
     *
     * @param Field & $Mail
     * @param Field & $Field
     * @return array
     */
    protected function sendAutoReply(&$Mail, &$Field)
    {
        $to = $Mail->getArray('To');
        $htmlBodyConfigPath = $Mail->get('BodyHTMLTpl');
        $subject = $this->getSubjectTemplate($Mail, $Field, 'Subject', 'SubjectTpl');
        $body = $this->getBodyTemplate($Mail, $Field, 'Body', 'BodyTpl');
        $info = $this->getBaseMailInfo($Mail);

        if (empty($to) || empty($subject) || empty($body)) {
            return $info;
        }

        $info = array_merge($info, [
            'from' => $Mail->get('From'),
            'to' => $to,
            'subject' => $subject,
            'cc' => $Mail->getArray('Cc'),
            'bcc' => $Mail->getArray('Bcc'),
            'reply-to' => $Mail->getArray('Reply-To'),
            'body' => $body,
        ]);

        $mailer = Mailer::init();

        // 基本設定を追加
        $this->addBaseMailParam($mailer, $info);

        if (!empty($htmlBodyConfigPath)) {
            // HTMLメール（本文HTMLファイルが設定されている場合）
            $htmlBodyTplPath = findTemplate($htmlBodyConfigPath);
            if ($htmlBodyTplPath !== false) {
                $info['body-html'] = Common::getMailTxt($htmlBodyTplPath, $Field);
                $mailer->setHtml($info['body-html'], $info['body']);
            } else {
                AcmsLogger::warning('HTMLメール本文のテンプレートが見つかりませんでした', [
                    'path' => $htmlBodyConfigPath,
                ]);
                // 管理者宛HTMLメール本文のテンプレートが取得できなかった場合はテキストメール
                $mailer->setBody($info['body']);
            }
        } else {
            // テキストメール
            $mailer->setBody($info['body']);
        }
        if ($Mail->get('FormSend') !== 'no') {
            $mailer->send();
        }

        return $info;
    }

    /**
     * 管理者宛メール送信
     *
     * @param Field & $Mail
     * @param Field & $Field
     * @return array
     */
    protected function sendToAdministrator(&$Mail, &$Field)
    {
        $to = $Mail->getArray('AdminTo');
        $htmlBodyConfigPath = $Mail->get('AdminBodyHTMLTpl');
        $attached_files = [];
        $subject = $this->getSubjectTemplate($Mail, $Field, 'AdminSubject', 'AdminSubjectTpl');
        $body = $this->getBodyTemplate($Mail, $Field, 'AdminBody', 'AdminBodyTpl');
        $info = $this->getBaseMailInfo($Mail);

        if (empty($to) || empty($subject) || empty($body)) {
            return $info;
        }

        $info = array_merge($info, [
            'from' => $Mail->get('AdminFrom'),
            'to' => $to,
            'subject' => $subject,
            'cc' => $Mail->getArray('AdminCc'),
            'bcc' => $Mail->getArray('AdminBcc'),
            'reply-to' => $Mail->getArray('AdminReply-To'),
            'body' => $body,
            'attached_file' => false,
        ]);
        $mailer = Mailer::init();

        // 基本設定を追加
        $this->addBaseMailParam($mailer, $info);

        // 添付ファイル
        if ($Mail->get('AdminAttachment') === 'on') {
            foreach ($Field->listFields() as $fd) {
                $pathInfo = $this->getAttachedFilePath($Field, $fd);
                if ($pathInfo === false) {
                    continue;
                }
                $temp_path = $pathInfo['temppath'];
                $mailer->attach($temp_path, $pathInfo['original_name']);
                $attached_files[] = $temp_path;
            }
        }

        if (!empty($htmlBodyConfigPath)) {
            // HTMLメール（本文HTMLファイルが設定されている場合）
            $htmlBodyTplPath = findTemplate($htmlBodyConfigPath);
            if ($htmlBodyTplPath !== false) {
                $info['body-html'] = Common::getMailTxt($htmlBodyTplPath, $Field);
                $mailer->setHtml($info['body-html'], $info['body']);
            } else {
                AcmsLogger::warning('管理者宛HTMLメール本文のテンプレートが見つかりませんでした', [
                    'path' => $htmlBodyConfigPath,
                ]);
                // テキストメール
                $mailer->setBody($info['body']);
            }
        } else {
            // テキストメール
            $mailer->setBody($info['body']);
        }
        if ($Mail->get('AdminFormSend') !== 'no') {
            $mailer->send(config('form_attached_file_delete_immediately', 'on') === 'on');
        }

        return $info;
    }

    /**
     * メールパラメータのベース
     *
     * @param Field $Mail
     * @return array
     */
    protected function getBaseMailInfo($Mail)
    {
        return [
            'sender' => $Mail->get('Sender'),
            'charset' => 'UTF-8',
            'charset-html' => 'UTF-8',
            'from' => '',
            'to' => [],
            'subject' => '',
            'cc' => [],
            'bcc' => [],
            'reply-to' => [],
            'body' => '',
            'body-html' => '',
            'attached_file' => false,
        ];
    }

    /**
     * 基本のメールパラメータを追加
     *
     * @param \Acms\Services\Mailer\Engine &$FormMail
     * @param array $info
     */
    protected function addBaseMailParam(\Acms\Services\Mailer\Engine &$FormMail, $info)
    {
        $FormMail->setFrom($info['from'])
            ->setTo(implode(', ', $info['to']))
            ->setSubject($info['subject'])
            ->setCc(implode(', ', $info['cc']))
            ->setBcc(implode(', ', $info['bcc']))
            ->setReplyTo(implode(', ', $info['reply-to']));
    }

    /**
     * メールログの保存
     *
     * @param array $info
     * @param array $log
     * @param \Field $Field
     * @param array $admin_log
     *
     * @return void
     */
    protected function updateLog($info, $log, $admin_log, $Field)
    {
        if (empty($info)) {
            return;
        }

        $fmid = $info['id'];
        $fmbid = $info['bid'];
        $fmeid = $this->Post->get('eid');

        $DB = DB::singleton(dsn());
        $SQL = SQL::newInsert('log_form');
        $SQL->addInsert('log_form_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addInsert('log_form_remote_addr', REMOTE_ADDR);
        $SQL->addInsert('log_form_user_agent', UA);
        $SQL->addInsert('log_form_mail_to', strval(join(', ', $log['to'])));
        $SQL->addInsert('log_form_mail_subject', acmsSerialize(strval($log['subject'])));
        $SQL->addInsert('log_form_mail_subject_admin', acmsSerialize(strval($admin_log['subject'])));
        $SQL->addInsert('log_form_mail_body', acmsSerialize(strval($log['body'])));
        $SQL->addInsert('log_form_mail_body_admin', acmsSerialize(strval($admin_log['body'])));
        $SQL->addInsert('log_form_data', acmsSerialize($Field));
        $SQL->addInsert('log_form_serial', $Field->get('serialNumber'));
        $SQL->addInsert('log_form_version', 1);

        $SQL->addInsert('log_form_entry_id', $fmeid);
        $SQL->addInsert('log_form_form_id', $fmid);
        $SQL->addInsert('log_form_blog_id', $fmbid);
        $DB->query($SQL->get(dsn()), 'exec');

        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('formSubmit', [$log, $admin_log]);

            Webhook::call(BID, 'form', 'form:sent', [$log, $admin_log, $Field->_aryField]);
        }
    }

    /**
     * Subjectのテンプレートを取得
     *
     * @param Field $mail
     * @param Field $field
     * @param string $txt_field
     * @param string $path_field
     * @return string
     */
    protected function getSubjectTemplate($mail, $field, $txt_field, $path_field)
    {
        $subjectTpl = $mail->get($txt_field, false);

        if (!empty($subjectTpl)) {
            return Common::getMailTxtFromTxt($subjectTpl, $field);
        }

        $configPath = $mail->get($path_field);
        if (empty($configPath)) {
            // 件名ファイル未設定の場合
            return '';
        }

        $subjectTplPath = findTemplate($configPath);
        if ($subjectTplPath === false) {
            AcmsLogger::warning('メール件名のテンプレートが見つかりませんでした', [
                'path' => $configPath,
            ]);
            return '';
        }

        return Common::getMailTxt($subjectTplPath, $field);
    }

    /**
     * bodyのテンプレートを取得
     *
     * @param Field $mail
     * @param Field $field
     * @param string $txt_field
     * @param string $path_field
     * @return string
     */
    protected function getBodyTemplate($mail, $field, $txt_field, $path_field)
    {
        $bodyTpl = $mail->get($txt_field, false);
        if (!empty($bodyTpl)) {
            return Common::getMailTxtFromTxt($bodyTpl, $field);
        }

        $configPath = $mail->get($path_field);
        if (empty($configPath)) {
            // 本文ファイル未設定の場合
            return '';
        }

        $bodyTplPath = findTemplate($configPath);
        if ($bodyTplPath === false) {
            AcmsLogger::warning('メール本文のテンプレートが見つかりませんでした', [
                'path' => $configPath,
            ]);
            return '';
        }

        return Common::getMailTxt($bodyTplPath, $field);
    }
}
