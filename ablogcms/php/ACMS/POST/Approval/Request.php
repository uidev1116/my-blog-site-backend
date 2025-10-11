<?php

class ACMS_POST_Approval_Request extends ACMS_POST_Approval
{
    function post()
    {
        $DB         = DB::singleton(dsn());
        $Approval   = $this->extract('approval');
        if (!($rvid    = $Approval->get('rvid'))) {
            return false;
        }

        $workflowPoint  = approvalWorkflowPublicPoint(BID, CID);
        $currentPoint   = approvalRevisionPublicPoint(EID, $rvid);
        $point          = approvalUserPoint(BID);

        if (enableParallelApproval(BID, CID) && $workflowPoint <= $currentPoint + $point) {
            $Approval->setMethod('approvalPoint', 'overpoint', false);
        }
        if (!sessionWithApprovalRequest()) {
            $Approval->setMethod('approvalRequest', 'operator', false);
        }
        $Approval->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $receiver   = $Approval->get('receiver');
            $comment    = $Approval->get('request_comment');
            $req_group  = $Approval->get('current_group');
            $ugid       = null;
            $uid        = null;

            if (preg_match('/\d:\d/', $receiver)) {
                list($ugid, $uid) = preg_split('@:@', $receiver, 2, PREG_SPLIT_NO_EMPTY);
            }
            $To     = '';
            $type   = 'series';

            if (empty($uid)) {
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
                    if ($type === 'parallel') {
                        // すでに承認作業済みのユーザを除去
                        $exceptUser = [];
                        $SQL = SQL::newSelect('approval_notification');
                        $SQL->addSelect('notification_request_user_id');
                        $SQL->addWhereOpr('notification_type', 'request');
                        $SQL->addWhereOpr('notification_rev_id', $rvid);
                        $SQL->addWhereOpr('notification_entry_id', EID);
                        $SQL->addWhereOpr('notification_blog_id', BID);
                        $all    = $DB->query($SQL->get(dsn()), 'all');
                        foreach ($all as $row) {
                            $exceptUser[] = $row['notification_request_user_id'];
                        }

                        $SQL = SQL::newSelect('workflow_usergroup', 'wug');
                        $SQL->addLeftJoin('usergroup_user', 'usergroup_id', 'usergroup_id', 'ugu', 'wug');
                        $SQL->addLeftJoin('user', 'user_id', 'user_id', 'u', 'ugu');
                        $SQL->addSelect('user_mail');
                        $SQL->addSelect('user_name');
                        $SQL->addWhereOpr('user_status', 'open');
                        $SQL->addWhereOpr('user_id', SUID, '<>', 'AND', 'u');
                        $SQL->addWhereOpr('workflow_blog_id', BID);
                        $SQL->addWhereOpr('workflow_category_id', $cid);
                        $SQL->addWhereNotIn('user_id', $exceptUser, 'AND', 'u');

                        $mail   = $DB->query($SQL->get(dsn()), 'all');
                        $mail_  = [];
                        foreach ($mail as $addr) {
                            $mail_[] = $addr['user_mail'];
                        }
                        $To     = $mail_;

                    // 直列承認
                    } else {
                        $uid = null;
                        $SQL = SQL::newSelect('usergroup_user', 't_usergroup_user');
                        $SQL->addLeftJoin('user', 'user_id', 'user_id', 't_user', 't_usergroup_user');
                        $SQL->addSelect('user_mail');
                        $SQL->addSelect('user_name');
                        $SQL->addWhereOpr('user_status', 'open');

                        if (empty($ugid)) {
                            $lastGroup  = $workflow->getArray('workflow_last_group');
                            $SQL->addWhereIn('usergroup_id', $lastGroup);
                        } else {
                            $SQL->addWhereOpr('usergroup_id', $ugid);
                        }

                        $mail = $DB->query($SQL->get(dsn()), 'all');
                        $mail_ = [];
                        foreach ($mail as $addr) {
                            $mail_[] = $addr['user_mail'];
                        }
                        $To = $mail_;
                    }
                } elseif (editionIsProfessional()) {
                    $uid    = null;
                    $ugid   = null;
                    $SQL    = SQL::newSelect('user');
                    $SQL->addLeftJoin('blog', 'blog_id', 'user_blog_id');
                    if (config('blog_manage_approval') == 'on') {
                        ACMS_Filter::blogTree($SQL, BID, 'self-ancestor');
                    } else {
                        $SQL->addWhereOpr('user_blog_id', BID);
                    }
                    ACMS_Filter::blogStatus($SQL);
                    $SQL->addWhereIn('user_auth', ['editor', 'administrator']);
                    $SQL->addWhereOpr('user_status', 'open');

                    $mail   = $DB->query($SQL->get(dsn()), 'all');
                    $mail_  = [];
                    foreach ($mail as $addr) {
                        $mail_[] = $addr['user_mail'];
                    }
                    $To     = $mail_;
                }
            } else {
                if (editionIsProfessional()) {
                    $ugid = null;
                }
                $To     = ACMS_RAM::userMail($uid);
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
                $Approval->setField('approval', 'request');
                $Approval->setField('approval2', 'request');
                $Approval->setField('approval3', 'request');
                $Approval->setField('approval4', 'request');
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

                $workflowPoint  = approvalWorkflowPublicPoint(BID, CID);
                $currentPoint   = approvalRevisionPublicPoint(EID, $rvid);
                $point          = approvalUserPoint(BID);

                $Approval->setField('approvalType', $type);
                $Approval->setField('workflowPoint', $workflowPoint);
                $Approval->setField('currentPoint', ($currentPoint + $point));
                $Approval->setField('approvalPoint', $workflowPoint - ($currentPoint + $point));

                $subject    = Common::getMailTxt($subjectTpl, $Approval);
                $body       = Common::getMailTxt($bodyTpl, $Approval);

                $to = is_array($To) ? implode(', ', $To) : $To;
                $from = getApprovalFrom(SUID);
                $bcc = implode(', ', configArray('mail_approval_bcc'));

                $send = true;
                if (HOOK_ENABLE) {
                    $data = [
                        'type'      => 'request',
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
                        AcmsLogger::warning('最終依頼の通知メールの送信に失敗しました', Common::exceptionArray($e));
                    }
                }

                // 並列承認
                if ($type == 'parallel') {
                    $ugid   = null;
                    $uid    = null;
                }

                $userGroup = [];
                if ($ugid === '0') {
                    $workflow = loadWorkflow(BID, CID);
                    $userGroup  = $workflow->getArray('workflow_last_group');
                } else {
                    $userGroup[] = $ugid;
                }

                //---------
                // Save DB
                $apid   = $DB->query(SQL::nextval('approval_id', dsn()), 'seq');

                foreach ($userGroup as $approvalUserGroupId) {
                    $SQL    = SQL::newInsert('approval');
                    $SQL->addInsert('approval_id', $apid);
                    $SQL->addInsert('approval_type', 'request');
                    $SQL->addInsert('approval_method', $type);
                    $SQL->addInsert('approval_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
                    $SQL->addInsert('approval_comment', $comment);
                    $SQL->addInsert('approval_receive_usergroup_id', $approvalUserGroupId);
                    $SQL->addInsert('approval_receive_user_id', $uid);
                    $SQL->addInsert('approval_request_usergroup_id', $req_group);
                    $SQL->addInsert('approval_request_user_id', SUID);
                    $SQL->addInsert('approval_revision_id', $rvid);
                    $SQL->addInsert('approval_entry_id', EID);
                    $SQL->addInsert('approval_blog_id', BID);
                    $DB->query($SQL->get(dsn()), 'exec');
                }

                //------------------------
                // Update Revision Status
                $SQL    = SQL::newUpdate('entry_rev');
                $SQL->addUpdate('entry_rev_status', 'in_review');
                // 並列承認
                if ($type == 'parallel') {
                    $currentPoint = approvalRevisionPublicPoint(EID, $rvid);
                    $point        = approvalUserPoint(BID);

                    $SQL->addUpdate('entry_approval_public_point', $currentPoint + $point);
                }
                $SQL->addWhereOpr('entry_id', EID);
                $SQL->addWhereOpr('entry_rev_id', $rvid);
                $DB->query($SQL->get(dsn()), 'exec');

                //---------------------
                // Update Notification

                // 並列承認
                $exceptUser = [];
                if ($type == 'parallel') {
                    $SQL    = SQL::newSelect('approval_notification');
                    $SQL->addSelect('notification_request_user_id');
                    $SQL->addWhereOpr('notification_type', 'request');
                    $SQL->addWhereOpr('notification_rev_id', $rvid);
                    $SQL->addWhereOpr('notification_entry_id', EID);
                    $SQL->addWhereOpr('notification_blog_id', BID);
                    $all    = $DB->query($SQL->get(dsn()), 'all');
                    foreach ($all as $row) {
                        $exceptUser[] = $row['notification_request_user_id'];
                    }
                }

                $SQL    = SQL::newDelete('approval_notification');
                $SQL->addWhereOpr('notification_type', 'reject');
                $SQL->addWhereOpr('notification_entry_id', EID);
                $SQL->addWhereOpr('notification_blog_id', BID);
                $DB->query($SQL->get(dsn()), 'exec');

                $SQL    = SQL::newDelete('approval_notification');
                $SQL->addWhereOpr('notification_rev_id', $rvid);
                $SQL->addWhereOpr('notification_entry_id', EID);
                $SQL->addWhereOpr('notification_blog_id', BID);
                $DB->query($SQL->get(dsn()), 'exec');

                foreach ($userGroup as $notificationUserGroupId) {
                    $SQL = SQL::newInsert('approval_notification');
                    $SQL->addInsert('notification_rev_id', $rvid);
                    $SQL->addInsert('notification_entry_id', EID);
                    $SQL->addInsert('notification_blog_id', BID);
                    $SQL->addInsert('notification_approval_id', $apid);
                    $SQL->addInsert('notification_receive_user_id', $uid);
                    $SQL->addInsert('notification_receive_usergroup_id', $notificationUserGroupId);
                    $SQL->addInsert('notification_request_user_id', SUID);
                    $SQL->addInsert('notification_type', 'request');
                    $SQL->addInsert('notification_except_user_ids', implode(',', $exceptUser));
                    $SQL->addInsert('notification_datetime', date('Y-m-d H:i:s', REQUEST_TIME));

                    $DB->query($SQL->get(dsn()), 'exec');
                }
            }

            $revision = Entry::getRevision(EID, $rvid);
            AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '（' . $revision['entry_rev_memo'] . '）」の承認依頼をしました', [
                'apid' => $apid,
                'eid' => EID,
                'rvid' => $rvid,
                'comment' => $comment,
            ]);
        }
        return $this->Post;
    }
}
