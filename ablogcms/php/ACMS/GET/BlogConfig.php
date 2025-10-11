<?php

use Acms\Services\Facades\Config;
use Acms\Services\Facades\Template as Tpl;

class ACMS_GET_BlogConfig extends ACMS_GET
{
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $config = Config::loadDefaultField();
        $config->overload(Config::loadBlogConfig(BID));
        $tpl->add(null, Tpl::buildField($config, $tpl));
        return $tpl->get();
    }
}
