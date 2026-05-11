<?php

class ACMS_POST_Approval_NotificationDelete extends ACMS_POST
{
    function post()
    {
        $DB         = DB::singleton(dsn());
        $Approval   = $this->extract('approval');

        if (
            0
            || !($rvid  = $Approval->get('rvid'))
            || !($bid   = $Approval->get('bid'))
            || !($eid   = $Approval->get('eid'))
            || !($apid  = $Approval->get('apid'))
        ) {
            return false;
        }

        $SQL = SQL::newSelect('approval_notification');
        $SQL->addSelect('notification_except_user_ids');
        $SQL->addWhereOpr('notification_rev_id', $rvid);
        $SQL->addWhereOpr('notification_entry_id', $eid);
        $SQL->addWhereOpr('notification_blog_id', $bid);
        $SQL->addWhereOpr('notification_approval_id', $apid);

        if ($except = $DB->query($SQL->get(dsn()), 'row')) {
            $exceptAry = array_map('intval', explode(',', $except['notification_except_user_ids']));
            if (!array_search(SUID, $exceptAry, true)) {
                array_push($exceptAry, SUID);
                $exceptAry = array_filter($exceptAry);

                $SQL = SQL::newUpdate('approval_notification');
                $SQL->addUpdate('notification_except_user_ids', implode(',', $exceptAry));
                $SQL->addWhereOpr('notification_rev_id', $rvid);
                $SQL->addWhereOpr('notification_entry_id', $eid);
                $SQL->addWhereOpr('notification_blog_id', $bid);
                $SQL->addWhereOpr('notification_approval_id', $apid);
                $DB->query($SQL->get(dsn()), 'exec');

                $revision = Entry::getRevision($eid, $rvid);
                AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '（' . $revision['entry_rev_memo'] . '）」の承認通知を削除しました', [
                    'apid' => $apid,
                    'eid' => $eid,
                    'rvid' => $rvid,
                ]);
            }
        }

        return $this->Post;
    }
}
