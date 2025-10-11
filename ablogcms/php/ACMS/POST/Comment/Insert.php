<?php

class ACMS_POST_Comment_Insert extends ACMS_POST_Comment
{
    /**
     * CSRF対策
     *
     * @var bool
     */
    protected $isCSRF = true;

    /**
     * 二重送信対策
     *
     * @var bool
     */
    protected $checkDoubleSubmit = true;

    /**
     * @inheritDoc
     */
    public function post()
    {
        $nextstep   = $this->Post->get('nextstep');
        $redirect   = $this->Post->get('redirect');

        //------
        // bug
        $Comment = $this->extract('comment');
        if (!$Comment->isValid()) {
            $this->Post->set('action', 'insert');
            return $this->Post;
        }

        $replyId    = intval($Comment->get('reply_id'));
        $name       = $Comment->get('name');
        $mail       = strval($Comment->get('mail'));
        $url        = strval($Comment->get('url'));
        $pass       = $Comment->get('pass');

        //-------------
        // save cookie
        if ('on' == $Comment->get('persistent')) {
            $expire = strtotime(date('Y-m-d H:i:s', REQUEST_TIME)) + intval(config('comment_cookie_lifetime'));
            acmsSetCookie('acms_comment_name', $name, $expire, '/');
            acmsSetCookie('acms_comment_mail', $mail, $expire, '/');
            acmsSetCookie('acms_comment_url', $url, $expire, '/');
            acmsSetCookie('acms_comment_pass', $pass, $expire, '/');
        } else {
            $expire = REQUEST_TIME - 1;
            acmsSetCookie('acms_comment_name', null, $expire, '/');
            acmsSetCookie('acms_comment_mail', null, $expire, '/');
            acmsSetCookie('acms_comment_url', null, $expire, '/');
            acmsSetCookie('acms_comment_pass', null, $expire, '/');
        }

        //-------
        // align
        $DB = DB::singleton(dsn());
        if (!empty($replyId)) {
            if (!$pt = ACMS_RAM::commentRight($replyId)) {
                die500();
            }
            $pid    = $replyId;

            $SQL    = SQL::newUpdate('comment');
            $SQL->setUpdate('comment_left', SQL::newOpr('comment_left', 2, '+'));
            $SQL->addWhereOpr('comment_left', $pt, '>');
            $SQL->addWhereOpr('comment_entry_id', EID);
            $SQL->addWhereOpr('comment_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            $SQL    = SQL::newUpdate('comment');
            $SQL->setUpdate('comment_right', SQL::newOpr('comment_right', 2, '+'));
            $SQL->addWhereOpr('comment_right', $pt, '>=');
            $SQL->addWhereOpr('comment_entry_id', EID);
            $SQL->addWhereOpr('comment_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');
        } else {
            $SQL    = SQL::newSelect('comment');
            $SQL->setSelect('comment_right');
            $SQL->addWhereOpr('comment_entry_id', EID);
            $SQL->addWhereOpr('comment_blog_id', BID);
            $SQL->setOrder('comment_right', 'DESC');
            $SQL->setLimit(1);
            $pt     = intval($DB->query($SQL->get(dsn()), 'one')) + 1;
            $pid    = 0;
        }

        $cmid   = $DB->query(SQL::nextval('comment_id', dsn()), 'seq');
        $SQL    = SQL::newInsert('comment');
        $SQL->addInsert('comment_id', $cmid);
        $SQL->addInsert('comment_status', config('comment_status'));
        $SQL->addInsert('comment_parent', $pid);
        $SQL->addInsert('comment_left', $pt);
        $SQL->addInsert('comment_right', $pt + 1);
        $SQL->addInsert('comment_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addInsert('comment_host', REMOTE_ADDR);
        $SQL->addInsert('comment_entry_id', EID);
        $SQL->addInsert('comment_user_id', intval(SUID));
        $SQL->addInsert('comment_blog_id', BID);
        $SQL->addInsert('comment_title', $Comment->get('title'));
        $SQL->addInsert('comment_body', $Comment->get('body'));
        $SQL->addInsert('comment_name', $Comment->get('name'));
        $SQL->addInsert('comment_mail', strval($Comment->get('mail')));
        $SQL->addInsert('comment_url', strval($Comment->get('url')));
        $SQL->addInsert('comment_pass', $Comment->get('pass'));
        $DB->query($SQL->get(dsn()), 'exec');

        //------
        // mail
        $Comment->set('commentUrl', acmsLink([
            'bid'       => BID,
            'cid'       => ACMS_RAM::entryCategory(EID),
            'eid'       => EID,
            'cmid'      => $cmid,
            'fragment'  => 'comment-' . $cmid,
        ], false));

        if (
            1
            and $to = configArray('mail_comment_to')
            and $subjectTpl = findTemplate(config('mail_comment_tpl_subject'))
            and $bodyTpl = findTemplate(config('mail_comment_tpl_body'))
        ) {
            $subject    = Common::getMailTxt($subjectTpl, $Comment);
            $body       = Common::getMailTxt($bodyTpl, $Comment);

            $to = implode(', ', $to);
            $from = config('mail_comment_from');
            $bcc = implode(', ', configArray('mail_comment_bcc'));

            try {
                $mailer = Mailer::init();
                $mailer = $mailer->setFrom($from)
                    ->setTo($to)
                    ->setBcc($bcc)
                    ->setSubject($subject)
                    ->setBody($body);

                if ($bodyHtmlTpl = findTemplate(config('mail_comment_tpl_body_html'))) {
                    $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $Comment);
                    $mailer = $mailer->setHtml($bodyHtml);
                }
                $mailer->send();
            } catch (Exception $e) {
                AcmsLogger::warning('コメントの通知メール送信に失敗しました', Common::exceptionArray($e));
            }
        }

        AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '」エントリーにコメントを投稿しました', [
            'comment_id' => $cmid,
        ]);

        if (!empty($redirect) && Common::isSafeUrl($redirect)) {
            $this->redirect($redirect);
        }

        if (!empty($nextstep)) {
            $this->Post->set('step', $nextstep);
            $this->Post->set('action', 'insert');
            return $this->Post;
        }

        return $this->Post;
    }
}
