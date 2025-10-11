<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_POST_Shop2_Form_Backstep extends ACMS_POST_Shop2
{
    function post()
    {
        Deprecated::once('Shop2_Form_Backstep モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        switch ($this->Post->get('step')) {
            case 'address':
                $this->Get->set('step', '');
                break;
            case 'deliver':
                $this->Get->set('step', 'address');
                break;
            case 'confirm':
                $this->Get->set('step', 'deliver');
                break;
            default:
                break;
        }

        $this->screenTrans($this->orderTpl, $this->Get->get('step'));
    }
}
