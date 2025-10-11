<?php

use Acms\Services\Facades\Auth;

class ACMS_GET_Admin_Blog_Index extends ACMS_GET_Admin
{
    function get()
    {
        if ('blog_index' <> ADMIN && 'blog_edit' <> ADMIN) {
            die403();
        }
        if (!sessionWithAdministration()) {
            if (Auth::checkShortcut(['bid' => BID])) {
                return '';
            }
            die403();
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];

        //-------
        // order
        $order  = ORDER ? ORDER : 'sort-asc';

        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('blog');
        $SQL->addSelect('blog_id');
        $SQL->addSelect('blog_name');
        $SQL->addSelect('blog_status');
        $SQL->addSelect('blog_sort');
        $SQL->addSelect('blog_config_set_scope');
        $SQL->addSelect('blog_theme_set_scope');
        $SQL->addSelect('blog_editor_set_scope');
        $SQL->addSelect('config_set_name', 'configSetName', 'configSet');
        $SQL->addSelect('config_set_name', 'themeSetName', 'themeSet');
        $SQL->addSelect('config_set_name', 'editorSetName', 'editorSet');
        $SQL->addLeftJoin('config_set', 'config_set_id', 'blog_config_set_id', 'configSet');
        $SQL->addLeftJoin('config_set', 'config_set_id', 'blog_theme_set_id', 'themeSet');
        $SQL->addLeftJoin('config_set', 'config_set_id', 'blog_editor_set_id', 'editorSet');
        $SQL->addWhereOpr('blog_parent', BID);

        $Pager  = new SQL_Select($SQL);
        $Pager->setSelect('*', 'blog_amount', null, 'COUNT');
        if (!$amount = $DB->query($Pager->get(dsn()), 'one')) {
            $Tpl->add('index#notFound');
            $vars['notice_mess'] = 'show';
            $Tpl->add(null, $vars);
            return $Tpl->get();
        }

        $vars['order:selected#' . $order] = config('attr_selected');
        if ($order === 'sort-asc' || $order === 'sort-desc') {
            $vars['sortable'] = 'on';
        } else {
            $vars['sortable'] = 'off';
        }

        //-------
        // limit
        $limits = configArray('admin_limit_option');
        $limit  = LIMIT ? LIMIT : $limits[config('admin_limit_default')];
        $from   = (PAGE - 1) * $limit;

        $SQL->setLimit($limit, $from);

        foreach ($limits as $val) {
            $_vars  = [
                'value' => $val,
                'label' => $val,
            ];
            if ($limit == $val) {
                $_vars['selected'] = config('attr_selected');
            }
            $Tpl->add('limit:loop', $_vars);
        }

        $vars   += $this->buildPager(
            PAGE,
            $limit,
            $amount,
            config('admin_pager_delta'),
            config('admin_pager_cur_attr'),
            $Tpl,
            [],
            ['admin' => ADMIN]
        );

        ACMS_Filter::blogOrder($SQL, $order);

        $q  = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        while ($row = $DB->next($statement)) {
            $bid    = $row['blog_id'];
            $Tpl->add('status#' . $row['blog_status']);
            $_vars  = [
                'bid'       => $bid,
                'sort'      => $row['blog_sort'],
                'name'      => $row['blog_name'],
                'configSet' => $row['configSetName'],
                'configSetScope' => $row['blog_config_set_scope'],
                'themeSet' => $row['themeSetName'],
                'themeSetScope' => $row['blog_theme_set_scope'],
                'editorSet' => $row['editorSetName'],
                'editorSetScope' => $row['blog_editor_set_scope'],
                'urlValue'  => acmsLink(['bid' => $bid]),
                'urlLabel'  => acmsLink(['bid' => $bid]),
                'adminTopLink'  => acmsLink([
                    'bid'   => $bid,
                    'admin' => 'top',
                ]),
                'itemLink'  => acmsLink([
                    'bid'   => $bid,
                    'admin' => 'blog_edit',
                ]),
            ];
            if (isBlogGlobal($bid)) {
                $_vars['indexLink'] = acmsLink([
                    'bid'   => $bid,
                    'admin' => 'blog_index',
                ]);
                $Tpl->add(['branch', 'blog:loop']);
            }
            $Tpl->add('blog:loop', $_vars);
        }

        //---------
        // success
        if ($success = $this->Post->get('success')) {
            $vars['success'] = $success;
        }

        //--------
        // error
        if ($error = $this->Post->get('error')) {
            $vars['error'] = $error;
        }

        $Tpl->add(null, $vars);
        return $Tpl->get();
    }
}
