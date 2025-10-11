<?php

class ACMS_POST_Approval_Comment extends ACMS_POST_Approval
{
    public function post()
    {
        $approval = $this->extract('approval');
        $rvid = $approval->get('rvid');
        $comment = $approval->get('request_comment');
        if (!$rvid) {
            return false;
        }
        $approval->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $apid = DB::query(SQL::nextval('approval_id', dsn()), 'seq');

            $SQL = SQL::newInsert('approval');
            $SQL->addInsert('approval_id', $apid);
            $SQL->addInsert('approval_type', 'comment');
            $SQL->addInsert('approval_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $SQL->addInsert('approval_comment', $comment);
            $SQL->addInsert('approval_request_user_id', SUID);
            $SQL->addInsert('approval_revision_id', $rvid);
            $SQL->addInsert('approval_entry_id', EID);
            $SQL->addInsert('approval_blog_id', BID);
            DB::query($SQL->get(dsn()), 'exec');

            $revision = Entry::getRevision(EID, $rvid);
            AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '（' . $revision['entry_rev_memo'] . '）」の承認フローにコメントを残しました', [
                'apid' => $apid,
                'eid' => EID,
                'rvid' => $rvid,
                'comment' => $comment,
            ]);

            $notifyAddresses = $this->getNotifyAddreses(EID, $rvid, SUID);
            if (!empty($notifyAddresses)) {
                $this->notify(EID, $rvid, $approval, $notifyAddresses);
            }
        }
        return $this->Post;
    }

    /**
     * 承認画面でコメントされた時の通知アドレスを取得
     *
     * @param int $eid
     * @param int $rvid
     * @param int $exceptUid
     * @return array
     */
    protected function getNotifyAddreses($eid, $rvid, $exceptUid = 0)
    {
        $sql = SQL::newSelect('approval');
        $sql->setSelect('approval_request_user_id');
        $sql->addWhereOpr('approval_entry_id', $eid);
        $sql->addWhereOpr('approval_revision_id', $rvid);
        $sql->addWhereOpr('approval_request_user_id', SUID, '<>');

        $all =  DB::query($sql->get(dsn()), 'all');
        $addresses = [];
        foreach ($all as $user) {
            $uid = $user['approval_request_user_id'];
            $addresses[] = ACMS_RAM::userMail($uid);
        }
        return $addresses;
    }

    /**
     * 通知メールを送信
     *
     * @param int $eid
     * @param int $rvid
     * @param Field $approval
     * @param array $addresses
     */
    protected function notify($eid, $rvid, $approval, $addresses)
    {
        //-----------
        // Send Mail
        if (
            1
            and $addresses
            and $subjectTpl = findTemplate(config('mail_approval_tpl_subject'))
            and $bodyTpl = findTemplate(config('mail_approval_tpl_body'))
        ) {
            $SQL = SQL::newSelect('entry_rev');
            $SQL->addWhereOpr('entry_id', $eid);
            $SQL->addWhereOpr('entry_rev_id', $rvid);
            $rev = DB::query($SQL->get(dsn()), 'row');

            $approval->setField('request_user', ACMS_RAM::userName(SUID));
            $approval->setField('approval', 'comment');
            $approval->setField('approval2', 'comment');
            $approval->setField('approval3', 'comment');
            $approval->setField('approval4', 'comment');
            $approval->setField('entryTitle', $rev['entry_title']);
            $approval->setField('entryStatus', ACMS_RAM::entryStatus($eid));
            $approval->setField('version', $rev['entry_rev_memo']);
            $approval->setField('revisionUrl', acmsLink([
                'protocol' => SSL_ENABLE ? 'https' : 'http',
                'bid' => BID,
                'cid' => CID,
                'eid' => $eid,
                'tpl' => 'ajax/revision/preview.html',
                'query' => ['rvid' => $rvid],
            ], false));

            $subject = Common::getMailTxt($subjectTpl, $approval);
            $body = Common::getMailTxt($bodyTpl, $approval);

            $to = is_array($addresses) ? implode(', ', $addresses) : $addresses;
            $from = getApprovalFrom(SUID);
            $bcc = implode(', ', configArray('mail_approval_bcc'));

            $send = true;
            if (HOOK_ENABLE) {
                $data = [
                    'type' => 'public',
                    'from' => [$from],
                    'to' => $addresses,
                    'subject' => $subject,
                    'bcc' => configArray('mail_approval_bcc'),
                    'body' => $body,
                    'data' => $approval,
                ];
                $Hook = ACMS_Hook::singleton();
                $Hook->call('approvalNotification', [$data, &$send]);
            }
            if ($send !== false) {
                try {
                    $mailer = Mailer::init();
                    $mailer = $mailer->setFrom($from)
                        ->setTo($to)
                        ->setBcc($bcc)
                        ->setSubject($subject)
                        ->setBody($body);

                    if ($bodyHtmlTpl = findTemplate(config('mail_approval_tpl_body_html'))) {
                        $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $approval);
                        $mailer = $mailer->setHtml($bodyHtml);
                    }
                    $mailer->send();
                } catch (Exception $e) {
                    AcmsLogger::warning('承認コメントの通知メールの送信に失敗しました', Common::exceptionArray($e));
                }
            }
        }
    }
}
