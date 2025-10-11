<?php

class ACMS_GET_Admin_Config_Media_Banner extends ACMS_GET_Admin
{
    protected function & getConfig($rid, $mid, $setid)
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
                    'media_banner_limit',
                    'media_banner_status',
                    'media_banner_src',
                    'media_banner_img',
                    'media_banner_alt',
                    'media_banner_attr1',
                    'media_banner_attr2',
                    'media_banner_target',
                    'media_banner_type',
                    'media_banner_link',
                    'media_banner_datestart',
                    'media_banner_timestart',
                    'media_banner_dateend',
                    'media_banner_timeend',
                    'media_banner_order',
                    'media_banner_label_attr1',
                    'media_banner_label_attr2',
                    'media_banner_hide_attr1',
                    'media_banner_hide_attr2',
                    'media_banner_tooltip_attr1',
                    'media_banner_tooltip_attr2'
                ] as $fd
            ) {
                $config->setField($fd, $_config->getArray($fd));
            }
        }
        return $config;
    }

    public function get()
    {
        if (!$rid = idval(ite($_GET, 'rid'))) {
            $rid = null;
        }

        if (!$mid = idval(ite($_GET, 'mid'))) {
            $mid = null;
        }
        if (!$setid = idval(ite($_GET, 'setid'))) {
            $setid = null;
        }

        if ($mid) {
            $setid = null;
        }

        $Config =& $this->getConfig($rid, $mid, $setid);
        $ary_vars = [];
        $ary_vars['notice_mess'] = $this->Post->get('notice_mess');

        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        $aryStatus = $Config->getArray('media_banner_status');
        $items = [];
        $mids = [];

        foreach ($aryStatus as $i => $status) {
            $type = $Config->get('media_banner_type', '', $i);
            $mid = $Config->get('media_banner_mid', '', $i);
            $source = $Config->get('media_banner_source', '', $i);

            if ($type === 'image') {
                $mids[] = $mid;
            }

            if ($source) {
                $source = html_entity_decode($source);
            }

            $items[] = [
                'media_banner_datestart' => $Config->get('media_banner_datestart', '', $i),
                'media_banner_timestart' => $Config->get('media_banner_timestart', '', $i),
                'media_banner_dateend' => $Config->get('media_banner_dateend', '', $i),
                'media_banner_timeend' => $Config->get('media_banner_timeend', '', $i),
                "media_banner_attr1" => $Config->get('media_banner_attr1', '', $i),
                "media_banner_attr2" => $Config->get('media_banner_attr2', '', $i),
                "media_banner_alt" => $Config->get('media_banner_alt', '', $i),
                "media_banner_target" => $Config->get('media_banner_target', '', $i),
                "media_banner_status" => $Config->get('media_banner_status', '', $i),
                "media_banner_override_link" => $Config->get('media_banner_link', '', $i),
                'media_banner_source' => $source,
                "media_banner_type" => $type,
                "media_banner_mid" => $mid,
            ];
        }

        $SQL = SQL::newSelect('media');
        $DB = DB::singleton(dsn());
        $SQL->addSelect('*');
        $SQL->addWhereIn('media_id', $mids);
        $row = $DB->query($SQL->get(dsn()), 'all');

        foreach ($items as $i => $item) {
            foreach ($row as $media) {
                if (intval($items[$i]['media_banner_mid']) === intval($media['media_id'])) {
                    if ($items[$i]['media_banner_type'] === 'image') {
                        $items[$i]['media_banner_preview'] = '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . Media::urlencode($media['media_path']);
                        if (isset($media['media_image_size'])) {
                            $sizes = explode(' x ', $media['media_image_size']);
                            $landscape = true;
                            if ($sizes && isset($sizes[0]) && isset($sizes[1])) {
                                $landscape = $sizes[0] > $sizes[1] ? true : false;
                            }
                            $items[$i]['media_banner_landscape'] = $landscape;
                        }
                    }
                    $items[$i]['media_banner_link'] = $media['media_field_2'];
                }
            }
        }

        $ary_vars = [];
        $hide1 = $Config->get('media_banner_hide_attr1');
        $hide2 = $Config->get('media_banner_hide_attr2');
        $order = $Config->get('media_banner_order');
        $ary_vars['media_banner_limit'] = $Config->get('media_banner_limit');
        $ary_vars['media_banner_parent_loop_class'] = $Config->get('media_banner_parent_loop_class');
        $ary_vars['media_banner_loop_class'] = $Config->get('media_banner_loop_class');
        $ary_vars['media_banner_label_attr1'] = $Config->get('media_banner_label_attr1');
        $ary_vars['media_banner_label_attr2'] = $Config->get('media_banner_label_attr2');
        $ary_vars['media_banner_tooltip_attr1'] = $Config->get('media_banner_tooltip_attr1');
        $ary_vars['media_banner_tooltip_attr2'] = $Config->get('media_banner_tooltip_attr2');
        $ary_vars['media_banner_hide_attr1'] = $hide1;
        $ary_vars['media_banner_hide_attr2'] = $hide2;

        if (strlen($order) > 0) {
            $ary_vars[ 'media_banner_order:selected#' . $order ] = config('attr_selected');
        }
        if ($hide1 === 'true') {
            $ary_vars['media_banner_hide_attr1:checked#' . $hide1 ] = config('attr_checked');
        }
        if ($hide2 === 'true') {
            $ary_vars['media_banner_hide_attr2:checked#' . $hide2 ] = config('attr_checked');
        }

        $ary_vars['media_banner_json'] = json_encode($items);

        $Tpl->add(null, $ary_vars);

        return $Tpl->get();
    }
}
