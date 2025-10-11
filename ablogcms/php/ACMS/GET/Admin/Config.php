<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Config;

class ACMS_GET_Admin_Config extends ACMS_GET_Admin
{
    /**
     * @param int|null $rid
     * @param int|null $mid
     * @param int|null $setid
     * @return \Field
     */
    public function & getConfig($rid, $mid, $setid = null)
    {
        $post_config =& $this->Post->getChild('config');

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
                    'links_label',
                    'links_value',
                    'navigation_label',
                    'navigation_uri',
                    'navigation_attr',
                    'navigation_a_attr',
                    'navigation_parent',
                    'navigation_target',
                    'navigation_publish',
                    'navigation_media',
                    'navigation_media_type',
                    'navigation_media_thumbnail',
                ] as $fd
            ) {
                $config->setField($fd, $_config->getArray($fd));
            }
        }
        $config->set('session_cookie_lifetime', env('SESSION_COOKIE_LIFETIME', '259200'));

        if (!$post_config->isNull() && ADMIN !== 'config_unit') {
            $config->overload($post_config);
            $post_config->overload($config);
            return $post_config;
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

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];
        $Config =& $this->getConfig($rid, $mid, $setid);

        if (!$this->Post->isValidAll()) {
            $Tpl->add('msg#error');
        }

        // add alert email info
        $Config->setField('alert_email_from', env('ALERT_EMAIL_FROM'));
        $Config->setField('alert_email_to', env('ALERT_EMAIL_TO'));
        $Config->setField('alert_email_bcc', env('ALERT_EMAIL_BCC'));

        //----------------
        // file extension
        $Config->setField(
            'file_extension_document@list',
            join(', ', $Config->getArray('file_extension_document'))
        );
        $Config->setField('file_extension_document');
        $Config->setField(
            'file_extension_archive@list',
            join(', ', $Config->getArray('file_extension_archive'))
        );
        $Config->setField('file_extension_archive');
        $Config->setField(
            'file_extension_movie@list',
            join(', ', $Config->getArray('file_extension_movie'))
        );
        $Config->setField('file_extension_movie');
        $Config->setField(
            'file_extension_audio@list',
            join(', ', $Config->getArray('file_extension_audio'))
        );
        $Config->setField('file_extension_audio');

        $admin  = ADMIN;
        if ($mid) {
            $module = loadModule($mid);
            $admin  = 'config_' . strtolower((string) preg_replace('@(?<=[a-zA-Z0-9])([A-Z])@', '-$1', $module->get('name')));
        }

        $vars['shortcutUrl'] = acmsLink([
            'bid'   => BID,
            'admin' => 'shortcut_edit',
            'query' => [
                'admin'  => $admin,
                'rid'   => $rid,
                'mid'   => $mid,
                'setid' => $setid
            ]
        ]);

        $vars += $this->buildColumn($Config, $Tpl);

        //-----------------
        // image unit size
        // Configを変質させてしまうので、Admin_Entry_Editとは同居できない
        // buildColumnメソッド内で、column_image_sizeを利用しているので、
        // この処理より後に、buildColumnメソッドは実行できない。
        if ($sizes = $Config->getArray('column_image_size')) {
            foreach ($sizes as $i => $size) {
                $sizes[$i] = preg_replace('/([^\d]*)/', '', $size);
            }
            $Config->set('column_image_size', $sizes);
        }
        if ($large_size = $Config->get('image_size_large')) {
            $Config->set('image_size_large', preg_replace('/([^\d]*)/', '', $large_size));
        }
        if ($tiny_size = $Config->get('image_size_tiny')) {
            $Config->set('image_size_tiny', preg_replace('/([^\d]*)/', '', $tiny_size));
        }

        $vars   += $this->buildNavigation($Config, $Tpl);
        $vars   += $this->buildField($Config, $Tpl, [], 'config');

        $vars['notice_mess'] = $this->Post->get('notice_mess');

        // 一覧ページ
        if (!$rid = idval($this->Get->get('rid'))) {
            $rid = null;
        }
        if (!$mid = idval($this->Get->get('mid'))) {
            $mid = null;
        }
        if (!$setid = idval($this->Get->get('setid'))) {
            $setid = null;
        }

        $vars['indexUrl']   = $this->getIndexUrl($rid, $mid, $setid);

        $this->extendTemplate($vars, $Tpl);
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }

    function extendTemplate(&$vars, &$Tpl)
    {
    }

    function getIndexUrl($rid, $mid, $setid)
    {
        $url = '';
        if (Config::canViewIndex(BID)) {
            if ($mid) {
                $url    = acmsLink([
                    'bid'   => BID,
                    'admin' => 'module_index',
                ]);
            } elseif ($rid || $setid) {
                $url    = acmsLink([
                    'bid'   => BID,
                    'admin' => 'config_index',
                    'query' => [
                        'rid' => $rid,
                        'setid' => $setid,
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
        return $url;
    }

    function bulidImageSize()
    {
    }

    /**
     * ユニット設定のテンプレートを組み立てる
     *
     * @param \Field $config 設定情報を含むFieldオブジェクト
     * @param \Template $tpl テンプレートオブジェクト
     * @param array|string $rootBlock ルートブロック名
     * @return array 空の配列を返す
     */
    private function buildColumn(\Field $config, \Template $tpl, $rootBlock = [])
    {
        $repository = Application::make('unit-repository');
        assert($repository instanceof \Acms\Services\Unit\Repository);
        if (!is_array($rootBlock)) {
            $rootBlock = [$rootBlock];
        }
        array_unshift($rootBlock, 'Config_Column');

        // typeで参照できるラベルの連想配列
        /** @var array<string, string> $typesLabel */
        $typesLabel = [];
        foreach ($config->getArray('column_add_type') as $i => $type) {
            $typesLabel[$type] = $config->get('column_add_type_label', '', $i);
        }

        /** @var array<string, string> $labels */
        $labels = $config->getArray('column_add_type_label');
        /** @var array<string, string> $unitConfigs */
        $unitConfigs = ['insert' => '新規エントリー作成'];
        foreach ($config->getArray('column_add_type') as $type) {
            $label = array_shift($labels);
            $unitConfigs['add_' . $type] = $label;
        }

        foreach ($unitConfigs as $id => $label) {
            $json = [];
            $prefix = 'column_def_' . $id . '_';
            $types = $config->getArray($prefix . 'type');
            $aligns = $config->getArray($prefix . 'align', true);
            $groups = $config->getArray($prefix . 'group', true);
            $sizes = $config->getArray($prefix . 'size', true);
            $edits = $config->getArray($prefix . 'edit', true);
            $field1s = $config->getArray($prefix . 'field_1', true);
            $field2s = $config->getArray($prefix . 'field_2', true);
            $field3s = $config->getArray($prefix . 'field_3', true);
            $field4s = $config->getArray($prefix . 'field_4', true);
            $field5s = $config->getArray($prefix . 'field_5', true);

            foreach ($types as $i => $type) {
                $item = [
                    'id' => uuidv4(),
                    'name' => $typesLabel[$type] ?? '',
                    'collapsed' => true,
                    'type' => $type,
                    'align' => isset($aligns[$i]) ? $aligns[$i] : '',
                    'group' => isset($groups[$i]) ? $groups[$i] : '',
                    'size' => isset($sizes[$i]) ? $sizes[$i] : '',
                    'edit' => isset($edits[$i]) ? $edits[$i] : '',
                    'field_1' => isset($field1s[$i]) ? $field1s[$i] : '',
                    'field_2' => isset($field2s[$i]) ? $field2s[$i] : '',
                    'field_3' => isset($field3s[$i]) ? $field3s[$i] : '',
                    'field_4' => isset($field4s[$i]) ? $field4s[$i] : '',
                    'field_5' => isset($field5s[$i]) ? $field5s[$i] : '',
                ];
                $model = $repository->makeModel($type);
                if ($model === null) {
                    throw new \LogicException(sprintf('Unit type "%s" is not registered.', $type));
                }
                if ($model instanceof \Acms\Services\Unit\Contracts\ConfigProcessable) {
                    $item = $model->processConfig($item);
                }
                $json[] = $item;
            }

            $tpl->add(array_merge(['mode:loop'], $rootBlock), [
                'id' => $id,
                'label' => $label,
                'json' => json_encode($json),
            ]);
        }
        $tpl->add($rootBlock);

        return [];
    }

    function buildNavigation(&$Config, &$Tpl, $rootBlock = [])
    {
        if (!is_array($rootBlock)) {
            $rootBlock = [$rootBlock];
        }
        array_unshift($rootBlock, 'Config_Navigation');
        $addNum = 0;

        $Count = [0 => $addNum];
        $Parent = [0 => []];
        $mediaIds = [];
        foreach ($Config->getArray('navigation_label') as $i => $label) {
            $mediaIds[] = intval($Config->get('navigation_media', null, $i));
        }
        $eagerLoadMedia = Media::mediaEagerLoad($mediaIds);
        foreach ($Config->getArray('navigation_label') as $i => $label) {
            $id = $i + 1;
            $pid = intval($Config->get('navigation_parent', 0, $i));
            $mediaId = $Config->get('navigation_media', null, $i);
            $mediaId = $mediaId ? (int) $mediaId : null;
            $mediaType = $eagerLoadMedia[$mediaId]['media_type'] ?? null;
            $mediaThumbnail = $eagerLoadMedia[$mediaId]['media_path'] ?? null;
            $Parent[$pid][$id] = [
                'id'        => $id,
                'pid'       => $pid,
                'label'     => $label,
                'uri'       => $Config->get('navigation_uri', null, $i),
                'target'    => $Config->get('navigation_target', null, $i),
                'publish'   => $Config->get('navigation_publish', null, $i),
                'attr'      => $Config->get('navigation_attr', null, $i),
                'a_attr'    => $Config->get('navigation_a_attr', null, $i),
                'media'     => $mediaId,
                'media_type' => $mediaType,
                'media_thumbnail' => $mediaThumbnail ? Common::toAbsoluteUrl($mediaThumbnail, MEDIA_LIBRARY_DIR) : '',
                'marks'     => [],
            ];
            $Count[$pid]    = (isset($Count[$pid]) ? $Count[$pid] : 0) + 1;
        }

        $all        = [];
        $pidStack   = [0];
        $aryMark    = [''];
        while (count($pidStack)) {
            $pid    = array_pop($pidStack);
            $mark   = array_pop($aryMark);
            while ($row = array_shift($Parent[$pid])) {
                $id = $row['id'];

                $row['marks']   = array_merge([count($Parent[$pid]) ? 1 : 0], $aryMark);
                $all[] = $row;

                if (isset($Parent[$id])) {
                    if (count($Parent[$pid])) {
                        $aryMark[] = 3;
                    } else {
                        $aryMark[] = 2;
                    }
                    $aryMark[] = $mark;
                    $pidStack[] = $pid;
                    $pidStack[] = $id;
                    break;
                }
            }
        }

        //---------------
        // parent select
        $PSelect    = [];
        foreach ($all as $row) {
            $label  = $row['label'];

            //--------
            // indent
            $mark   = '';
            $marks  = array_reverse($row['marks']);
            $cnt    = count($row['marks']);
            for ($i = 1; $i < $cnt; $i++) {
                if (!isset($marks[$i])) {
                    continue;
                }
                $mark   .= $Config->get('indent_marks', '', $marks[$i]);
            }

            $PSelect[$row['id']]    = $mark . htmlspecialchars($label);
        }

        $seq    = 0;
        $Sort   = [];
        $length = count($all) - 1;
        foreach ($all as $row) {
            $id     = $row['id'];
            $pid    = $row['pid'];
            $marks  = $row['marks'];

            $Sort[$pid] = (isset($Sort[$pid]) ? $Sort[$pid] : 0) + 1;

            //--------
            // indent
            $level  = 0;
            $marks  = array_reverse($marks);
            foreach ($marks as $i => $_mark) {
                if (empty($i)) {
                    continue;
                }
                if (0 == $_mark) {
                    $block  = 'child#last';
                } elseif (1 == $_mark) {
                    $block  = 'child';
                } elseif (2 == $_mark) {
                    $block  = 'descendant#last';
                } elseif (3 == $_mark) {
                    $block  = 'descendant';
                } else {
                    continue;
                }
                $Tpl->add(array_merge([$block, 'navigation:loop'], $rootBlock));
                $level++;
            }

            //------
            // sort
            for ($i = 1; $i <= $Count[$pid]; $i++) {
                $vars   = [
                    'label' => $i,
                    'value' => $i,
                ];
                if ($i == $Sort[$pid]) {
                    $vars['selected'] = $Config->get('attr_selected');
                }
                $Tpl->add(array_merge(['sort:loop', 'navigation:loop'], $rootBlock), $vars);
            }

            //---------------
            // parent select
            foreach ($PSelect as $_id => $_label) {
                $vars   = [
                    'value' => $_id,
                    'label' => $_label,
                ];
                if ($pid == $_id) {
                    $vars['selected']   = $Config->get('attr_selected');
                }
                $Tpl->add(array_merge(['parent:loop', 'navigation:loop'], $rootBlock), $vars);
            }

            $vars   = [
                'seq'   => $seq,
                'level' => $level,
                'pseq'  => $pid,
                'label' => $row['label'],
                'uri'   => $row['uri'],
                'attr'  => $row['attr'],
                'a_attr' => $row['a_attr'],
                'media' => $row['media'],
                'media_type' => $row['media_type'],
                'media_thumbnail' => $row['media_thumbnail'],
                'navigation_target:checked#' . $row['target'] => $Config->get('attr_checked'),
                'navigation_publish:checked#' . $row['publish'] => $Config->get('attr_checked'),
            ];
            if ($length !== $seq) {
                $Tpl->add('glue');
            }
            $Tpl->add(array_merge(['navigation:loop'], $rootBlock), $vars);
            $seq++;
        }
        $Tpl->add($rootBlock);

        return [];
    }
}
