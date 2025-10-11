<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Form_DeliverList extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Form_DeliverList モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();
        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        $SESSION =& $this->openSession();
        $ADDRESS = $SESSION->getChild('address');

        $delivers = $this->config->getArray('shop_order_deliver_label');
        $charge = $this->config->getArray('shop_order_deliver_charge');
        $common = $this->config->get('shop_order_shipping_common');

        foreach ($delivers as $key => $deliver) {
            $vars = ['deliver' => $deliver];

            /**
             * detect shipping
             */
            if (isset($charge[$key]) && is_numeric($charge[$key])) {
                $vars += ['charge' => @$charge[$key]];
            } else {
                if (is_numeric($common)) {
                    $vars += [
                        'charge' => intval($this->config->get('shop_order_shipping_common')),
                        'prefecture' => '全国一律',
                    ];
                } else {
                    $vars += [
                        'charge' => $this->shipping($ADDRESS->get('prefecture')),
                        'prefecture' => $ADDRESS->get('prefecture'),
                    ];
                }
            }

            if ($SESSION->get('deliver') == $deliver) {
                $vars += ['selected' => config('attr_selected'),
                    'checked' => config('attr_checked'),
                ];
            }

            $Tpl->add('deliver:loop', $vars);
        }

        return $Tpl->get();
    }

    function shipping($prefecture)
    {
        $labels = $this->config->getArray('shop_order_shipping_label');
        $charge = $this->config->getArray('shop_order_shipping_charge');

        foreach ($labels as $key => $label) {
            if ($prefecture == $label) {
                return @$charge[$key];
            }
        }
    }
}
