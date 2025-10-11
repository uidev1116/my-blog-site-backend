<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Cart_Notify extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Cart_Notify モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $step   = $this->Post->get('step', 'apply');
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $added  = $this->session->get('added');

        if (!empty($added)) {
            if (
                1
                and config('shop_tax_calculate') !== 'intax'
                and isset($added[$this->item_price])
            ) {
                //$added[$this->item_price] -= $added[$this->item_price.'#tax'];
            }
            $Tpl->add('added', $this->sanitize($added));
            $this->session->delete('added');
            $this->session->save();
        } elseif (!empty($_SESSION['deleted'])) {
            $Tpl->add('deleted', $this->sanitize($this->session->get('deleted')));
            $this->session->delete('deleted');
            $this->session->save();
        } else {
            return '';
        }

        return $Tpl->get();
    }
}
