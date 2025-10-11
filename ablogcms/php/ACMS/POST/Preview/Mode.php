<?php

class ACMS_POST_Preview_Mode extends ACMS_POST
{
    function post()
    {
        $fakeUa = $this->Post->get('preview_fake_ua', false);
        $token = $this->Post->get('preview_token', false);

        Preview::startPreviewMode($fakeUa, $token);

        Common::setSafeHeadersWithoutCache(200, 'text/plain');
        die('OK');
    }
}
