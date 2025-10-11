<?php

class ACMS_GET_Admin_Fix extends ACMS_GET_Admin
{
    function fix(&$Tpl, $block)
    {
        return true;
    }

    public function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];
        $root   = [
            'indexUrl'  => acmsLink([
                'bid'   => BID,
                'admin' => 'fix_index',
            ]),
        ];

        $step   = $this->Post->get('step', '');
        $msg    = $this->Post->get('message');

        $block  = !(empty($step) or is_bool($step)) ? ['step#' . $step] : ['step'];
        $this->fix($Tpl, $block);

        $vars   += $this->buildField($this->Post, $Tpl, $block);
        $this->Post->reset(true);

        if ($this->Post->isValidAll()) {
            $step   = $this->Post->get('step', $step);
        } else {
            $this->Post->delete('step');
            $step   = '';
            $Tpl->add('msg#error');
        }
        $Tpl->add('message#' . $msg, $vars);

        $Tpl->add($block, $vars);
        $Tpl->add(null, $root);

        return $Tpl->get();
    }
}
