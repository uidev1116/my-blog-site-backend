<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_POST_Shop2_Form_Submit extends ACMS_POST_Shop2
{
    function post()
    {
        Deprecated::once('Shop2_Form_Submit モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();
        $DB = DB::singleton(dsn());

        $Order = $this->extract('order', new ACMS_Validator());

        $TEMP    = $this->openCart();

        // 在庫数チェック
        if ('on' == config('shop_stock_change')) {
            $flg_valid = true;
            foreach ($TEMP as $key => $item) {
                $entryField = loadEntryField(intval($item['item_id']));
                $int_stock = intval($entryField->get($this->item_sku));
                if (!($int_stock >= intval($item[$this->item_qty]))) {
                    $flg_valid = false;
                    $TEMP[$key][$this->item_qty . ':v#max'] = 'over';
                }
            }
            if (!$flg_valid) {
                $this->Get->set('step', '');
                $this->screenTrans($this->orderTpl, $this->Get->get('step'));
            }
        }

        $SESSION =& $this->openSession();

        $PRIMARY = $SESSION->getChild('primary');
        $ADDRESS = $SESSION->getChild('address');
        /**
         * start save to FIELD Object
         */
        $FIELD =& $this->Post->getChild('field');

        /**
         * store: session
         */
        $list = $SESSION->listFields();
        foreach ($list as $key) {
            $FIELD->set($key, $SESSION->get($key));
        }

        /**
         * validation
         */
        if (
            0
            or empty($TEMP)
            or empty($ADDRESS)
            or !$SESSION->get('total')
            or !$SESSION->get('subtotal')
        ) {
            $TEMP = [];
            $this->closeSession($SESSION);
            $this->closeCart($TEMP);

            $this->Post->set('step', 'error');
            return $this->Post;
        }


        /**
         * store: order note
         */
        $FIELD->set('note', $Order->get('note'));

        /**
         * store: address
         */
        $fds = $PRIMARY->listFields();
        foreach ($fds as $fd) {
            $FIELD->set($fd . '#1', $PRIMARY->get($fd));
        }
        $fds = $ADDRESS->listFields();
        foreach ($fds as $fd) {
            $FIELD->set($fd . '#2', $ADDRESS->get($fd));
        }

        // すでに送信していれば2重で記録等をしない
        if (!$this->alreadySubmit()) {
            /**
             * build: item:loop
             */
            $cartBody = '';
            if ($cartTpl = findTemplate($this->cartTpl)) {
                $Tpl = new Template(setGlobalVars(LocalStorage::get($cartTpl)), new ACMS_Corrector());
                foreach ($TEMP as $item) {
                    /*
                    if ( config('shop_tax_calculate') != 'extax' ) {
                        $item[$this->item_price] += $item[$this->item_price.'#tax'];
                    }
                    */
                    $Tpl->add('item:loop', $item);
                }
                $Tpl->add(null);
                $cartBody = $Tpl->get();
            }

            /**
             * resultステップ用に，現在のカートの内容をポートレートに退避しておく
             * @todo issue: array_valuesで添え字をなくさないと、Fieldクラスが暴走する / v150時点で応急処置
             */
            $SESSION->set('portrait_cart', array_values($TEMP));

            /**
             * initVars
             */
            $suid = ( !!SUID ) ? SUID : 0;

            /**
             * Update Item Stock Keeping Unit
             */
            if ('on' == config('shop_stock_change')) {
                foreach ($TEMP as $item) {
                    $sku = $this->getSku($item[$this->item_id]);
                    $sku = ($sku <= $item[$this->item_qty]) ? '' : $sku - $item[$this->item_qty];

                    $sku = (intval($sku) < 0) ? 0 : intval($sku);

                    Common::deleteFieldCache('eid', $item[$this->item_id]);

                    $SQL    = SQL::newUpdate('field');
                    $SQL->addUpdate('field_value', $sku);
                    $SQL->addWhereOpr('field_key', $this->item_sku);
                    $SQL->addWhereOpr('field_eid', $item[$this->item_id]);
                    $DB->query($SQL->get(dsn()), 'exec');
                    unset($sku);
                }
            }
            $code   = date('Ymd-His') . sprintf("-%03d", mt_rand(0, 999));
            $SESSION->set('code', $code);

            /**
             * メール用に変数セット
             */
            $FIELD->set('cart', $cartBody);
            $FIELD->set('code', $code);

            /**
             * Form_Submitをインスタンス化して実行する
             */
            $Submit = new ACMS_POST_Form_Submit();

            $Submit->Q      = $this->Q;
            $Submit->Get    = $this->Get;
            $Submit->Post   = $this->Post;

            setConfig('form_csrf_enable', 'off');
            $Submit->post();
        } else {
            $SESSION->set('submitted', true);
        }

        $this->Post->set('code', $SESSION->get('code'));
        $this->Post->set('step', 'result');

        /**
         * セッションとカートをクリアする
         */
        $TEMP = [];
        $this->closeSession($SESSION);
        $this->closeCart($TEMP);

        return $this->Post;
    }

    function getSku($eid)
    {
        $DB = DB::singleton(dsn());
        $SQL    = SQL::newSelect('field');
        $SQL->addSelect('field_value');
        $SQL->addWhereOpr('field_key', $this->item_sku);
        $SQL->addWhereOpr('field_eid', $eid);
        return intval($DB->query($SQL->get(dsn()), 'one'));
    }
}
