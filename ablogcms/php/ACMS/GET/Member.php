<?php

use Acms\Services\Facades\Login;

class ACMS_GET_Member extends ACMS_GET
{
    /**
     * Main
     *
     * @return string
     */
    public function get(): string
    {
        $this->init();
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $this->buildTpl($tpl);

        return $tpl->get();
    }

    /**
     * 初期処理
     *
     * @return void
     */
    protected function init(): void
    {
        if (Login::isLoggedIn()) {
            page404();
        }
    }

    /**
     * テンプレート組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildTpl(Template $tpl): void
    {
        $vars = $this->buildField($this->Post, $tpl);
        $tpl->add(null, $vars);
    }
}
