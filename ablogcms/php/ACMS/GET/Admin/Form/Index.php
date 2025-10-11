<?php

class ACMS_GET_Admin_Form_Index extends ACMS_GET_Admin
{
    function get()
    {
        if ('form_index' <> ADMIN && 'form2-edit' <> ADMIN) {
            return '';
        }
        if (
            0
            || ( !roleAvailableUser() && !sessionWithFormAdministration() )
            || ( roleAvailableUser() && !roleAuthorization('form_view', BID) && !roleAuthorization('form_edit', BID) )
        ) {
            die403();
        }
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $Vars   = [];

        //-------
        // order
        $order  = ORDER ? ORDER : 'id-asc';
        $Vars['order:selected#' . $order] = config('attr_selected');
        //--------
        // limit
        $limits = configArray('admin_limit_option');
        $limit  = (int)$this->Q->get('limit', $limits[config('admin_limit_default')]);
        foreach ($limits as $val) {
            $_vars  = ['value' => $val];
            if ($limit == $val) {
                $_vars['selected'] = config('attr_selected');
            }
            $Tpl->add('limit:loop', $_vars);
        }
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('form');
        $SQL->addSelect('log_form_datetime');
        $SQL->addSelect('log_form_form_id');
        $SQL->addLeftJoin('blog', 'blog_id', 'form_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');
        $Where  = SQL::newWhere();
        $Where->addWhereOpr('form_blog_id', BID, '=', 'OR');
        $Where->addWhereOpr('form_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);
        $Amount = new SQL_Select($SQL);
        $Amount->setSelect('*', 'form_amount', null, 'count');
        if (!$pageAmount = $DB->query($Amount->get(dsn()), 'one')) {
            $Tpl->add('index#notFound');
            $Vars['notice_mess'] = 'show';
            $Tpl->add(null, $Vars);
            return $Tpl->get();
        }
        $Vars   += $this->buildPager(
            PAGE,
            $limit,
            $pageAmount,
            config('admin_pager_delta'),
            config('admin_pager_cur_attr'),
            $Tpl,
            [],
            ['admin' => ADMIN]
        );
        $Log = SQL::newSelect('log_form');
        $Log->setOrder('log_form_datetime', 'DESC');
        $SQL->addLeftJoin($Log, 'log_form_form_id', 'form_id', 'log_form_ordered');
        $SQL->addSelect('form_id');
        $SQL->addSelect('form_code');
        $SQL->addSelect('form_name');
        $SQL->addSelect('form_scope');
        $SQL->addSelect('form_blog_id');
        $SQL->addSelect('log_form_datetime', 'form_log_amount', null, 'count');
        $Case = SQL::newCase();
        $Case->add(SQL::newOpr('log_form_datetime'), '1000-01-01 00:00:00');
        $Case->setElse(SQL::newFunction('log_form_datetime', 'MAX'));
        $SQL->addSelect($Case, 'form_last_datetime');
        //-------
        // order
        $base_order = explode('-', $order);
        $base       = $base_order[0];
        $ord        = $base_order[1];
        $ord   = ('asc' == $ord) ? 'ASC' : 'DESC';
        if ('code' == $base) {
            $SQL->setOrder('form_code', $ord);
        } elseif ('amount' == $base) {
            $SQL->setOrder('form_log_amount', $ord);
        } elseif ('datetime' == $base) {
            $SQL->setOrder('form_last_datetime', $ord);
        } else {
            $SQL->setOrder('form_id', $ord);
        }
        $SQL->setGroup('form_id');
        $SQL->setLimit($limit, (PAGE - 1) * $limit);
        $q  = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');
        while ($row = $DB->next($statement)) {
            $fmid   = intval($row['form_id']);
            $fmbid  = intval($row['form_blog_id']);
            $editAction = false;
            $logAction  = false;

            if (
                0
                || ( !roleAvailableUser() && sessionWithFormAdministration($fmbid) )
                || ( roleAvailableUser() && roleAuthorization('form_edit', $fmbid) )
            ) {
                $editAction = true;
            }
            if (
                0
                || ( !roleAvailableUser() && sessionWithFormAdministration(BID) )
                || ( roleAvailableUser() && roleAuthorization('form_view', BID) )
            ) {
                $logAction  = true;
            }
            if ($editAction) {
                $Tpl->add(['editAction', 'form:loop'], [
                    'itemUrl'   => acmsLink([
                        'bid'   => $fmbid,
                        'admin' => 'form_edit',
                        'query' => [
                            'fmid'  => $fmid,
                        ],
                    ]),
                ]);
            }
            if ($logAction) {
                $Tpl->add(['logAction', 'form:loop'], [
                    'logUrl'    => acmsLink([
                        'bid'   => BID,
                        'admin' => 'form_log',
                        'query' => [
                            'fmid'  => $fmid,
                        ],
                    ]),
                ]);
            }
            $Tpl->add('form:loop', [
                'code'      => $row['form_code'],
                'name'      => $row['form_name'],
                'datetime'  => $row['form_last_datetime'],
                'amount'    => $row['form_log_amount'],
                'scope'     => $row['form_scope'],
            ]);
        }
        $Tpl->add(null, $Vars);
        return $Tpl->get();
    }
}
