<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Entry_DirectEdit_Disable extends ACMS_POST
{
    public function post()
    {
        if (Login::isLoggedIn()) {
            $session =& Field::singleton('session');
            $session->set('entry_direct_edit', 'disable');

            AcmsLogger::info('ダイレクト編集を無効化させました');

            $this->redirect(REQUEST_URL);
        }
        return $this->Post;
    }
}
