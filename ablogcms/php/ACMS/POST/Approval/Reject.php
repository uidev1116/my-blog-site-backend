<?php

class ACMS_POST_Approval_Reject extends ACMS_POST_Approval
{
    public function post()
    {
        $DB         = DB::singleton(dsn());
        $Approval   = $this->extract('approval');

        if (!sessionWithApprovalReject()) {
            return false;
        }
        if (!($rvid    = $Approval->get('rvid'))) {
            return false;
        }

        $Approval->setMethod('receiver', 'required');

        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $comment    = $Approval->get('request_comment');
            $req_group  = $Approval->get('current_group');
            $rec_user   = null;
            $To         = '';
            $type       = 'series';

            if (editionIsEnterprise()) {
                $workflow = loadWorkflow(BID, CID);
                $type = $workflow->get('workflow_type');
                $cid = $workflow->get('workflow_category_id');
                if (empty($cid)) {
                    $cid = null;
                } else {
                    $cid = intval($cid);
                }

                // 並列承認
                if ($type == 'parallel') {
                    $SQL    = SQL::newSelect('workflow_usergroup', 'wug');
                    $SQL->addLeftJoin('usergroup_user', 'usergroup_id', 'usergroup_id', 'ugu', 'wug');
                    $SQL->addLeftJoin('user', 'user_id', 'user_id', 'u', 'ugu');
                    $SQL->addSelect('user_mail');
                    $SQL->addSelect('user_name');
                    $SQL->addWhereOpr('user_status', 'open');
                    $SQL->addWhereOpr('user_id', SUID, '<>', 'AND', 'u');
                    $SQL->addWhereOpr('workflow_blog_id', BID);
                    $SQL->addWhereOpr('workflow_category_id', $cid);

                    $mail = $DB->query($SQL->get(dsn()), 'all');
                    $mail_ = [];
                    foreach ($mail as $addr) {
                        $mail_[] = $addr['user_mail'];
                    }
                    $To     = $mail_;

                // 直列承認
                } else {
                    $SQL    = SQL::newSelect('approval');
                    $SQL->addLeftJoin('user', 'approval_request_user_id', 'user_id');
                    $SQL->addSelect('user_mail');
                    $SQL->addSelect('user_name');
                    $SQL->addWhereOpr('user_status', 'open');
                    $SQL->addWhereOpr('approval_type', 'request');
                    $SQL->addWhereOpr('approval_revision_id', $rvid);
                    $SQL->addWhereOpr('approval_entry_id', EID);
                    $all    = $DB->query($SQL->get(dsn()), 'all');
                    $mail_  = [];
                    foreach ($all as $mail) {
                        $mail_[] = $mail['user_mail'];
                    }
                    $To     = $mail_;
                }
            } elseif (editionIsProfessional()) {
                $SQL    = SQL::newSelect('approval');
                $SQL->addSelect('approval_request_user_id');
                $SQL->addWhereOpr('approval_revision_id', $rvid);
                $SQL->addWhereOpr('approval_entry_id', EID);
                $SQL->addWhereOpr('approval_blog_id', BID);
                $SQL->addWhereOpr('approval_type', 'request');
                if ($uid = $DB->query($SQL->get(dsn()), 'one')) {
                    $To         = ACMS_RAM::userMail($uid);
                    $rec_user   = $uid;
                }
            }

            //-----------
            // Send Mail
            $apid = null;
            if (
                1
                and $To
                and $subjectTpl = findTemplate(config('mail_approval_tpl_subject'))
                and $bodyTpl    = findTemplate(config('mail_approval_tpl_body'))
            ) {
                $revision = Entry::getRevision(EID, $rvid);

                $Approval->setField('request_user', ACMS_RAM::userName(SUID));
                $Approval->setField('approval', 'reject');
                $Approval->setField('approval2', 'reject');
                $Approval->setField('approval3', 'reject');
                $Approval->setField('approval4', 'reject');
                $Approval->setField('entryTitle', $revision['entry_title']);
                $Approval->setField('entryStatus', ACMS_RAM::entryStatus(EID));
                $Approval->setField('version', $revision['entry_rev_memo']);
                $Approval->setField('revisionUrl', acmsLink([
                    'protocol'  => SSL_ENABLE ? 'https' : 'http',
                    'bid'   => BID,
                    'cid'   => CID,
                    'eid'   => EID,
                    'tpl'   => 'ajax/revision/preview.html',
                    'query' => ['rvid' => $rvid],
                ], false));

                $subject    = Common::getMailTxt($subjectTpl, $Approval);
                $body       = Common::getMailTxt($bodyTpl, $Approval);

                $to = is_array($To) ? implode(', ', $To) : $To;
                $from = getApprovalFrom(SUID);
                $bcc = implode(', ', configArray('mail_approval_bcc'));

                $send = true;
                if (HOOK_ENABLE) {
                    $data = [
                        'type'      => 'reject',
                        'from'      => [$from],
                        'to'        => $To,
                        'subject'   => $subject,
                        'bcc'       => configArray('mail_approval_bcc'),
                        'body'      => $body,
                        'data'      => $Approval,
                    ];
                    $Hook   = ACMS_Hook::singleton();
                    $Hook->call('approvalNotification', [$data, & $send]);
                }
                if ($send !== false) {
                    try {
                        $mailer = Mailer::init();
                        $mailer->setFrom($from)
                            ->setTo($to)
                            ->setBcc($bcc)
                            ->setSubject($subject)
                            ->setBody($body);
                        if ($bodyHtmlTpl = findTemplate(config('mail_approval_tpl_body_html'))) {
                            $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $Approval);
                            $mailer = $mailer->setHtml($bodyHtml);
                        }
                        $mailer->send();
                    } catch (Exception $e) {
                        AcmsLogger::warning('最終却下の通知メールの送信に失敗しました', Common::exceptionArray($e));
                    }
                }

                //---------
                // Save DB
                $apid = $DB->query(SQL::nextval('approval_id', dsn()), 'seq');

                // 並列承認
                if ($type == 'parallel') {
                    $rec_user   = null;
                    $req_group  = null;
                }

                $SQL    = SQL::newInsert('approval');
                $SQL->addInsert('approval_id', $apid);
                $SQL->addInsert('approval_type', 'reject');
                $SQL->addInsert('approval_method', $type);
                $SQL->addInsert('approval_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
                $SQL->addInsert('approval_comment', $comment);
                $SQL->addInsert('approval_receive_usergroup_id', null);
                $SQL->addInsert('approval_receive_user_id', $rec_user);
                $SQL->addInsert('approval_request_usergroup_id', $req_group);
                $SQL->addInsert('approval_request_user_id', SUID);
                $SQL->addInsert('approval_revision_id', $rvid);
                $SQL->addInsert('approval_entry_id', EID);
                $SQL->addInsert('approval_blog_id', BID);
                $DB->query($SQL->get(dsn()), 'exec');

                //------------------------
                // Update Revision Status
                $SQL    = SQL::newUpdate('entry_rev');
                $SQL->addUpdate('entry_rev_status', 'reject');
                // 並列承認
                if ($type == 'parallel') {
                    $POINT  = SQL::newSelect('entry_rev');
                    $POINT->addSelect('entry_approval_reject_point');
                    $POINT->addWhereOpr('entry_id', EID);
                    $POINT->addWhereOpr('entry_rev_id', $rvid);
                    $currentPoint = $DB->query($POINT->get(dsn()), 'one');
                    $point        = approvalUserPoint(BID);

                    $SQL->addUpdate('entry_approval_reject_point', $currentPoint + $point);
                }
                $SQL->addWhereOpr('entry_id', EID);
                $SQL->addWhereOpr('entry_rev_id', $rvid);
                $DB->query($SQL->get(dsn()), 'exec');
                ACMS_RAM::entry(EID, null);

                //---------------------
                // Update Notification
                $SQL    = SQL::newDelete('approval_notification');
                $SQL->addWhereOpr('notification_rev_id', $rvid);
                $SQL->addWhereOpr('notification_entry_id', EID);
                $SQL->addWhereOpr('notification_blog_id', BID);
                $DB->query($SQL->get(dsn()), 'exec');

                $SQL    = SQL::newInsert('approval_notification');
                $SQL->addInsert('notification_rev_id', $rvid);
                $SQL->addInsert('notification_entry_id', EID);
                $SQL->addInsert('notification_blog_id', BID);
                $SQL->addInsert('notification_approval_id', $apid);
                $SQL->addInsert('notification_request_user_id', SUID);
                $SQL->addInsert('notification_receive_user_id', $rec_user);
                $SQL->addInsert('notification_receive_usergroup_id', null);
                $SQL->addInsert('notification_type', 'reject');
                $SQL->addInsert('notification_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
                $DB->query($SQL->get(dsn()), 'exec');
            }

            $revision = Entry::getRevision(EID, $rvid);
            AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '（' . $revision['entry_rev_memo'] . '）」の承認を却下しました', [
                'apid' => $apid,
                'eid' => EID,
                'rvid' => $rvid,
                'comment' => $comment,
            ]);
        }
        return $this->Post;
    }
}
