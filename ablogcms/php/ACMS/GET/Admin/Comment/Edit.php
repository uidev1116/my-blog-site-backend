<?php

use Acms\Services\Facades\Login;

class ACMS_GET_Admin_Comment_Edit extends ACMS_GET_Admin
{
    function get()
    {
        if (!EID) {
            return '';
        }
        if (ADMIN) {
            return '';
        }
        if ('on' <> config('comment')) {
            return '';
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];

        $step       = $this->Post->get('step', 'apply');
        $action     = $this->Post->get('action', 'insert');
        $Comment    =& $this->Post->getChild('comment');
        if ($this->Post->isNull()) {
            if ('reply' == ALT) {
                $Comment->setField('reply_id', CMID);
                $Comment->setField('title', ACMS_RAM::commentTitle(CMID));
                $Tpl->add('header#reply');
            } else {
                $Comment->setField('title', ACMS_RAM::entryTitle(EID));
                $Tpl->add('header#insert');
            }
            if (Login::isLoggedIn()) {
                /** @var int|null $sessionUserId */
                $sessionUserId = SUID;
                assert(is_int($sessionUserId)); // ログインしていることが保証されている
                $Comment->setField('name', ACMS_RAM::userName($sessionUserId));
                $Comment->setField('mail', ACMS_RAM::userMail($sessionUserId));
                $Comment->setField('url', ACMS_RAM::userUrl($sessionUserId));
            }
        }

        $rootBlock  = 'step#' . $step;

        $Tpl->add(['msg#' . $action, $rootBlock]);
        $Tpl->add(['action#' . $action, $rootBlock]);

        $vars   += $this->buildField($Comment, $Tpl, $rootBlock, 'comment');
        $vars['step']   = $step;
        $vars['action'] = $action;

        $Tpl->add($rootBlock, $vars);

        return $Tpl->get();
    }
}
