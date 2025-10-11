<?php

// openCart & closeCart はカートの表示でありモジュールIDとルールの対象であるため、$this->bid
// openSession & closeSession はオーダーフォームのセンション管理下にあるため、BID

/**
 * @deprecated カート機能は非推奨です。代替として、Shopping Cart 拡張アプリをご利用ください。
 */
class ACMS_GET_Shop2 extends ACMS_GET
{
    protected $session;

    protected $config;

    /**
     * @var string
     */
    protected $item_id;

    /**
     * @var string
     */
    protected $item_name;

    /**
     * @var string
     */
    protected $item_price;

    /**
     * @var string
     */
    protected $item_qty;

    /**
     * @var string
     */
    protected $item_sku;

    /**
     * @var string
     */
    protected $item_category;

    /**
     * @var string
     */
    protected $item_except;

    /**
     * @var string
     */
    protected $cname;

    /**
     * @var string
     */
    protected $sname;

    /**
     * @var string
     */
    protected $addedTpl;

    /**
     * @var string
     */
    protected $orderTpl;

    /**
     * @var string
     */
    protected $loginTpl;

    protected function initVars()
    {
        $this->config = Config::loadDefaultField();
        $this->config->overload(Config::loadBlogConfig(BID));

        $this->item_id       = $this->config->get('shop_item_id');
        $this->item_name     = $this->config->get('shop_item_name');
        $this->item_price    = $this->config->get('shop_item_price');
        $this->item_qty      = $this->config->get('shop_item_qty');
        $this->item_sku      = $this->config->get('shop_item_sku');
        $this->item_category = $this->config->get('shop_item_category');
        $this->item_except   = $this->config->get('shop_item_exception');
        $this->cname         = $this->config->get('shop_cart');
        $this->sname         = $this->config->get('shop_session');
        $this->addedTpl      = $this->config->get('shop_tpl_added');
        $this->orderTpl      = $this->config->get('shop_tpl_order');
        $this->loginTpl      = $this->config->get('shop_tpl_login');
        $this->session = ACMS_Session::singleton();

        if (!defined('NO_CACHE_PAGE')) {
            define('NO_CACHE_PAGE', 1);
        }
    }

    function sanitize(&$data)
    {
        if (is_array($data)) {
            foreach ($data as & $row) {
                $this->sanitize($row);
            }
            return $data;
        } else {
            if (is_string($data)) {
                $data = htmlentities($data, ENT_QUOTES, 'UTF-8');
            }
            return $data;
        }
    }

    function & openSession()
    {
        $sname = $this->sname . BID;
        $session = $this->session->get($sname);

        if (!ACMS_SID && !empty($session)) {
            return $session;
        } elseif (!ACMS_SID && empty($session)) {
            $field = new Field();
            return $field;
        } elseif (!!ACMS_SID && !empty($session)) {
            $this->session->delete($sname);
            $this->session->save();
            return $session;
        } elseif (!!ACMS_SID && empty($session)) {
            return Field::singleton('session');
        }
    }

    function closeSession($DATA)
    {
        if (!ACMS_SID) {
            $this->session->set($this->sname . BID, $DATA);
            $this->session->save();
        } elseif (!!ACMS_SID) {
            // script shutdown, when session is auto saving.
        }
    }

    function openCart()
    {
        $cart = $this->session->get($this->cname . $this->bid);
        if (!!ACMS_SID) {
            $temp = $this->loadCart(ACMS_SID);
            return !empty($temp) ? $temp : $cart;
        } elseif (!ACMS_SID && !empty($cart)) {
            return $cart;
        } else {
            return [];
        }
    }

    function closeCart($CART)
    {
        $this->session->set($this->cname . $this->bid, $CART);
        $this->session->save();

        if (!!ACMS_SID) {
            $CART   = acmsSerialize($CART);
            $DB     = DB::singleton(dsn());

            $SQL    = SQL::newDelete('shop_cart');
            $SQL->addWhereOpr('cart_session_id', ACMS_SID);
            $SQL->addWhereOpr('cart_blog_id', $this->bid);
            $DB->query($SQL->get(dsn()), 'exec');

            $SQL    = SQL::newInsert('shop_cart');
            $SQL->addInsert('cart_data', $CART);
            $SQL->addInsert('cart_session_id', ACMS_SID);
            $SQL->addInsert('cart_blog_id', $this->bid);
            $res = $DB->query($SQL->get(dsn()), 'exec');
        }
    }

    function loadCart($sid)
    {
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('shop_cart');
        $SQL->addSelect('cart_data');
        $SQL->addWhereOpr('cart_session_id', $sid);
        $SQL->addWhereOpr('cart_blog_id', $this->bid);
        $DATA   = $DB->query($SQL->get(dsn()), 'row');
        return acmsUnserialize($DATA['cart_data']);
    }

    function screenTrans($page = null, $step = null)
    {
        if (!empty($step)) {
            redirect(acmsLink(['tpl' => $page, 'query' => ['step' => $step]]));
        } else {
            redirect(acmsLink(['tpl' => $page]));
        }
    }
}
