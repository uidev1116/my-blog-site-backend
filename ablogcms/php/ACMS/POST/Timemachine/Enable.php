<?php

use Acms\Services\Facades\Preview;

class ACMS_POST_Timemachine_Enable extends ACMS_POST
{
    public function post()
    {
        if (!timemachineAuth()) {
            die403();
        }
        $session =& Field::singleton('session');
        $date = $this->Post->get('date');
        $time = $this->Post->get('time');
        $ruleId = null;
        if ($this->Post->isExists('rule')) {
            $ruleId = (int)$this->Post->get('rule', '0');
        }
        $fakeUa = $this->Post->get('preview_fake_ua');
        $token = $this->Post->get('preview_token');
        $datetime = $date . ' ' . $time;
        if (
            preg_match(REGEXP_VALID_DATETIME, $datetime) &&
            $fakeUa &&
            $token
        ) {
            $session->set('timemachine_datetime', $datetime);
            if ($ruleId !== null) {
                $session->set('timemachine_rule_id', $ruleId);
            }
            Preview::startPreviewMode($fakeUa, $token);

            AcmsLogger::info('タイムマシンモードを開始しました');
            Common::setSafeHeadersWithoutCache(200, 'text/plain');
            die('OK');
        }
        Common::setSafeHeadersWithoutCache(200, 'text/plain');
        die('NG');
    }
}
