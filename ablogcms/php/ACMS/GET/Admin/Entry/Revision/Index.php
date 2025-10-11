<?php

class ACMS_GET_Admin_Entry_Revision_Index extends ACMS_GET_Admin_Entry_Revision
{
    public function get()
    {
        if (!sessionWithContribution()) {
            return 'Bad Access.';
        }
        if (!defined('EID')) {
            return '';
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $currentRvid = $this->getCurrentRevisionId(EID);
        $reserveRvid = $this->getReserveRevisionId(EID);
        $revisionAry = $this->getRevisionsData(EID);
        $vars = [
            'current_rvid' => intval($currentRvid) > 1 ? $currentRvid : null,
            'reserve_rev_id' => $reserveRvid,
            'view_rvid' => RVID,
        ];

        if (empty($revisionAry)) {
            $tpl->add('revision#notFound');
            $tpl->add(null, $vars);
            return $tpl->get();
        }
        $this->build($tpl, $revisionAry, $currentRvid, $reserveRvid);
        $tpl->add(null, $vars);

        return $tpl->get();
    }

    protected function build($tpl, $revisionAry, $currentRvid, $reserveRvid)
    {
        foreach ($revisionAry as $rev) {
            $auid = $rev['entry_rev_user_id'];
            $author = ACMS_RAM::user($auid);
            $rvid = intval($rev['entry_rev_id']);

            $revision = [
                'rvid' => $rvid,
                'current_rvid' => intval($currentRvid) > 1 ? $currentRvid : null,
                'reserve_rvid' => $reserveRvid,
                'view_rvid' => RVID,
                'memo' => $rev['entry_rev_memo'],
                'status' => $rev['entry_status'],
                'rev_status' => $rev['entry_rev_status'],
                'author' => $author['user_name'] ?? '存在しないユーザー',
                'icon' => loadUserIcon($auid),
                'datetime' => $rev['entry_rev_datetime'],
                'start_datetime' => $rev['entry_start_datetime'],
                'end_datetime' => $rev['entry_end_datetime'],
                'confirmUrl' => acmsLink([
                    'bid' => BID,
                    'eid' => EID,
                    'cid' => CID,
                    'aid' => $this->Get->get('aid') ? (int)$this->Get->get('aid') : null,
                    'query' => [
                        'rvid' => $rev['entry_rev_id'],
                        'aid' => $this->Get->get('aid'),
                    ],
                ]),
            ];
            if (sessionWithApprovalAdministrator(BID, $rev['entry_category_id']) || $auid === SUID) {
                $revision['editUrl'] = acmsLink([
                    'bid' => BID,
                    'eid' => EID,
                    'admin' => 'entry_editor',
                    'query' => [
                        'rvid' => $rev['entry_rev_id'],
                    ],
                ]);

                if (
                    1
                    && $rvid !== 1
                    && $currentRvid !== $rvid
                    && $reserveRvid !== $rvid
                ) {
                    $tpl->add(['delete', 'revision:loop'], [
                        '_rvid' => $rvid,
                    ]);
                }
            }
            if (empty($rev['entry_rev_status'])) {
                $rev['entry_rev_status'] = 'none';
            }
            if ($rev['entry_status'] === 'trash' && $rev['entry_rev_status'] === 'in_review') {
                $tpl->add(['touch:rev_status#trash', 'revision:loop']);
            } else {
                $tpl->add(['touch:rev_status#' . $rev['entry_rev_status'], 'revision:loop']);
            }
            $tpl->add(['touch:status#' . $rev['entry_status'], 'revision:loop']);
            $tpl->add('revision:loop', $revision);
        }
    }
}
