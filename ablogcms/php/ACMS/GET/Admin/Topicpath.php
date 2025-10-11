<?php

class ACMS_GET_Admin_Topicpath extends ACMS_GET_Admin
{
    function get()
    {
        if (!SUID) {
            return '';
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $blogs = [];

        //-----------
        // blog tree
        $DB = DB::singleton(dsn());

        $SQL = SQL::newSelect('blog');
        $SQL->addSelect('blog_id');
        $SQL->addSelect('blog_name');
        $SQL->addSelect('blog_parent');
        $SQL->addWhereIn('blog_id', Auth::getAuthorizedBlog(SUID));
        $SQL->setOrder('blog_left', 'ASC');
        $all = $DB->query($SQL->get(dsn()), 'all');

        foreach ($all as $blog) {
            $pbid = $blog['blog_parent'];
            if (!isset($blogs[$pbid])) {
                $blogs[$pbid] = [];
            }
            $blogs[$pbid][] = $blog;
        }

        $SQL = SQL::newSelect('blog');
        $SQL->addSelect('blog_id');
        $SQL->addSelect('blog_name');

        $fromLeft   = ACMS_RAM::blogLeft(BID);
        $fromRight  = ACMS_RAM::blogRight(BID);
        $toLeft     = ACMS_RAM::blogLeft(SBID);
        $toRight    = ACMS_RAM::blogRight(SBID);
        $SQL->addWhereBw('blog_left', $toLeft, $fromLeft);
        $SQL->addWhereBw('blog_right', $fromRight, $toRight);
        $SQL->setOrder('blog_left');
        $q  = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');
        $i  = 0;
        while ($row = $DB->next($statement)) {
            $bid    = intval($row['blog_id']);
            if (!empty($i)) {
                $Tpl->add('glue');
            }

            $topics = [];
            if (isset($blogs[$bid]) && count($blogs[$bid]) > 0) {
                $topics['child_blog'] = 1;
                foreach ($blogs[$bid] as $child) {
                    $Tpl->add(['childBlog:loop', 'topic:loop'], [
                        'name' => $child['blog_name'],
                        'blogUrl' => acmsLink([
                            'bid'   => $child['blog_id'],
                            'admin' => 'top'
                        ]),
                    ]);
                }
            }
            $topics += [
                'url'   => acmsLink([
                    'bid'   => $bid,
                    'admin' => 'top'
                ]),
                'label'  => $row['blog_name'],
            ];

            $Tpl->add('topic:loop', $topics);
            $i++;
        }

        $aryAdmin   = [];
        if ('form_log' == ADMIN) {
            $aryAdmin[] = 'form_index';
            $aryAdmin[] = 'form_edit';
            $aryAdmin[] = 'form_log';
        } elseif ('shop' == substr(ADMIN, 0, strlen('shop'))) {
            if ('shop_menu' != ADMIN) {
                $aryAdmin[] = 'shop_menu';
            }
            if (preg_match('@_edit$@', ADMIN)) {
                $aryAdmin[] = str_replace('_edit', '_index', ADMIN);
            }
            $aryAdmin[] = ADMIN;
        } elseif ('schedule' == substr(ADMIN, 0, strlen('schedule'))) {
            if ('schedule_index' != ADMIN) {
                $aryAdmin[] = 'schedule_index';
            }
            $aryAdmin[] = ADMIN;
        } elseif ('config_set_theme' == substr(ADMIN, 0, strlen('config_set_theme'))) {
            $aryAdmin[] = 'config_set_theme_index';
            $aryAdmin[] = 'rule_index';
            $aryAdmin[] = 'rule_edit';
        } elseif ('config_set_editor' == substr(ADMIN, 0, strlen('config_set_editor'))) {
            $aryAdmin[] = 'config_set_editor_index';
            $aryAdmin[] = 'rule_index';
            $aryAdmin[] = 'rule_edit';
        } elseif ('config_set_base' === substr(ADMIN, 0, strlen('config_set_base'))) {
            $aryAdmin[] = 'config_set_base_index';
            $aryAdmin[] = 'rule_index';
            $aryAdmin[] = 'rule_edit';
        } elseif ('config_theme' === ADMIN) {
            $aryAdmin[] = 'config_set_theme_index';
            $aryAdmin[] = 'config_theme';
            $aryAdmin[] = 'rule_index';
            $aryAdmin[] = 'rule_edit';
        } elseif ('config_editor' === ADMIN) {
            $aryAdmin[] = 'config_set_editor_index';
            $aryAdmin[] = 'config_editor';
            $aryAdmin[] = 'rule_index';
            $aryAdmin[] = 'rule_edit';
        } elseif (preg_match('/^config_(edit|unit|bulk-change)/', ADMIN)) {
            $aryAdmin[] = 'config_set_editor_index';
            $aryAdmin[] = 'config_editor';
            $aryAdmin[] = 'rule_index';
            $aryAdmin[] = 'rule_edit';
            $aryAdmin[] = ADMIN;
        } elseif ('config' === substr(ADMIN, 0, strlen('config'))) {
            $aryAdmin[] = 'config_set_base_index';
            if (!in_array(ADMIN, ['config_set_index', 'config_set_edit'])) {
                if ('config_import' !== ADMIN && 'config_export' !== ADMIN) {
                    $aryAdmin[] = 'config_index';
                    $aryAdmin[] = 'rule_index';
                    $aryAdmin[] = 'rule_edit';
                }
                if ('config_index' !== ADMIN) {
                    $aryAdmin[] = ADMIN;
                }
            }
            if ('config_set_edit' === ADMIN) {
                $aryAdmin[] = ADMIN;
            }
        } elseif ('module' == substr(ADMIN, 0, strlen('module'))) {
            $aryAdmin[] = 'module_index';
            if ('module_import' !== ADMIN) {
                $aryAdmin[] = 'rule_index';
                $aryAdmin[] = 'rule_edit';
            }
            if ('module_index' !== ADMIN) {
                $aryAdmin[] = ADMIN;
            }
        } elseif ('fix' == substr(ADMIN, 0, strlen('fix'))) {
            $aryAdmin[] = 'fix_index';
            if ('fix_index' <> ADMIN) {
                $aryAdmin[] = ADMIN;
            }
        } elseif (preg_match('@(\_edit|\_editor)$@', ADMIN)) {
            if (!('user_edit' == ADMIN and !sessionWithContribution())) {
                if ('blog_edit' !== ADMIN) {
                    $aryAdmin[] = str_replace(['_editor', '_edit'], ['_index', '_index'], ADMIN);
                }
            }
            $aryAdmin[] = ADMIN;
        } elseif ('form2-edit' === ADMIN) {
            $aryAdmin[] = 'entry_index';
            $aryAdmin[] = ADMIN;
        } elseif ('import' == substr(ADMIN, 0, strlen('import'))) {
            if ('import_index' != ADMIN) {
                $aryAdmin[] = 'import_index';
            }
            $aryAdmin[] = ADMIN;
        } elseif ('export' == substr(ADMIN, 0, strlen('export'))) {
            if ('export_index' != ADMIN) {
                $aryAdmin[] = 'export_index';
            }
            $aryAdmin[] = ADMIN;
        } elseif (ADMIN !== 'top') {
            $aryAdmin[] = ADMIN;
        }

        foreach ($aryAdmin as $admin) {
            $Tpl->add('glue');
            $Tpl->add($admin);
            if (preg_match('@_edit$@', $admin)) {
                $url = acmsLink([
                    'bid' => BID,
                    'uid' => UID,
                    'cid' => CID,
                    'eid' => EID,
                    'tag' => TAG,
                    'admin' => $admin,
                    'query' => Field::singleton('get'),
                ]);
            } elseif ($admin === 'config_set_default' || $admin === 'config_index' || $admin === 'rule_edit') {
                $url = acmsLink([
                    'bid' => BID,
                    'admin' => ADMIN,
                    'query' => [
                        'rid' => $this->Get->get('rid'),
                        'setid' => $this->Get->get('setid'),
                    ],
                ]);
            } else {
                $url = acmsLink([
                    'bid'   => BID,
                    'admin' => $admin,
                    'query' => [
                        'rid'   => $this->Get->get('rid'),
                        'mid'   => $this->Get->get('mid'),
                        'fmid'  => $this->Get->get('fmid'),
                    ],
                ]);
            }
            $topicVars = ['url' => $url];

            if ($admin === 'config_theme') {
                if ($configSets = $this->getConfigSets('theme')) {
                    $topicVars['config_set'] = 1;
                    foreach ($configSets as $set) {
                        $Tpl->add(['configSet:loop', 'topic:loop'], [
                            'name' => $set['name'],
                            'configSetUrl' => acmsLink([
                                'bid' => $set['bid'],
                                'admin' => ADMIN,
                                'query' => [
                                    'rid' => $this->Get->get('rid'),
                                    'setid' => $set['id'],
                                ]
                            ]),
                        ]);
                    }
                }
            }
            if ($admin === 'config_editor') {
                if ($configSets = $this->getConfigSets('editor')) {
                    $topicVars['config_set'] = 1;
                    foreach ($configSets as $set) {
                        $Tpl->add(['configSet:loop', 'topic:loop'], [
                            'name' => $set['name'],
                            'configSetUrl' => acmsLink([
                                'bid' => $set['bid'],
                                'admin' => ADMIN,
                                'query' => [
                                    'rid' => $this->Get->get('rid'),
                                    'setid' => $set['id'],
                                ]
                            ]),
                        ]);
                    }
                }
            }
            if ($admin === 'config_index') {
                $configSets = array_merge(
                    [
                        [
                            'id' => null,
                            'bid' => BID,
                            'name' => 'このブログのコンフィグ（レガシー設定）',
                        ]
                    ],
                    $this->getConfigSets(null)
                );

                $topicVars['config_set'] = 1;
                foreach ($configSets as $set) {
                    $Tpl->add(['configSet:loop', 'topic:loop'], [
                        'name' => $set['name'],
                        'configSetUrl' => acmsLink([
                            'bid' => $set['bid'],
                            'admin' => ADMIN,
                            'query' => [
                                'rid' => $this->Get->get('rid'),
                                'setid' => $set['id'],
                            ]
                        ]),
                    ]);
                }
            }
            if ($admin === 'rule_edit') {
                if ($rules = $this->getRule()) {
                    $topicVars['rule'] = 1;
                    foreach ($rules as $rule) {
                        $Tpl->add(['rule:loop', 'topic:loop'], [
                            'name' => $rule['name'],
                            'ruleUrl' => acmsLink([
                                'bid' => $rule['bid'],
                                'admin' => ADMIN,
                                'query' => [
                                    'setid' => $this->Get->get('setid'),
                                    'mid' => $this->Get->get('mid'),
                                    'rid' => $rule['id'],
                                ]
                            ]),
                        ]);
                    }
                }
            }
            $Tpl->add('topic:loop', $topicVars);
        }
        $rootConfig = Config::loadBlogField(RBID);
        $Tpl->add(null, [
            'blog_theme_logo@squarePath' => $rootConfig->get('blog_theme_logo@squarePath'),
        ]);

        return $Tpl->get();
    }

    /**
     * @param string|null $type
     * @return array{bid: int, id: int, name: string}[]
     */
    protected function getConfigSets($type = null)
    {
        $SQL = SQL::newSelect('config_set');
        $SQL->addWhereOpr('config_set_type', $type);
        $SQL->addWhereOpr('config_set_blog_id', BID);
        $SQL->setOrder('config_set_sort', 'ASC');

        $result = [];
        $all = DB::query($SQL->get(dsn()), 'all');
        foreach ($all as $item) {
            $result[] = [
                'id' => $item['config_set_id'],
                'bid' => $item['config_set_blog_id'],
                'name' => $item['config_set_name'],
            ];
        }
        return $result;
    }

    protected function getRule()
    {
        $SQL = SQL::newSelect('rule');
        $SQL->addLeftJoin('blog', 'blog_id', 'rule_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');

        $Where = SQL::newWhere();
        $Where->addWhereOpr('rule_blog_id', BID, '=', 'OR');
        $Where->addWhereOpr('rule_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);
        $SQL->addWhereOpr('rule_status', 'open');
        $SQL->setOrder('rule_sort');

        $result = [];
        $result[] = [
            'id' => null,
            'bid' => BID,
            'name' => gettext('ルールなし'),
        ];

        $all = DB::query($SQL->get(dsn()), 'all');
        foreach ($all as $item) {
            $result[] = [
                'id' => $item['rule_id'],
                'bid' => BID,
                'name' => $item['rule_name'],
            ];
        }
        return $result;
    }
}
