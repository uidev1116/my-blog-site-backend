<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2_Cart_Empty extends ACMS_GET_Shop2
{
    function get()
    {
        Deprecated::once('Shop2_Cart_Empty モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $step   = $this->Post->get('step', 'apply');
        $bid    = BID;

        if ($step == 'result') {
            $cart = $this->session->get($this->cname . $bid);
            if (!empty($cart)) {
                $this->session->delete($this->cname . $bid);
                $this->session->save();
            }
            if (!!ACMS_SID) {
                $DB     = DB::singleton(dsn());
                $SQL    = SQL::newDelete('shop_cart');
                $SQL->addWhereOpr('cart_session_id', ACMS_SID);
                $SQL->addWhereOpr('cart_blog_id', $bid);
                $DB->query($SQL->get(dsn()), 'exec');
            }
        }
        return '';
    }
}
