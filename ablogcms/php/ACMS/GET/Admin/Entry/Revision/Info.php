<?php

use Acms\Services\Facades\Entry;

class ACMS_GET_Admin_Entry_Revision_Info extends ACMS_GET_Admin_Entry_Revision
{
    public function get()
    {
        /** @var int $blogId */
        $blogId = BID;
        /** @var int|null $entryId */
        $entryId = EID;
        /** @var int|null $revisionId */
        $revisionId = RVID;
        /** @var int|null $categoryId */
        $categoryId = CID;
        /** @var int $requestTime */
        $requestTime = REQUEST_TIME;

        if (!sessionWithContribution($blogId)) {
            return 'Bad Access.';
        }
        if (is_null($entryId)) {
            return '';
        }
        if (is_null($revisionId)) {
            return '';
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $revision = $this->getRevision($entryId, $revisionId);
        if ($revision === false) {
            page404();
        }
        // 開始日時が未来なら「公開予約（日時指定）」の文脈で扱う（entry_reserve_rev_id とは別概念）
        $isReserve = strtotime($revision['entry_start_datetime']) > $requestTime;

        if (Entry::canChangeEntryRevision($entryId, $revisionId)) {
            $Tpl->add('revisionChange', [
                'canChange' => '1',
                'isReserve' => $isReserve ? '1' : '0',
                'reserveDatetime' => $isReserve ? $revision['entry_start_datetime'] : '',
            ]);
        }
        if (Entry::canUpdateEntryRevision($entryId, $revisionId)) {
            $Tpl->add('edit');
        }
        if (Entry::canViewApprovalHistory($entryId)) {
            $Tpl->add('approvalHistory');
        }

        $auid = $revision['entry_rev_user_id'];
        $author = ACMS_RAM::user($auid);
        $vars = [
            'rvid' => $revisionId,
            'memo' => $revision['entry_rev_memo'],
            'author' => $author['user_name'],
            'icon' => loadUserIcon($auid),
            'status_code' => $revision['entry_rev_status'],
            'isReserve' => $isReserve ? '1' : '0',
            'reserveDatetime' => $isReserve ? $revision['entry_start_datetime'] : '',
            'datetime' => $revision['entry_rev_datetime'],
            'url' => acmsLink([
                'eid' => $entryId,
                'bid' => $blogId,
                'aid' => $this->Get->get('aid') ? (int)$this->Get->get('aid') : null,
                'query' => [
                    'rvid' => $revisionId,
                    'trash' => 'show',
                ],
            ]),
        ];
        if (enableApproval($blogId, $categoryId)) {
            $vars['status'] = $this->getRevisionStatusLabel($revision);
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }

    /**
     * @param array<string, mixed> $revision
     */
    private function getRevisionStatusLabel(array $revision): string
    {
        $status = match ($revision['entry_rev_status']) {
            'in_review' => '承認中',
            'reject' => '承認却下',
            'approved' => '承認済み',
            default => '承認前',
        };
        if ($revision['entry_status'] === 'trash') {
            $status .= ' 削除依頼';
        }
        return $status;
    }
}
