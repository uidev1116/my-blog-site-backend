<?php

class ACMS_GET_Approval_RequestList extends ACMS_GET
{
    public function get()
    {
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $DB     = DB::singleton(dsn());
        $vars   = [];
        $limit  = 20;

        $SQL    = SQL::newSelect('approval');
        $SQL->addWhereOpr('approval_type', 'request');
        $SQL->addWhereOpr('approval_request_user_id', SUID);
        $SQL->addOrder('approval_datetime', 'DESC');

        $Pager  = new SQL_Select($SQL);
        $Pager->setSelect('*', 'approval_amount', null, 'count');
        if (!$pageAmount = intval($DB->query($Pager->get(dsn()), 'one'))) {
            $Tpl->add('index#notFound');
            $Tpl->add(null, $vars);
            return $Tpl->get();
        }

        $vars   += $this->buildPager(
            PAGE,
            $limit,
            $pageAmount,
            config('admin_pager_delta'),
            config('admin_pager_cur_attr'),
            $Tpl,
            [],
            ['admin' => ADMIN]
        );

        $SQL->setLimit($limit, (PAGE - 1) * $limit);
        $all = $DB->query($SQL->get(dsn()), 'all');

        foreach ($all as $i => $row) {
            //-----------
            // 承認フロー
            $SQL    = SQL::newSelect('approval');
            $SQL->addWhereOpr('approval_revision_id', $row['approval_revision_id']);
            $SQL->addWhereOpr('approval_entry_id', $row['approval_entry_id']);
            $SQL->addWhereOpr('approval_blog_id', $row['approval_blog_id']);
            $SQL->addWhereOpr('approval_type', 'comment', '<>');
            $SQL->setOrder('approval_datetime', 'DESC');
            if (!($history = $DB->query($SQL->get(dsn()), 'all'))) {
                return '';
            }

            foreach ($history as $row2) {
                //--------------
                // 操作ユーザ情報
                $reqUserField   = loadUser($row2['approval_request_user_id']);
                $reqUser        = $this->buildField($reqUserField, $Tpl, ['requestUser', 'approval:loop']);

                $Tpl->add(['requestUser', 'history:loop', 'approval:loop'], $reqUser);

                //------------------
                // 担当者 承認依頼のみ
                if ($row2['approval_type'] === 'request') {
                    $receive = null;
                    if (!!$row2['approval_receive_user_id']) {
                        $receive['userOrGroupp'] = ACMS_RAM::userName($row2['approval_receive_user_id']);
                    } elseif (!!$row2['approval_receive_usergroup_id']) {
                        $SQL    = SQL::newSelect('usergroup');
                        $SQL->addSelect('usergroup_name');
                        $SQL->addWhereOpr('usergroup_id', $row2['approval_receive_usergroup_id']);
                        $groupName = $DB->query($SQL->get(dsn()), 'one');
                        $receive['userOrGroupp'] = $groupName;
                    }
                    if ($receive) {
                        $Tpl->add(['receiveUser', 'history:loop', 'approval:loop'], $receive);
                    }
                }

                //---------
                // 承認情報
                $approvalField  = new Field();
                foreach ($row2 as $key => $val) {
                    $key_       = substr($key, strlen('approval_'));
                    $approvalField->add($key_, $val);
                }

                $SQL    = SQL::newSelect('entry_rev');
                $SQL->addSelect('entry_status');
                $SQL->addWhereOpr('entry_rev_id', $row['approval_revision_id']);
                $SQL->addWhereOpr('entry_id', $row['approval_entry_id']);
                $SQL->addWhereOpr('entry_blog_id', $row['approval_blog_id']);
                if ($revStatus = $DB->query($SQL->get(dsn()), 'one')) {
                    if ($approvalField->get('type') === 'request' && $revStatus === 'trash') {
                        $approvalField->set('type', 'trash');
                    }
                }
                $approval  = $this->buildField($approvalField, $Tpl, ['history:loop', 'approval:loop']);

                $Tpl->add(['history:loop', 'approval:loop'], $approval);
            }

            //-------------
            // last status
            $last = array_shift($history);
            $Tpl->add('type:touch#' . $last['approval_type']);

            $loop = [
                'i' => $i,
            ];

            $SQL = SQL::newSelect('entry_rev');
            $SQL->addWhereOpr('entry_id', $row['approval_entry_id']);
            $SQL->addWhereOpr('entry_rev_id', $row['approval_revision_id']);
            $rev = $DB->query($SQL->get(dsn()), 'row');

            if ($rev) {
                $loop['title'] = $rev['entry_title'];
                $loop['version'] = $rev['entry_rev_memo'];
                $loop['rvid'] = $row['approval_revision_id'];
                $loop['eid'] = $row['approval_entry_id'];
                $loop['url'] = acmsLink([
                    'bid' => $row['approval_blog_id'],
                    'eid' => $row['approval_entry_id'],
                    'tpl' => 'ajax/revision/preview.html',
                    'query' => [
                        'rvid' => $row['approval_revision_id'],
                    ],
                ], false, false, true);
            }
            $Tpl->add('approval:loop', $loop);
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
