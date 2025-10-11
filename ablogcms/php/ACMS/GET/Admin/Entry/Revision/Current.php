<?php

class ACMS_GET_Admin_Entry_Revision_Current extends ACMS_GET_Admin_Entry_Revision
{
    public function get()
    {
        if (!sessionWithContribution(BID)) {
            return 'Bad Access.';
        }
        if (!defined('EID')) {
            return '';
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $vars = [];

        $currentRvid = $this->getCurrentRevisionId(EID);
        $currentRevision = $this->getRevision(EID, $currentRvid); // @phpstan-ignore-line
        $editVersion = $this->getRevision(EID, RVID); // @phpstan-ignore-line
        $count = $this->countRevisions(EID);

        if ($currentRvid > 1) {
            $vars['currentVersion'] = $currentRvid;
            if (isset($currentRevision['entry_rev_memo'])) {
                $vars['currentVersionName'] = $currentRevision['entry_rev_memo'];
            }
        } else {
            $Tpl->add('notExistCurrentVersion');
        }
        if (RVID > 1 && $editVersion) { // @phpstan-ignore-line
            $vars['editVersion'] = RVID;
            if (isset($editVersion['entry_rev_memo'])) {
                $vars['editVersionName'] = $editVersion['entry_rev_memo'];
            }
            if (isset($editVersion['entry_rev_status'])) {
                $vars['rev_status'] = $editVersion['entry_rev_status'];
            }
        }
        $vars['confirmUrl'] = acmsLink([
            'bid' => BID,
            'eid' => EID,
            'cid' => CID,
            'aid' => $this->Get->get('aid') ? (int)$this->Get->get('aid') : null,
            'query' => [
                'rvid' => RVID,
                'aid' => $this->Get->get('aid'),
            ],
        ]);
        if ($count > 0) {
            $vars['amount'] = $count;
        } else {
            $Tpl->add('notFound');
        }
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
