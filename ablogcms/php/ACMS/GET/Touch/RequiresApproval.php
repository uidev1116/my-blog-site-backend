<?php

use Acms\Services\Facades\Entry;

class ACMS_GET_Touch_RequiresApproval extends ACMS_GET
{
    public function get()
    {
        if (Entry::requiresApproval(BID, CID)) {
            return $this->tpl;
        }
        return '';
    }
}
