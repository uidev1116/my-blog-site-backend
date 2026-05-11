<?php

class ACMS_POST_Approval_RejectRequest extends ACMS_POST
{
    function post()
    {
        $DB = DB::singleton(dsn());
        $Approval = $this->extract('approval');

        if (!($rvid = $Approval->get('rvid'))) {
            return false;
        }
        if (!editionIsEnterprise()) {
            return false;
        }
        $workflow = loadWorkflow(BID, CID);
        $type = $workflow->get('workflow_type');
        if ($type !== 'parallel') {
            return false;
        }
        $workflowPoint = approvalWorkflowRejectPoint(BID, CID);
        $currentPoint = approvalRevisionRejectPoint(EID, $rvid);
        $point = approvalUserPoint(BID);

        if ($workflowPoint <= $currentPoint + $point) {
            $Approval->setMethod('approvalPoint', 'overpoint', false);
        }
        if (!sessionWithApprovalRejectRequest()) {
            $Approval->setMethod('approvalRequest', 'operator', false);
        }
        $Approval->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $comment    = $Approval->get('request_comment');
            $rec_user   = null;

            //---------
            // Save DB
            $apid   = $DB->query(SQL::nextval('approval_id', dsn()), 'seq');

            $SQL    = SQL::newInsert('approval');
            $SQL->addInsert('approval_id', $apid);
            $SQL->addInsert('approval_type', 'rejectRequest');
            $SQL->addInsert('approval_method', $type);
            $SQL->addInsert('approval_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $SQL->addInsert('approval_comment', $comment);
            $SQL->addInsert('approval_receive_usergroup_id', null);
            $SQL->addInsert('approval_receive_user_id', null);
            $SQL->addInsert('approval_request_usergroup_id', null);
            $SQL->addInsert('approval_request_user_id', SUID);
            $SQL->addInsert('approval_revision_id', $rvid);
            $SQL->addInsert('approval_entry_id', EID);
            $SQL->addInsert('approval_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            //------------------------
            // Update Revision Status
            $SQL    = SQL::newUpdate('entry_rev');
            $SQL->addUpdate('entry_rev_status', 'in_review');
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

            //---------------------
            // Update Notification
            $exceptUser     = [];
            $exceptUser[]   = SUID;
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
            $SQL    = SQL::newUpdate('approval_notification');
            $SQL->addUpdate('notification_except_user_ids', implode(',', $exceptUser));
            $SQL->addWhereOpr('notification_type', 'request');
            $SQL->addWhereOpr('notification_rev_id', $rvid);
            $SQL->addWhereOpr('notification_entry_id', EID);
            $SQL->addWhereOpr('notification_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            $revision = Entry::getRevision(EID, $rvid);
            AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '（' . $revision['entry_rev_memo'] . '）」の承認却下依頼をしました', [
                'apid' => $apid,
                'eid' => EID,
                'rvid' => $rvid,
                'comment' => $comment,
            ]);
        }
        return $this->Post;
    }
}
