<?php

use Acms\Services\Facades\Preview;
use Acms\Services\Facades\Common;

class ACMS_POST_Timemachine_Disable extends ACMS_POST
{
    function post()
    {
        $session =& Field::singleton('session');
        $session->delete('timemachine_datetime');
        $session->delete('timemachine_rule_id');
        Preview::endPreviewMode();

        AcmsLogger::info('タイムマシンモードを終了しました');

        Common::setSafeHeadersWithoutCache(200, 'text/plain');
        die('OK');
    }
}
