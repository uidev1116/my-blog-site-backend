<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Form_RequestList extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Form_RequestList モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());

        $SESSION =& $this->openSession();

        /*
        * Date -------------------------------------------------------
        */
        $start  = intval($this->config->get('shop_order_request_date_min'));
        $limit  = intval($this->config->get('shop_order_request_date_max'));

        do {
            $date = date('Y-m-d', strtotime('+' . $start . 'day'));
            $vars = ['date' => $date];

            // if session is stored
            if ($SESSION->get('request_date') == $date) {
                $vars += ['selected' => config('attr_selected'),
                    'checked'  => config('attr_checked'),
                ];
            }

            $Tpl->add('date:loop', $vars);
            $start++;
        } while ($start <= $limit);

        /*
        * Time -------------------------------------------------------
        */
        $times = $this->config->getArray('shop_order_request_time');

        foreach ($times as $time) {
            $vars = ['time' => $time];

            // if session is stored
            if ($SESSION->get('request_time') == $time) {
                $vars += ['selected' => config('attr_selected'),
                    'checked'  => config('attr_checked'),
                ];
            }

            $Tpl->add('time:loop', $vars);
        }

        /*
        * Others ----------------------------------------------------
        */
        $requests = $this->config->getArray('shop_order_request_others_label');
        $charge   = $this->config->getArray('shop_order_request_others_charge');

        foreach ($requests as $key => $request) {
            $vars = [
                'request' => $request,
                'charge'  => @$charge[$key],
            ];

            // if session is stored
            if ($SESSION->get('request_others') == $request) {
                $vars += ['selected' => config('attr_selected'),
                    'checked'  => config('attr_checked'),
                ];
            }

            $Tpl->add('request:loop', $vars);
        }

        return $Tpl->get();
    }
}
