<?php

use Acms\Services\Logger\Deprecated;

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_POST_Shop2_Form_Address extends ACMS_POST_Shop2
{
    function post()
    {
        Deprecated::once('Shop2_Form_Address モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Shopping Cart 拡張アプリ',
        ]);
        $this->initVars();

        $Order = $this->extract('order');
        $Order->setMethod('sendto', 'required', true);
        $Order->validate(new ACMS_Validator());

        if ($this->alreadySubmit()) {
            $this->screenTrans();
        }

        // プライマリアドレスのバリデーションメソッドを追加
        $Primary = $this->extract('primary');
        $Primary->setMethod('name', 'required');
        $Primary->setMethod('ruby', 'required');
        $Primary->setMethod('zip', 'required');
        $Primary->setMethod('prefecture', 'required');
        $Primary->setMethod('city', 'required');
        $Primary->setMethod('field_1', 'required');
        $Primary->setMethod('telephone', 'required');
        $Primary->validate(new ACMS_Validator());

        // 送り先がセカンダリで指定されていれば，バリデーションメソッドを追加
        $Secondary = $this->extract('secondary');
        if ($Order->get('sendto') == 'secondary') {
            $Secondary->setMethod('name#2', 'required');
            $Secondary->setMethod('ruby#2', 'required');
            $Secondary->setMethod('zip#2', 'required');
            $Secondary->setMethod('prefecture#2', 'required');
            $Secondary->setMethod('city#2', 'required');
            $Secondary->setMethod('field_1#2', 'required');
            $Secondary->setMethod('telephone#2', 'required');
        }
        $Secondary->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());

            /**
             * プライマリアドレスをINSERTまたはUPDATEする
             */
            if (!is_null(SUID)) {
                $SQL = SQL::newSelect('shop_address');
                $SQL->addWhereOpr('address_user_id', SUID);
                $SQL->addWhereOpr('address_primary', 'on');
                $row = $DB->query($SQL->get(dsn()), 'row');

                if (empty($row)) {
                    $aid    = $DB->query(SQL::nextval('shop_address_id', dsn()), 'seq');
                    $SQL    = SQL::newInsert('shop_address');
                    $SQL->addInsert('address_id', $aid);
                    $SQL->addInsert('address_name', $Primary->get('name'));
                    $SQL->addInsert('address_ruby', $Primary->get('ruby'));
                    $SQL->addInsert('address_country', $Primary->get('country'));
                    $SQL->addInsert('address_zip', $Primary->get('zip'));
                    $SQL->addInsert('address_prefecture', $Primary->get('prefecture'));
                    $SQL->addInsert('address_city', $Primary->get('city'));
                    $SQL->addInsert('address_field_1', $Primary->get('field_1'));
                    $SQL->addInsert('address_field_2', $Primary->get('field_2'));
                    $SQL->addInsert('address_telephone', $Primary->get('telephone'));
                    $SQL->addInsert('address_user_id', SUID);
                    $SQL->addInsert('address_blog_id', BID);
                    $SQL->addInsert('address_primary', 'on');
                    $DB->query($SQL->get(dsn()), 'exec');
                } else {
                    $SQL    = SQL::newUpdate('shop_address');
                    $SQL->addUpdate('address_name', $Primary->get('name'));
                    $SQL->addUpdate('address_ruby', $Primary->get('ruby'));
                    $SQL->addUpdate('address_country', $Primary->get('country'));
                    $SQL->addUpdate('address_zip', $Primary->get('zip'));
                    $SQL->addUpdate('address_prefecture', $Primary->get('prefecture'));
                    $SQL->addUpdate('address_city', $Primary->get('city'));
                    $SQL->addUpdate('address_field_1', $Primary->get('field_1'));
                    $SQL->addUpdate('address_field_2', $Primary->get('field_2'));
                    $SQL->addUpdate('address_telephone', $Primary->get('telephone'));
                    $SQL->addWhereOpr('address_user_id', SUID);
                    $SQL->addWhereOpr('address_primary', 'on');
                    $DB->query($SQL->get(dsn()), 'exec');
                }
            }

            /**
             * セカンダリアドレスの指定があり，かつ登録フラグが立っていればINSERTを発行
             */
            if (
                !$Secondary->isNull() && // @phpstan-ignore-line
                $Secondary->get('regist') === 'on' &&
                $Order->get('sendto') === 'secondary' &&
                !is_null(SUID) // @phpstan-ignore-line
            ) {
                $aid    = $DB->query(SQL::nextval('shop_address_id', dsn()), 'seq');
                $SQL    = SQL::newInsert('shop_address');
                $SQL->addInsert('address_id', $aid);
                $SQL->addInsert('address_name', $Secondary->get('name#2'));
                $SQL->addInsert('address_ruby', $Secondary->get('ruby#2'));
                $SQL->addInsert('address_country', $Secondary->get('country#2'));
                $SQL->addInsert('address_zip', $Secondary->get('zip#2'));
                $SQL->addInsert('address_prefecture', $Secondary->get('prefecture#2'));
                $SQL->addInsert('address_city', $Secondary->get('city#2'));
                $SQL->addInsert('address_field_1', $Secondary->get('field_1#2'));
                $SQL->addInsert('address_field_2', $Secondary->get('field_2#2'));
                $SQL->addInsert('address_telephone', $Secondary->get('telephone#2'));
                $SQL->addInsert('address_user_id', SUID);
                $SQL->addInsert('address_blog_id', BID);
                $SQL->addInsert('address_primary', 'off');
                $DB->query($SQL->get(dsn()), 'exec');
            }
            $Secondary->delete('regist');

            /**
             * セッションに保存する送り先のアドレスを決める
             */
            $Address = new Field();

            if ($Order->get('sendto') == 'primary') {
                $Address = $Primary;
            } elseif ($Order->get('sendto') == 'secondary') {
                $list = $Secondary->listFields();
                foreach ($list as $fd) {
                    $fix = str_replace('#2', '', $fd);
                    $Address->set($fix, $Secondary->get($fd));
                }
            } elseif (is_numeric($Order->get('sendto'))) {
                $SQL = SQL::newSelect('shop_address');
                $SQL->addWhereOpr('address_user_id', SUID);
                $SQL->addWhereOpr('address_id', $Order->get('sendto'));

                if ($row = $DB->query($SQL->get(dsn()), 'row')) {
                    foreach ($row as $key => $val) {
                        if ($key == 'address_primary') {
                            continue;
                        }
                        if ($key == 'address_user_id') {
                            continue;
                        }
                        $Address->set(substr($key, strlen('address_')), $val);
                    }
                }
            }

            //$SESSION =& Field::singleton('session');
            $SESSION =& $this->openSession();

            if (is_null(SUID)) { // @phpstan-ignore-line
                $mail = $Primary->get('mail');
                $Primary->delete('mail');
                $SESSION->set('mail', $mail);
                $Address->set('mail', $mail);
            }

            $SESSION->addChild('primary', $Primary);
            $SESSION->addChild('address', $Address);
            $SESSION->set('sendto', $Order->get('sendto'));

            $this->closeSession($SESSION);

            $step = 'deliver';
        } else {
            $step = 'address';
        }

        $this->Post->set('step', $step);
        return $this->Post;
    }
}
