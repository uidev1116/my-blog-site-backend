<?php

class ACMS_GET_Approval_Notification extends ACMS_GET
{
    function notificationCount()
    {
        return Approval::notificationCount();
    }

    function buildSql()
    {
        return Approval::buildSql();
    }

    public function get()
    {
        if (!editionWithProfessional()) {
            return '';
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $DB     = DB::singleton(dsn());
        $vars   = [];

        $SQL    = $this->buildSql();

        if (!($all = $DB->query($SQL->get(dsn()), 'all'))) {
            $Tpl->add('approval#notFound');
            return $Tpl->get();
        }

        $empty = true;
        foreach ($all as $row) {
            $exceptUsers = explode(',', $row['notification_except_user_ids']);
            if (
                in_array(strval(SUID), $exceptUsers, true)
            ) {
                continue;
            }
            if ($row['notification_type'] == 'reject') {
                $requestUser = $row['notification_request_user_id'];

                $SQL    = SQL::newSelect('approval');
                $SQL->addWhereOpr('approval_type', 'request');
                $SQL->addWhereOpr('approval_revision_id', $row['notification_rev_id']);
                $SQL->addWhereOpr('approval_entry_id', $row['notification_entry_id']);
                $SQL->addWhereOpr('approval_request_user_id', SUID);
                $SQL->addWhereOpr('approval_datetime', $row['notification_datetime'], '<');

                if (
                    0
                    || !$DB->query($SQL->get(dsn()), 'row')
                    || ACMS_RAM::entryStatus($row['notification_entry_id']) === 'close'
                    || ACMS_RAM::entryStatus($row['notification_entry_id']) === 'trash'
                ) {
                    continue;
                }
            }

            //--------------
            // 操作ユーザ情報
            $reqUserField   = loadUser($row['approval_request_user_id']);
            $reqUser        = $this->buildField($reqUserField, $Tpl, ['requestUser', 'approval:loop']);
            $Tpl->add(['requestUser', 'approval:loop'], $reqUser);

            //------------------
            // 担当者 承認依頼のみ
            $receive = [];
            if ($row['approval_type'] === 'request') {
                if (!!$row['approval_receive_user_id']) {
                    $receive['userOrGroup'] = ACMS_RAM::userName($row['approval_receive_user_id']);
                } elseif (!!$row['approval_receive_usergroup_id']) {
                    $SQL    = SQL::newSelect('usergroup');
                    $SQL->addSelect('usergroup_name');
                    $SQL->addWhereOpr('usergroup_id', $row['approval_receive_usergroup_id']);
                    $groupName = $DB->query($SQL->get(dsn()), 'one');
                    $receive['userOrGroup'] = $groupName;
                }
                $Tpl->add(['receiveUser', 'approval:loop'], $receive);
            }

            //---------
            // 承認情報
            $approvalField  = new Field();
            foreach ($row as $key => $val) {
                $key_       = substr($key, strlen('approval_'));
                $approvalField->add($key_, $val);
            }

            $SQL    = SQL::newSelect('entry_rev');
            $SQL->addSelect('entry_status');
            $SQL->addWhereOpr('entry_rev_id', $row['notification_rev_id']);
            $SQL->addWhereOpr('entry_id', $row['notification_entry_id']);
            $SQL->addWhereOpr('entry_blog_id', $row['notification_blog_id']);
            if ($revStatus = $DB->query($SQL->get(dsn()), 'one')) {
                if ($approvalField->get('type') === 'request' && $revStatus === 'trash') {
                    $approvalField->set('type', 'trash');
                }
            }

            $approval   = $this->buildField($approvalField, $Tpl, ['approval:loop']);
            $approval   += $receive;
            $approval['rev_id']         = $row['notification_rev_id'];
            $approval['entry_id']       = $row['notification_entry_id'];
            $approval['blog_id']        = $row['notification_blog_id'];
            $approval['approval_id']    = $row['notification_approval_id'];
            $approval['datetime']       = $row['notification_datetime'];

            $approval['url'] = acmsLink([
                'bid'           => $row['approval_blog_id'],
                'eid'           => $row['notification_entry_id'],
                'tpl'           => 'ajax/revision/preview.html',
                'query'         => [
                    'rvid'  => $row['notification_rev_id'],
                ],
            ], false, false, true);

            $Tpl->add('approval:loop', $approval);
            $empty = false;
        }
        if ($empty) {
            $Tpl->add('approval#notFound');
            return $Tpl->get();
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
