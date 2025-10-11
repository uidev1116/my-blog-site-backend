<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Form_Order extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Form_Order モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $SESSION =& $this->openSession();
        $TEMP   = $this->openCart();

        $Order  =& $this->Post->getChild('order');
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());

        $step   = $this->Get->get('step');
        if ($this->Post->isValidAll()) {
            $step  = $this->Post->get('step', $step);
        }

        $root  = !(empty($step) or is_bool($step)) ? 'step#' . $step : 'step';
        $vars   = $this->buildField($Order, $Tpl, $root, 'order');
        $vars['step'] = $step;

        if ($root == 'step') {
            $SESSION->delete('submitted');
            $SESSION->delete('portrait_cart');
        }

        if ($step == 'confirm') {
            // Cart

            $TEMP = $this->openCart();
            foreach ($TEMP as $item) {
                /*if ( config('shop_tax_calculate') != 'extax' ) {
                    $item[$this->item_price] += $item[$this->item_price.'#tax'];
                }*/
                $Tpl->add(['item:loop', 'cart', $root], $item);
            }

            // Address
            $Primary = $SESSION->getChild('primary');
            $Address = $SESSION->getChild('address');

            $Tpl->add(['primary', $root], $this->buildField($Primary, $Tpl, ['primary', $root]));
            $Tpl->add(['address', $root], $this->buildField($Address, $Tpl, ['address', $root]));

            // Session
            $vars += $this->buildField($SESSION, $Tpl, $root);
        }

        if ($step == 'result') {
            $_vars   = null;
            $this->initVars();
            $_vars   = ['code' => $this->Post->get('code')];

            $Field      = $this->Post->getChild('field');
            $Primary    = $SESSION->getChild('primary');
            $TEMP       = $SESSION->getArray('portrait_cart');
            $block      = $Field->get('settle');

            $_vars += $this->buildField($Field, $Tpl, [$block, $root]);
            $Tpl->add([$block, $root], $_vars);
        }

        $Tpl->add($root, $vars);
        return $Tpl->get();
    }
}
