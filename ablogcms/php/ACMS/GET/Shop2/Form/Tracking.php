<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Form_Tracking extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Form_Tracking モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $SESSION =& $this->openSession();

        if ($SESSION->isNull('submitted')) {
            $ADDRESS  =  $SESSION->getChild('address');
            $TEMP     =  $SESSION->getArray('portrait_cart');

            $Tpl    = new Template($this->config->get('shop_order_tracking_code'), new ACMS_Corrector());

            foreach ($TEMP as $item) {
                $price  = !empty($item[$this->item_price])  ? $item[$this->item_price]  : 0;
                $qty    = !empty($item[$this->item_qty])    ? $item[$this->item_qty]    : 0;
                $name   = !empty($item[$this->item_name])   ? $item[$this->item_name]   : 'unknown';
                $sku    = !empty($item[$this->item_sku])    ? $item[$this->item_sku]    : 0;
                $cate   = !empty($item[$this->item_category]) ? $item[$this->item_category] : 'unknown';

                $vars = [
                    'code'      => $SESSION->get('code'),
                    'price'     => $price,
                    'quantity'  => $qty,
                    'name'      => $name,
                    'stock'     => $sku,
                    'category'  => $cate,
                ];
                $Tpl->add('item:loop', $vars);
            }

            $vars = [
                'code'      => $SESSION->get('code'),
                'payment'   => $SESSION->get('payment', 'unknown'),
                'total'     => $SESSION->get('total', 0),
                'tax'       => $SESSION->get('tax-only', 0),
                'shipping'  => $SESSION->get('charge#deliver', 0),
                'city'      => $ADDRESS->get('city', 'unknown'),
                'prefecture' => $ADDRESS->get('prefecture', 'unknown'),
                'country'   => $ADDRESS->get('country', 'Japan'),
            ];

            $Tpl->add(null, $vars);

            return $Tpl->get();
        } else {
            return '';
        }
    }
}
