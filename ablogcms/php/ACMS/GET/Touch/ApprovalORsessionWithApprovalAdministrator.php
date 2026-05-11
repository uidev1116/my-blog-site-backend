<?php

use Acms\Services\Facades\Entry;

class ACMS_GET_Touch_ApprovalORsessionWithApprovalAdministrator extends ACMS_GET
{
    function get()
    {
        return Entry::requiresApproval(BID, CID) ? '' : $this->tpl;
    }
}
