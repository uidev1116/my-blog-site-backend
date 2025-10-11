<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_POST_Shop2_Form_Checkout extends ACMS_POST_Shop2
{
    function post()
    {
        Deprecated::once('Shop2_Form_Checkout モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        if (!$this->inventoryCheck()) {
            return $this->Post;
        }

        if ($this->alreadySubmit()) {
            $this->screenTrans();
        }

        if (!!ACMS_SID || $this->config->get('shop_order_login') != 'required') {
            $this->Post->set('step', 'address');
            return $this->Post;
        } else {
            $this->setReferer($this->orderTpl, 'address');
            $this->screenTrans($this->loginTpl);
        }
    }

    function inventoryCheck()
    {
        $TEMP = $this->openCart(BID);

        foreach ($TEMP as $hash => $row) {
            if (
                0
                or !isset($row[$this->item_price])
                or !isset($row[$this->item_qty])
            ) {
                continue;
            }

            $efield = loadEntryField(intval($row[$this->item_id]));
            $item_stock = $efield->get($this->item_sku);
            if (intval($item_stock) > 0) {
                if (intval($item_stock) < intval($row[$this->item_qty])) {
                    return false;
                }
            }
        }
        return true;
    }
}
