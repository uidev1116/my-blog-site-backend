<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Form_Item extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Form_Item モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $Tpl->add(null, $this->buildField($this->Post, $Tpl));

        return $Tpl->get();
    }
}
