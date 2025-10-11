<?php

use Acms\Services\Facades\PublicStorage;

class ACMS_GET_Admin_Config_Banner extends ACMS_GET_Admin
{
    public function & getConfig($rid, $mid, $setid)
    {
        $config = Config::loadDefaultField();
        if ($setid) {
            $config->overload(Config::loadConfigSet($setid));
        } else {
            $config->overload(Config::loadBlogConfig(BID));
        }
        $_config = null;

        if (!!$rid && !$mid) {
            $_config = Config::loadRuleConfig($rid, $setid);
        } elseif (!!$mid) {
            $_config = Config::loadModuleConfig($mid, $rid);
        }

        if (!!$_config) {
            $config->overload($_config);
            foreach (
                [
                    'banner_limit',
                    'banner_status',
                    'banner_src',
                    'banner_img',
                    'banner_url',
                    'banner_alt',
                    'banner_attr1',
                    'banner_attr2',
                    'banner_target',
                    'banner_datestart',
                    'banner_timestart',
                    'banner_dateend',
                    'banner_timeend',
                    'banner_order'
                ] as $fd
            ) {
                $config->setField($fd, $_config->getArray($fd));
            }
        }
        return $config;
    }

    public function get()
    {
        if (!IS_LICENSED) {
            return '';
        }
        if (!($rid = intval($this->Get->get('rid')))) {
            $rid = null;
        }
        if (!($mid = intval($this->Get->get('mid')))) {
            $mid = null;
        }
        if (!($setid = intval($this->Get->get('setid')))) {
            $setid = null;
        }
        if ($mid) {
            $setid = null;
        }

        if (!Config::isOperable($rid, $mid, $setid)) {
            die403();
        }

        $Config =& $this->getConfig($rid, $mid, $setid);
        $ary_vars = [];
        $ary_vars['notice_mess'] = $this->Post->get('notice_mess');

        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        $aryStatus = $Config->getArray('banner_status');
        $amount = count($aryStatus) + 2;

        foreach ($aryStatus as $i => $status) {
            $id = uniqueString();
            if ($img = $Config->get('banner_img', '', $i)) {
                $xy = PublicStorage::getImageSize(ARCHIVES_DIR . $img);
                $Tpl->add('banner#img', [
                    'banner#img_id'    => $id,
                    'banner@img_id'   => $id,
                    'img'   => $img,
                    'x'     => isset($xy[0]) ? $xy[0] : 0,
                    'y'     => isset($xy[1]) ? $xy[1] : 0,
                    'url'   => $Config->get('banner_url', '', $i),
                    'alt'   => $Config->get('banner_alt', '', $i),
                    'attr1' => $Config->get('banner_attr1', '', $i),
                    'attr2' => $Config->get('banner_attr2', '', $i),
                    'datestart' => $Config->get('banner_datestart', '', $i),
                    'timestart' => $Config->get('banner_timestart', '', $i),
                    'dateend' => $Config->get('banner_dateend', '', $i),
                    'timeend' => $Config->get('banner_timeend', '', $i),
                    'target:checked#' . $Config->get('banner_target', '', $i) => config('attr_checked'),
                ]);
            } else {
                $Tpl->add('banner#src', [
                    'banner#src_id'    => $id,
                    'src'   => $Config->get('banner_src', '', $i),
                    'datestart' => $Config->get('banner_datestart', '', $i),
                    'timestart' => $Config->get('banner_timestart', '', $i),
                    'dateend' => $Config->get('banner_dateend', '', $i),
                    'timeend' => $Config->get('banner_timeend', '', $i),
                ]);
            }

            for ($j = 1; $j <= $amount; $j++) {
                $vars   = [
                    'value' => $j,
                    'label' => $j,
                ];
                if (($i + 1) == $j) {
                    $vars['selected'] = config('attr_selected');
                }
                $Tpl->add('sort:loop', $vars);
            }

            $vars   = ['id' => $id];
            if ('open' == $status) {
                $vars['status:checked#open'] = config('attr_checked');
            }
            $Tpl->add('banner:loop', $vars);
        }

        foreach (['src', 'img'] as $i => $type) {
            $id = uniqueString();
            for ($j = 1; $j <= $amount; $j++) {
                $vars   = [
                    'value' => $j,
                    'label' => $j,
                ];
                if (($amount - 2 + $i + 1) == $j) {
                    $vars['selected'] = config('attr_selected');
                }
                $Tpl->add('sort:loop', $vars);
            }

            $vars = [
                'banner#' . $type . '_id' => $id,
                'datestart' => '1000-01-01',
                'timestart' => '00:00:00',
                'dateend' => '9999-12-31',
                'timeend' => '23:59:59',
            ];
            if ('img' == $type) {
                $vars['target:checked#_blank']   = config('attr_checked');
            }
            $Tpl->add('banner#' . $type, $vars);
            $Tpl->add('banner:loop', [
                'status:checked#open' => config('attr_checked'),
                'id' => $id,
            ]);
        }

        $ary_vars['shortcutUrl'] = acmsLink([
            'bid'   => BID,
            'admin' => 'shortcut_edit',
            'query' => [
                'admin'  => ADMIN,
                'rid'   => $rid,
                'mid'   => $mid,
                'setid' => $setid
            ]
        ]);

        if (sessionWithAdministration()) {
            if (!empty($mid)) {
                $url    = acmsLink([
                    'bid'   => BID,
                    'admin' => 'module_index',
                ]);
            } elseif (!empty($rid)) {
                $url    = acmsLink([
                    'bid'   => BID,
                    'admin' => 'config_index',
                    'query' => [
                        'rid'   => $rid,
                    ],
                ]);
            } elseif ('shop' == substr(ADMIN, 0, 4)) {
                $url    = acmsLink([
                    'bid'   => BID,
                    'admin' => 'shop_menu',
                ]);
            } else {
                $url    = acmsLink([
                    'bid'   => BID,
                    'admin' => 'config_index',
                ]);
            }
        } else {
            $url    = acmsLink([
                'bid'   => BID,
                'admin' => 'top',
            ]);
        }
        $ary_vars['indexUrl']   = $url;

        $ary_vars['banner_limit'] = $Config->get('banner_limit');
        $ary_vars['banner_loop_class'] = $Config->get('banner_loop_class');
        $ary_vars['banner_size_large'] = $Config->get('banner_size_large');

        $order = $Config->get('banner_order');
        if (strlen($order) > 0) {
            $ary_vars[ 'banner_order:selected#' . $order ] = config('attr_selected');
        }

        $criterion = $Config->get('banner_size_large_criterion');
        if (strlen($criterion) > 0) {
            $ary_vars['banner_size_large_criterion:selected#' . $criterion] = config('attr_selected');
        }

        $Tpl->add(null, $ary_vars);

        return $Tpl->get();
    }
}
