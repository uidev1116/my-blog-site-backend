<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Session;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Mailer\Transport\GoogleApi;

class ACMS_GET_Admin_Config_GmailSmtp_Callback extends ACMS_GET
{
    public function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        try {
            $api = Application::make('mailer.google.smtp.api');
            assert($api instanceof GoogleApi);

            $session = Session::handle();
            $setid = $session->get('mailer.google.smtp.api.setid', null);

            $api->init(BID, $setid);
            $client = $api->getClient();
            $urlContext = [
                'bid' => BID,
                'admin' => 'config_mail',
            ];
            if ($setid) {
                $urlContext['query'] = [
                    'setid' => $setid,
                ];
            }
            $base_uri = acmsLink($urlContext);
            $code = $this->Get->get('code');
            $client->fetchAccessTokenWithAuthCode($code);
            $accessToken = $client->getAccessToken();
            $api->updateAccessToken(json_encode($accessToken));

            AcmsLogger::info('Gmail API のOAuth認証に成功しました。');

            redirect($base_uri);
        } catch (\Exception $e) {
            AcmsLogger::error('Gmail API のOAuth認証に失敗しました。', Common::exceptionArray($e));
        }
        return '';
    }
}
