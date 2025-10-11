<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_POST_Shop2_Cart_Add extends ACMS_POST_Shop2
{
    public function post()
    {
        Deprecated::once('Shop2_Cart_Add モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $Cart = $this->extract('cart');
        $Cart->setMethod($this->item_id, 'required');
        $Cart->setMethod($this->item_id, 'digits');
        $Cart->setMethod($this->item_qty, 'required');
        $Cart->setMethod($this->item_qty, 'digits');
        $Cart->setMethod('cart_bid', 'digits');

        $Cart->validate(new ACMS_Validator());

        $bid = $Cart->isNull('cart_bid') ? BID : intval($Cart->get('cart_bid', BID));

        if ($this->Post->isValidAll()) {
            $TEMP = $this->openCart($bid);

            $DB = DB::singleton(dsn());
            $SQL = SQL::newSelect('entry');
            $SQL->addLeftJoin('field', 'field_eid', 'entry_id');
            $SQL->addSelect('field_key');
            $SQL->addSelect('field_value');
            $SQL->addSelect('entry_primary_image');
            $SQL->addSelect('entry_category_id');
            $SQL->addWhereOpr('entry_id', $Cart->get($this->item_id));

            /**
             * @var array{
             *   field_key: string,
             *   field_value: string,
             *   entry_primary_image: int|null,
             *   entry_category_id: int|null
             * }[] $fds
             */
            $fds = $DB->query($SQL->get(dsn()), 'all');

            if (!empty($fds)) {
                // レパートリーの指定を取得する
                $repertories = $Cart->getArray('item_repertory_fields');

                // カート内の商品情報フィールドに配列型は転写できない（efdに選択肢として配列を登録することはできる）
                foreach ($fds as $fd) {
                    $item[$fd['field_key']] = $fd['field_value'];
                }

                // すべての行にentry系の情報はつながっている
                $item['entry_primary_image'] = $fds[0]['entry_primary_image'];
                $categoryName = null;
                $categoryId = intval($fds[0]['entry_category_id']);
                if ($categoryId > 0) {
                    $categoryName = ACMS_RAM::categoryName($categoryId);
                }
                $item[$this->item_category] = $categoryName;

                // 既定のフィールドを埋める
                $item[$this->item_qty] = intval($Cart->get($this->item_qty));
                $item[$this->item_id]  = intval($Cart->get($this->item_id));
                //$item[$this->item_price.'#tax'] = $this->tax($item[$this->item_price]);

                // 直前に追加した商品の情報を保存
                $_SESSION['added'] = $item;
                $this->session->set('added', $item);
                $this->session->save();

                // 商品データを組み立てる（既定のフィールドは前に追加しているので破棄）
                $Cart->delete($this->item_qty);
                $Cart->delete($this->item_id);
                $Cart->delete('item_repertory_fields'); // レパートリーの定義も不要なので破棄する
                $fds = $Cart->listFields();
                foreach ($fds as $fd) {
                    $item[$fd] = $Cart->get($fd);
                }

                // 既存のカート内商品の走査
                if (!is_array($TEMP)) {
                    $TEMP = [];
                }

                if (isset($item['item_tax'])) {
                    $tax_rate = $item['item_tax']; // 商品毎の消費税を適用
                } else {
                    $tax_rate = floatval(config('shop_tax_rate')); // 標準設定の消費税
                }
                @$item[$this->item_price . '#rate'] = $tax_rate * 100;


                foreach ($TEMP as $p => $inItem) {
                    // レパートリー含めて同じ商品があれば，既存の記録を破棄して，数量だけマージ
                    if ($this->isSameItem($inItem, $item, $repertories)) {
                        $item[$this->item_qty] += $inItem[$this->item_qty];
                        unset($TEMP[$p]);
                    }
                }

                @$item[$this->item_price . '#sum'] = intval($item[$this->item_price] * $item[$this->item_qty]);


                if (config('shop_tax_calc_method') == 'pileup') {
                    // 商品毎に消費税を計算

                    if (config('shop_tax_calculate') == 'extax') {
                        // 外税
                        $tax = $item[$this->item_price] * $item[$this->item_qty] * $tax_rate ;
                    } else {
                        // 内税
                        $tax = ( $item[$this->item_price] * $item[$this->item_qty] ) - ( $item[$this->item_price] * $item[$this->item_qty] / ( 1 + $tax_rate )) ;
                    }

                    if (config('shop_tax_rounding') == 'ceil') {
                        // 切り上げ
                        $item[$this->item_price . '#tax'] = intval(ceil($tax));
                    } elseif (config('shop_tax_rounding') == 'round') {
                        // 四捨五入
                        $item[$this->item_price . '#tax'] = intval(round($tax));
                    } else {
                        // 切り捨て
                        $item[$this->item_price . '#tax'] = intval(floor($tax));
                    }
                }

                // ハッシュを配列キーにあたえる（ユーザー内だから単一時間軸内でユニークであればOK）
                $TEMP[md5((string) time())] = $item;
            }
            $this->closeCart($TEMP, $bid);

            // redirect to target location
            if (config('shop_cart_elevate') == 'on') {
                $this->screenTrans($this->addedTpl, null, $bid);
            } else {
                $this->screenTrans($this->addedTpl);
            }
        } else {
            return $this->Post;
        }
    }

    // レパートリーの定義は，判定したい商品の追加時には，必ず同じモノが提供されなければならない
    protected function isSameItem($old, $new, $repertories)
    {
        // エントリーIDが異なれば，違う商品とみなす
        if ($old[$this->item_id] !== $new[$this->item_id]) {
            return false;
        }

        // エントリーIDが異なることが確認された上で，レパートリーの定義がなければ，同じ商品とみなす
        if (empty($repertories)) {
            return true;
        }

        foreach ($repertories as $repertory) {
            // もしもいずれかでレパートリーが未定義であれば，違う商品とみなす
            if (!isset($old[$repertory]) || !isset($new[$repertory])) {
                return false;
            }

            // 何らかのレパートリーが異なれば，違う商品とみなす
            if ($old[$repertory] !== $new[$repertory]) {
                return false;
            }
        }

        // すべてのレパートリーに一致が見られれば，同じ商品とみなす
        return true;
    }
}
