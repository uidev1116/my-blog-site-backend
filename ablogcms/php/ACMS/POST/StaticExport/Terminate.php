<?php

class ACMS_POST_StaticExport_Terminate extends ACMS_POST
{
    /**
     * Run
     */
    public function post()
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $service = App::make('static-export.terminate-check');
        $service->terminate();

        AcmsLogger::info('静的書き出しを停止させました', [
            'bid' => BID,
        ]);

        sleep(3);
        $this->redirect(HTTP_REQUEST_URL);
    }
}
