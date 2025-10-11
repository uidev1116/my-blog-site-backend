<?php

class ACMS_GET_Admin_ActionResult extends ACMS_GET
{
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        if ($this->Post->isNull()) {
            // Getでアクセスされた場合
            return $tpl->render([]);
        }

        if (!$this->Post->isValidAll()) {
            return $tpl->render([
                'status' => 'error',
            ]);
        }

        if ($this->Post->isChildExists('errors')) {
            return $tpl->render([
                'status' => 'error',
            ]);
        }

        return $tpl->render([
            'status' => 'success'
        ]);
    }
}
