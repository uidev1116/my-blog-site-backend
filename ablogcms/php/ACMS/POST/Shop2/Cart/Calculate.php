<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_POST_Shop2_Cart_Calculate extends ACMS_POST_Shop2
{
    function post()
    {
        Deprecated::once('Shop2_Cart_Calculate モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $Cart = $this->extract('cart');
        $Cart->validate(new ACMS_Validator());
        $bid    = $Cart->isNull('cart_bid') ? BID : intval($Cart->get('cart_bid', BID));

        if ($Cart->isValid()) {
            $TEMP = $this->openCart($bid);

            $fds = $Cart->listFields();
            foreach ($fds as $fd) {
                $qty = intval($Cart->get($fd));
                $TEMP[$fd][$this->item_qty] = $qty;
                if (empty($TEMP[$fd][$this->item_qty])) {
                    unset($TEMP[$fd]);
                } else {
                    $rate   = intval($TEMP[$fd][$this->item_price . '#rate']) / 100;
                    $price  = $TEMP[$fd][$this->item_price];

                    if (config('shop_tax_calculate') == 'extax') {
                        $tax = $price * $qty * $rate;
                    } else {
                        $tax = ( $price * $qty ) - ( $price * $qty / ( 1 + $rate )) ;
                    }

                    if (config('shop_tax_rounding') == 'ceil') {
                        // 切り上げ
                        $tax = intval(ceil($tax));
                    } elseif (config('shop_tax_rounding') == 'round') {
                        // 四捨五入
                        $tax = intval(round($tax));
                    } else {
                        // 切り捨て
                        $tax = intval(floor($tax));
                    }

                    $TEMP[$fd][$this->item_price . '#tax'] = $tax;
                    $TEMP[$fd][$this->item_price . '#sum'] = $price * $qty;
                }
            }

            $this->closeCart($TEMP, $bid);
        }
        $this->Post->set('step', null);
        return $this->Post;
    }
}
