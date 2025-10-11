<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_POST_Shop2_Form_Confirm extends ACMS_POST_Shop2
{
    public function post()
    {
        Deprecated::once('Shop2_Form_Confirm モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $Order = $this->extract('order');
        $Order->setMethod('payment', 'required', true);
        $Order->setMethod('deliver', 'required', true);
        $Order->validate(new ACMS_Validator());

        if ($this->alreadySubmit()) {
            $this->screenTrans();
        }

        if ($this->Post->isValidAll()) {
            $SESSION =& $this->openSession();
            $SESSION->set('payment', $Order->get('payment'));
            $SESSION->set('deliver', $Order->get('deliver'));
            $SESSION->set('request_date', $Order->get('request_date'));
            $SESSION->set('request_time', $Order->get('request_time'));
            $SESSION->set('request_others', $Order->get('request_others'));



            /*
            * 合計類の一括計算（税抜き計など）
            */
            $ADDRESS = $SESSION->getChild('address');
            $TEMP = $this->openCart();
            $amount = [
                'amount' => 0,
                'subtotal' => 0,
                'tax-omit' => 0,
                'tax-only' => 0,
            ];
            $tax_rate = [];

            $tax_rate_array = configArray('shop_tax_rate_array');
            foreach ($tax_rate_array as $rate) {
                $amount['tax-omit' . $rate] = 0;
                $amount['tax-only' . $rate] = 0;
            }
            foreach ($TEMP as $row) {
                $price  = $row[$this->item_price];
                $qty    = $row[$this->item_qty];
                $sum    = $row[$this->item_price . '#sum'];
                $rate   = $row[$this->item_price . '#rate'];

                array_push($tax_rate, $rate);

                $tax = 0;
                if (isset($row[$this->item_price . '#tax'])) {
                    $tax = $row[$this->item_price . '#tax'];
                }

                @$amount['amount'] += $qty;

                if (!isset($amount['tax-omit' . $rate])) {
                    $amount['tax-omit' . $rate] = 0;
                }
                if (!isset($amount['tax-only' . $rate])) {
                    $amount['tax-only' . $rate] = 0;
                }
                if (config('shop_tax_calc_method') == 'pileup') {
                    // 商品毎に消費税を計算

                    if (config('shop_tax_calculate') == 'extax') {
                        @$amount['subtotal'] += $sum + $tax;
                        @$amount['tax-omit'] += $sum;
                        @$amount['tax-only'] += $tax;

                        @$amount['tax-omit' . $rate] += $sum;
                        @$amount['tax-only' . $rate] += $tax;
                    } else {
                        @$amount['subtotal'] += $sum;
                        @$amount['tax-omit'] += $sum - $tax;
                        @$amount['tax-only'] += $tax;

                        @$amount['tax-omit' . $rate] += $sum - $tax;
                        @$amount['tax-only' . $rate] += $tax;
                    }
                } else {
                    // 小計毎に消費税を計算

                    @$amount['subtotal'] += $sum; // 外税時には再計算

                    @$amount['tax-omit' . $rate] += $sum;
                    @$amount['tax-only' . $rate] += $tax;
                }

                if (!empty($row[$this->item_except]) && empty($except_flg)) {
                    $except_flg = ($row[$this->item_except] == 'on') ? true : false;
                }
            }

            $tax_rate = array_unique($tax_rate);

            if (config('shop_tax_calc_method') == 'rebate') {
                // 小計毎に消費税を計算

                $amount['tax-omit'] = 0;
                $amount['tax-only'] = 0;

                if (config('shop_tax_calculate') == 'intax') {
                    //内税 intax

                    foreach ($tax_rate as $rate) {
                        $rate_num = $rate / 100 + 1;
                        $sum = $amount['tax-omit' . $rate];

                        if (config('shop_tax_rounding') == 'ceil') {
                            // 切り上げ
                            $tax = intval(ceil($sum - ( $sum / $rate_num )));
                        } elseif (config('shop_tax_rounding') == 'round') {
                            // 四捨五入
                            $tax = intval(round($sum - ( $sum / $rate_num )));
                        } else {
                            // 切り捨て
                            $tax = intval(floor($sum - ( $sum / $rate_num )));
                        }

                        if (!isset($amount['tax-omit' . $rate])) {
                            $amount['tax-omit' . $rate] = 0;
                        }
                        if (!isset($amount['tax-only' . $rate])) {
                            $amount['tax-only' . $rate] = 0;
                        }

                        @$amount['tax-omit' . $rate] = $sum - $tax;
                        @$amount['tax-only' . $rate] = $tax;

                        @$amount['tax-omit'] += $amount['tax-omit' . $rate];
                        @$amount['tax-only'] += $amount['tax-only' . $rate];
                    }
                } else {
                    //外税 extax
                    $amount['subtotal'] = 0;

                    foreach ($tax_rate as $rate) {
                        $rate_num = $rate / 100;
                        $sum = $amount['tax-omit' . $rate];

                        if (config('shop_tax_rounding') == 'ceil') {
                            // 切り上げ
                            $tax = intval(ceil($sum * $rate_num));
                        } elseif (config('shop_tax_rounding') == 'round') {
                            // 四捨五入
                            $tax = intval(round($sum * $rate_num));
                        } else {
                            // 切り捨て
                            $tax = intval(floor($sum * $rate_num));
                        }

                        if (!isset($amount['tax-omit' . $rate])) {
                            $amount['tax-omit' . $rate] = 0;
                        }
                        if (!isset($amount['tax-only' . $rate])) {
                            $amount['tax-only' . $rate] = 0;
                        }

                        @$amount['tax-omit' . $rate] = $sum;
                        @$amount['tax-only' . $rate] = $tax;

                        @$amount['tax-omit'] += $amount['tax-omit' . $rate];
                        @$amount['tax-only'] += $amount['tax-only' . $rate];

                        @$amount['subtotal'] += $sum + $tax;
                    }
                }
            }

            /*
            * detect exception item
            */
            if (!empty($except_flg)) {
                $SESSION->set($this->item_except, 'on');
            } elseif (!$SESSION->isNull($this->item_except)) {
                $SESSION->delete($this->item_except);
            }

            /*
            * 諸費用の計算（送料／支払い手数料）
            */
            $charge = [];
            $charge['deliver'] = $this->deliverCharge($SESSION->get('deliver'), $ADDRESS->get('prefecture'));
            $charge['payment'] = $this->paymentCharge($SESSION->get('payment'));
            $charge['others']  = $this->othersCharge($SESSION->get('request_others'));

            $common = config('shop_order_shipping_common');
            $charge['deliver'] = is_numeric($common) ? $common : $charge['deliver'];
            $charge['deliver'] = ( $amount['subtotal'] < config('shop_order_shipping_limit') ) ? $charge['deliver'] : 0;

            // total = subtotal + charge( deliver + payment + others )
            $involve = explode('-', config('shop_total_involve'));
            $amount['total-owner'] = $amount['subtotal'];

            foreach ($involve as $row) {
                if ($row != ('deliver' || 'payment' || 'others')) {
                    continue;
                }
                $amount['total-owner'] += $charge[$row];
            }

            $amount['total'] = $amount['subtotal'] + $charge['deliver'] + $charge['payment'] + $charge['others'];

            $amount['charge#deliver'] = $charge['deliver'];
            $amount['charge#payment'] = $charge['payment'];
            $amount['charge#others']  = $charge['others'];
            $amount['settle']  = $this->settleMethod($SESSION->get('payment'));

            $amount['charge#deliver-tax'] = $this->taxCalc($charge['deliver']);
            $amount['charge#payment-tax'] = $this->taxCalc($charge['payment']);
            $amount['charge#others-tax']  = $this->taxCalc($charge['others']);

            $rate = floatval(config('shop_tax_rate')) * 100;

            $chargeTax = $amount['charge#deliver-tax'] + $amount['charge#payment-tax'] + $amount['charge#others-tax'];

            if (!isset($amount['tax-omit' . $rate])) {
                $amount['tax-omit' . $rate] = 0;
            }
            if (!isset($amount['tax-only' . $rate])) {
                $amount['tax-only' . $rate] = 0;
            }

            @$amount['tax-omit' . $rate] += $amount['charge#deliver'] + $amount['charge#payment'] + $amount['charge#others'] - $chargeTax;
            @$amount['tax-only' . $rate] += $chargeTax;


            // 設定をセッションに入れておく
            $amount['shop_tax_calculate'] = config('shop_tax_calculate');
            $amount['shop_tax_calc_method'] = config('shop_tax_calc_method');
            $amount['shop_tax_rounding'] = config('shop_tax_rounding');
            $amount['shop_tax_no'] = config('shop_tax_no');

            foreach ($amount as $key => $val) {
                $SESSION->set($key, $val);
            }
            $this->closeSession($SESSION);

            $step = 'confirm';
        } else {
            $step = 'deliver';
        }

        $this->Post->set('step', $step);
        return $this->Post;
    }

    protected function othersCharge($request)
    {
        $labels = configArray('shop_order_request_others_label');
        $charge = configArray('shop_order_request_others_charge');

        foreach ($labels as $key => $label) {
            if ($request == $label) {
                return @$charge[$key];
            }
        }

        return 0;
    }

    protected function deliverCharge($deliver, $prefecture = null)
    {
        $labels = configArray('shop_order_deliver_label');
        $charge = configArray('shop_order_deliver_charge');

        foreach ($labels as $key => $label) {
            if ($deliver == $label) {
                if (!isset($charge[$key])) {
                    continue;
                }
                if (!is_numeric(@$charge[$key]) && !empty($prefecture)) {
                    return $this->shipping($prefecture);
                } else {
                    return @$charge[$key];
                }
            }
        }
        return 0;
    }

    protected function paymentCharge($payment)
    {
        $labels = configArray('shop_order_payment_label');
        $charge = configArray('shop_order_payment_charge');

        foreach ($labels as $key => $label) {
            if ($payment == $label) {
                return intval(@$charge[$key]);
            }
        }
        return 0;
    }

    protected function settleMethod($payment)
    {
        $labels = configArray('shop_order_payment_label');
        $index  = array_search($payment, $labels, true);
        $block  = config('shop_order_payment_block', 'default', $index);

        return empty($block) ? 'default' : $block;
    }

    protected function shipping($prefecture)
    {
        $labels = configArray('shop_order_shipping_label');
        $charge = configArray('shop_order_shipping_charge');

        foreach ($labels as $key => $label) {
            if ($prefecture == $label) {
                return @$charge[$key];
            }
        }
    }

    protected function taxCalc($amount)
    {
        $rate = floatval(config('shop_tax_rate')) + 1;

        if (config('shop_tax_rounding') == 'ceil') {
            // 切り上げ
            $tax = intval(ceil($amount - ( $amount / $rate )));
        } elseif (config('shop_tax_rounding') == 'round') {
            // 四捨五入
            $tax = intval(round($amount - ( $amount / $rate )));
        } else {
            // 切り捨て
            $tax = intval(floor($amount - ( $amount / $rate )));
        }
        return $tax;
    }
}
