<?php

class ACMS_GET_Admin_Category_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if (roleAvailableUser()) {
            if (!roleAuthorization('category_edit', BID)) {
                die403();
            }
        } else {
            if (!sessionWithCompilation()) {
                die403();
            }
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $limits = configArray('admin_limit_option');
        $limit = LIMIT ? LIMIT : $limits[config('admin_limit_default')];
        $vars = [];

        //----------------------
        // category create auth
        if (
            0
            || !roleAvailableUser()
            || (roleAvailableUser() && roleAuthorization('category_create', BID))
        ) {
            $Tpl->add('action#categoryInsert');
        }

        $layered = true;
        if (
            0
            || config('admin_category_layered') === 'on'
            || TPL === 'ajax/arg/cid-reference.html'
        ) {
            $layered = false;
        }

        $_cid = (int)$this->Get->get('_cid', 0);

        //-------
        // order
        $order = ORDER ? ORDER : 'sort-asc';
        $vars['order:selected#' . $order] = config('attr_selected');
        if ($order === 'sort-asc') {
            $vars['sortable'] = 'on';
        } else {
            $vars['sortable'] = 'off';
        }

        //-------
        // limit
        foreach ($limits as $val) {
            $_vars = ['limit' => $val];
            if ($limit == $val) {
                $_vars['selected'] = config('attr_selected');
            }
            $Tpl->add('limit:loop', $_vars);
        }

        //-----
        // bid
        $target_bid = $this->Get->get('_bid', BID);

        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('category', 'master');
        $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        $SQL->addLeftJoin('config_set', 'config_set_id', 'category_config_set_id', 'configSet');
        $SQL->addLeftJoin('config_set', 'config_set_id', 'category_theme_set_id', 'themeSet');
        $SQL->addLeftJoin('config_set', 'config_set_id', 'category_editor_set_id', 'editorSet');
        ACMS_Filter::blogTree($SQL, $target_bid, 'ancestor-or-self');

        $SQL->addSelect('category_id', null, 'master');
        $SQL->addSelect('category_name', null, 'master');
        $SQL->addSelect('category_parent', null, 'master');
        $SQL->addSelect('category_left', null, 'master');
        $SQL->addSelect('category_sort', null, 'master');
        $SQL->addSelect('category_code', null, 'master');
        $SQL->addSelect('category_status', null, 'master');
        $SQL->addSelect('category_scope', null, 'master');
        $SQL->addSelect('category_blog_id', null, 'master');
        $SQL->addSelect('category_config_set_scope', null, 'master');
        $SQL->addSelect('category_theme_set_scope', null, 'master');
        $SQL->addSelect('category_editor_set_scope', null, 'master');
        $SQL->addSelect('config_set_name', 'configSetName', 'configSet');
        $SQL->addSelect('config_set_name', 'themeSetName', 'themeSet');
        $SQL->addSelect('config_set_name', 'editorSetName', 'editorSet');

        $Where = SQL::newWhere();
        $Where->addWhereOpr('category_blog_id', $target_bid, '=', 'OR', 'master');
        $Where->addWhereOpr('category_scope', 'global', '=', 'OR', 'master');
        $SQL->addWhere($Where);

        if ($layered) {
            $SQL->addWhereOpr('category_parent', $_cid, '=', 'AND', 'master');
        }

        //---------
        // keyword
        if (!!KEYWORD) {
            $SQL->addLeftJoin('fulltext', 'fulltext_cid', 'category_id', 'ft', 'master');
            $keywords = preg_split(REGEX_SEPARATER, KEYWORD, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($keywords as $keyword) {
                $SQL->addWhereOpr('fulltext_value', '%' . $keyword . '%', 'LIKE');
            }
        }

        //--------
        // amount
        $Amount = new SQL_Select($SQL);
        $Amount->addLeftJoin('entry', 'entry_category_id', 'category_id', 'entry', 'master');
        $Amount->addSelect('entry_id', 'category_entry_amount', 'entry', 'COUNT');
        $Amount->addGroup('category_id', 'master');
        $Amount->addWhereOpr('entry_status', 'trash', '<>', 'AND', 'entry');
        $cnts = $DB->query($Amount->get(dsn()), 'all');

        if ($layered) {
            $Pager = new SQL_Select($SQL);
            $Pager->setSelect(SQL::newFunction('category_id', 'DISTINCT', 'master'), 'category_amount', null, 'COUNT');
            $Pager->setGroup(null);
            if (!$pageAmount = intval($DB->query($Pager->get(dsn()), 'one'))) {
                $Tpl->add('index#notFound');
                $vars['notice_mess'] = 'show';
                $Tpl->add(null, $vars);
                return $Tpl->get();
            }
            $vars += $this->buildPager(
                PAGE,
                $limit,
                $pageAmount,
                config('admin_pager_delta'),
                config('admin_pager_cur_attr'),
                $Tpl,
                [],
                ['admin' => ADMIN]
            );
            $SQL->setLimit($limit, (PAGE - 1) * $limit);
        }

        foreach ($cnts as $cnt) {
            $total[$cnt['category_id']] = $cnt['category_entry_amount'];
        }
        ACMS_Filter::categoryOrder($SQL, $order);

        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');
        $row = $DB->next($statement);

        $categoryIds = [];
        $childCategories = [];
        do {
            if (!empty($row['category_id'])) {
                $categoryIds[] = $row['category_id'];
            }
        } while ($row = $DB->next($statement));


        if ($layered) {
            $CHILD = SQL::newSelect('category');
            $CHILD->addSelect('category_id');
            $CHILD->addSelect('category_parent');
            if (!empty($categoryIds)) {
                $CHILD->addWhereIn('category_parent', $categoryIds);
            }
            $all = $DB->query($CHILD->get(dsn()), 'all');
            foreach ($all as $category) {
                $childCategories[$category['category_parent']] = $category['category_id'];
            }
        }

        $all = [];
        $amount = [];
        $parent = [];
        $last = [];
        $statement = $DB->query($q, 'exec');
        while ($row = $DB->next($statement)) {
            $bid = intval($row['category_blog_id']);
            $cid = intval($row['category_id']);
            $pid = intval($row['category_parent']);
            $all[$pid][] = $row;
            $parent[$cid] = $pid;
            $last[$pid] = $cid;
            if (!isset($amount[$bid][$pid])) {
                $amount[$bid][$pid] = 0;
            }
            $amount[$bid][$pid] += 1;
        };

        $stack = [];
        if ($layered) {
            $stack = isset($all[$_cid]) ? $all[$_cid] : [];
        } elseif (isset($all[0])) {
            $stack = $all[0];
        }
        unset($all[0]);
        $last = array_flip($last);

        while ($row = array_shift($stack)) {
            $bid = intval($row['category_blog_id']);
            $cid = intval($row['category_id']);
            $pid = intval($row['category_parent']);
            $sort = intval($row['category_sort']);
            $Tpl->add('status#' . $row['category_status']);

            if (BID !== intval($row['category_blog_id'])) {
                $row['category_scope'] = 'parental';
                $disabled = config('attr_disabled');
            } else {
                $disabled = '';
            }

            $blocks = [];
            if (!empty($parent[$cid])) {
                $blocks[] = isset($last[$cid]) ? 'child#last' : 'child';
                $_pid = $cid;
                while ($_pid = $parent[$_pid]) {
                    if (empty($parent[$_pid])) {
                        break;
                    }
                    $blocks[] = isset($last[$_pid]) ? 'descendant#last' : 'descendant';
                }
            }

            $level = 0;
            foreach (array_reverse($blocks) as $block) {
                $Tpl->add($block);
                $Tpl->add('indent:loop');
                $level++;
            }

            $Tpl->add('scope:touch#' . $row['category_scope']);

            $cvars = [
                'cid' => $cid,
                'sort' => $sort,
                'pcid' => $pid,
                'name' => $row['category_name'],
                'code' => $row['category_code'],
                'scope' => $row['category_scope'],
                'amount' => empty($total[$cid]) ? 0 : $total[$cid],//$row['category_entry_amount'],
                'configSet' => $row['configSetName'],
                'configSetScope' => $row['category_config_set_scope'],
                'themeSet' => $row['themeSetName'],
                'themeSetScope' => $row['category_theme_set_scope'],
                'editorSet' => $row['editorSetName'],
                'editorSetScope' => $row['category_editor_set_scope'],
                'disabled' => $disabled,
                'level' => $level,
            ];

            $cbid = intval($row['category_blog_id']);
            if (BID === $cbid) {
                $Tpl->add('mine', [
                    'itemLink' => acmsLink([
                        'bid' => BID,
                        'cid' => $cid,
                        'admin' => 'category_edit',
                    ])
                ]);
            } else {
                if (
                    0
                    or (roleAvailableUser() && roleAuthorization('category_edit', $cbid))
                    or sessionWithAdministration($cbid)
                ) {
                    $Tpl->add('notMinePermit', [
                        'itemLink' => acmsLink([
                            'bid' => $cbid,
                            'cid' => $cid,
                            'admin' => 'category_edit',
                        ])
                    ]);
                } else {
                    $Tpl->add('notMine');
                }
            }

            //-------
            // field
            $cvars += $this->buildField(loadCategoryField($cid), $Tpl, 'category:loop');

            if (
                1
                && $layered
                && isset($childCategories[$cid])
                && $childCategories[$cid] > 0
            ) {
                $Tpl->add(['childLink', 'category:loop'], [
                    'parent_cid' => $cid,
                    'pre_cid' => $_cid,
                    'childLink' => acmsLink([
                        'admin' => 'category_index',
                        'page' => 1,
                        'query' => [
                            '_cid' => $cid,
                            'pre' => $_cid,
                        ],
                    ], true),
                ]);
            }
            $Tpl->add('category:loop', $cvars);

            if (isset($all[$cid])) {
                while ($_row = array_pop($all[$cid])) {
                    array_unshift($stack, $_row);
                }
                unset($all[$cid]);
            }
        }

        $Tpl->add(null, $vars);
        return $Tpl->get();
    }
}
