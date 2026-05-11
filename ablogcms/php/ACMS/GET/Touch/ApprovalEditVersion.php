<?php

use Acms\Services\Facades\Entry;

class ACMS_GET_Touch_ApprovalEditVersion extends ACMS_GET
{
    function get()
    {
        if (!Entry::requiresApproval(BID, CID) || (RVID && RVID > 1)) {
            return $this->tpl;
        }
        return '';
    }
}
