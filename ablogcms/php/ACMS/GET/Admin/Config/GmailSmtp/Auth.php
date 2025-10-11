<?php

use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Application;
use Acms\Services\Mailer\Transport\GoogleApi;

class ACMS_GET_Admin_Config_GmailSmtp_Auth extends ACMS_GET
{
    public function get()
    {
        if (!sessionWithAdministration()) {
            return '';
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        try {
            $api = Application::make('mailer.google.smtp.api');
            assert($api instanceof GoogleApi);
            $setid = intval($this->Get->get('setid'));
            if (empty($setid)) {
                $setid = null;
            }
            $api->init(BID, $setid);
            $client = $api->getClient();
            $authorized = 'false';
            if ($client->getAccessToken() && $client->getRefreshToken() && !$client->isAccessTokenExpired()) {
                $authorized = 'true';
            }
            $Tpl->add(null, [
                'authorized' => $authorized,
                'scopes' => $api->getScopes(),
            ]);
        } catch (\Exception $e) {
            AcmsLogger::error('Gmail API のアクセストークンの取得に失敗しました。', Common::exceptionArray($e));
        }
        return $Tpl->get();
    }
}
