<?php

class ACMS_GET_Admin_Errors extends ACMS_GET_Admin
{
    function get()
    {
        if (!sessionWithContribution()) {
            return '';
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $session =& Field::singleton('session');

        $errors = false;
        if ($this->Post->isChildExists('errors')) {
            $errors = $this->Post->getChild('errors');
        } elseif ($session->isChildExists('errors')) {
            $errors = $session->getChild('errors');
            $session->removeChild('errors');
        }
        if (empty($errors)) {
            return '';
        }
        $vars = $this->buildField($errors, $Tpl);
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
