<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Cart_Result extends ACMS_GET_Shop2_Cart_List
{
    public function get()
    {
        Deprecated::once('Shop2_Cart_Result モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $this->initPrivateVars();

        $SESSION = $this->openSession();
        $TEMP = $SESSION->getArray('portrait_cart');
        $Tpl = $this->buildList($TEMP);

        return $Tpl;
    }
}
