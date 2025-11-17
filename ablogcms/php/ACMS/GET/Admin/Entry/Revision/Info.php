<?php

class ACMS_GET_Admin_Entry_Revision_Info extends ACMS_GET_Admin_Entry_Revision
{
    public function get()
    {
        if (!sessionWithContribution(BID)) {
            return 'Bad Access.';
        }
        if (!EID) {
            return '';
        }
        if (!RVID) {
            return '';
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $revision = $this->getRevision(EID, RVID);
        if (!$revision) {
            page404();
        }
        $isReserve = strtotime($revision['entry_start_datetime']) > REQUEST_TIME;
        $vars = [];

        $sql = SQL::newSelect('entry');
        $sql->setSelect('entry_current_rev_id');
        $sql->addWhereOpr('entry_id', EID);
        $currentRevId = DB::query($sql->get(dsn()), 'one');

        if (RVID === 1) {
        } elseif (RVID === intval($currentRevId)) {
        } elseif (roleAvailableUser()) {
            // ロール管理下のユーザー
            if (enableApproval(BID, $revision['entry_category_id']) && sessionWithApprovalAdministrator(BID, $revision['entry_category_id']) && isset($revision['entry_rev_status']) && $revision['entry_rev_status'] === 'approved') {
                // 承認機能が有効でかつ承認済みの場合
                $Tpl->add('revisionChange', [
                    'canChange' => '1',
                    'isReserve' => $isReserve ? '1' : '0',
                    'reserveDatetime' => $isReserve ? $revision['entry_start_datetime'] : '',
                ]);
            } elseif (!enableApproval(BID, $revision['entry_category_id']) && roleAuthorization('entry_edit', BID, EID)) {
                // 承認機能が無効でかつ編集権限がある場合
                $Tpl->add('revisionChange', [
                    'canChange' => '1',
                    'isReserve' => $isReserve ? '1' : '0',
                    'reserveDatetime' => $isReserve ? $revision['entry_start_datetime'] : '',
                ]);
            }
        } elseif (enableApproval(BID, $revision['entry_category_id'])) {
            // 承認機能が有効
            if (sessionWithApprovalAdministrator(BID, $revision['entry_category_id']) && isset($revision['entry_rev_status']) && $revision['entry_rev_status'] === 'approved') {
                $Tpl->add('revisionChange', [
                    'canChange' => '1',
                    'isReserve' => $isReserve ? '1' : '0',
                    'reserveDatetime' => $isReserve ? $revision['entry_start_datetime'] : '',
                ]);
            }
        } else {
            do {
                if (!sessionWithCompilation(BID)) {
                    if (!sessionWithContribution(BID)) {
                        break;
                    }
                    if (SUID != ACMS_RAM::entryUser(EID)) {
                        break;
                    }
                }
                $Tpl->add('revisionChange', [
                    'canChange' => '1',
                    'isReserve' => $isReserve ? '1' : '0',
                    'reserveDatetime' => $isReserve ? $revision['entry_start_datetime'] : '',
                ]);
            } while (false);
        }
        if (sessionWithApprovalAdministrator(BID, $revision['entry_category_id']) || intval($revision['entry_rev_user_id']) === SUID) {
            $Tpl->add('edit');
        }
        if ($revision) {
            $auid = $revision['entry_rev_user_id'];
            $author = ACMS_RAM::user($auid);

            $status = '承認前';
            switch ($revision['entry_rev_status']) {
                case 'in_review':
                    $status = '承認中';
                    break;
                case 'reject':
                    $status = '承認却下';
                    break;
                case 'approved':
                    $status = '承認済み';
                    break;
                default:
                    $status = '承認前';
                    break;
            }
            if ($revision['entry_status'] === 'trash') {
                $status .= ' 削除依頼';
            }

            $vars = [
                'rvid' => RVID,
                'memo' => $revision['entry_rev_memo'],
                'author' => $author['user_name'],
                'icon' => loadUserIcon($auid),
                'status_code' => $revision['entry_rev_status'],
                'isReserve' => $isReserve ? '1' : '0',
                'reserveDatetime' => $isReserve ? $revision['entry_start_datetime'] : '',
                'datetime' => $revision['entry_rev_datetime'],
                'url' => acmsLink([
                    'eid' => EID,
                    'bid' => BID,
                    'aid' => $this->Get->get('aid', null),
                    'query' => [
                        'rvid' => RVID,
                        'trash' => 'show',
                    ],
                ]),
            ];
            if (enableApproval(BID, CID)) {
                $vars['status'] = $status;
            }
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
