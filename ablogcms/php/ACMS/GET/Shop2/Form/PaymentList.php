<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Form_PaymentList extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Form_PaymentList モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());

        $SESSION =& $this->openSession();

        $payments = $this->config->getArray('shop_order_payment_label');
        $charge   = $this->config->getArray('shop_order_payment_charge');

        foreach ($payments as $key => $payment) {
            $vars = ['payment' => $payment,
                'charge'  => @$charge[$key],
            ];

            if ($SESSION->get('payment') == $payment) {
                $vars += ['selected' => config('attr_selected'),
                    'checked'  => config('attr_checked'),
                ];
            }

            $Tpl->add('payment:loop', $vars);
        }

        return $Tpl->get();
    }
}
