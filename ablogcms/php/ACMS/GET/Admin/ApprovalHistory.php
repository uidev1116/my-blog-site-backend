<?php

class ACMS_GET_Admin_ApprovalHistory extends ACMS_GET_Admin
{
    public function get()
    {
        if ('approval_index' !== ADMIN) {
            return '';
        }
        if (!sessionWithApprovalAdministrator()) {
            die403();
        }

        $order  = ORDER ? ORDER : 'desc';
        $limits = configArray('admin_limit_option');
        $limit  = LIMIT ? LIMIT : $limits[config('admin_limit_default')];

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];

        //----------
        // init SQL
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('approval');
        $SQL->addWhereOpr('approval_type', 'comment', '<>');

        //-------
        // order
        $vars['order:selected#' . $order]  = config('attr_selected');

        //-------
        // limit
        foreach ($limits as $val) {
            $_vars  = ['limit' => $val];
            if ($limit == $val) {
                $_vars['selected'] = config('attr_selected');
            }
            $Tpl->add('limit:loop', $_vars);
        }

        $Pager  = new SQL_Select($SQL);
        $Pager->setSelect('*', 'entry_amount', null, 'count');
        if (!$pageAmount = intval($DB->query($Pager->get(dsn()), 'one'))) {
            $Tpl->add('index#notFound');
            $vars['notice_mess'] = 'show';
            $Tpl->add(null, $vars);
            return $Tpl->get();
        }

        $vars   += $this->buildPager(
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
        $SQL->setOrder('approval_datetime', $order);

        $q  = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        while ($row = $DB->next($statement)) {
            $_vars  = [];
            $rvid   = $row['approval_revision_id'];
            $type   = $row['approval_type'];
            $eid    = $row['approval_entry_id'];

            $REV    = SQL::newSelect('entry_rev');
            $REV->addSelect('entry_rev_memo');
            $REV->addSelect('entry_status');
            $REV->addSelect('entry_title');
            $REV->addWhereOpr('entry_id', $eid);
            $REV->addWhereOpr('entry_rev_id', $rvid);
            if (!!$rev = $DB->query($REV->get(dsn()), 'row')) {
                $_vars['title']         = $rev['entry_rev_memo'];
                $_type                  = $rev['entry_status'];
                if ($_type === 'trash' && $type === 'request') {
                    $type = $_type;
                }
            }

            $_vars   += [
                'eid'               => $eid,
                'rvid'              => $rvid,
                'type'              => $type,
                'entryTitle'        => ACMS_RAM::entryTitle($eid),
                'datetime'          => $row['approval_datetime'],
                'requestUser'       => ACMS_RAM::userName($row['approval_request_user_id']),
                'requestUserIcon'   => loadUserIcon($row['approval_request_user_id']),
                'comment'           => $row['approval_comment'],
                'revisionUrl'       => acmsLink([
                    'bid'   => ACMS_RAM::entryBlog($eid),
                    'eid'   => $eid,
                    'tpl'   => 'ajax/revision/preview.html',
                    'query' => [
                        'rvid'  => $rvid,
                        'trash' => 'show',
                    ],
                ]),
                'entryUrl'       => acmsLink([
                    'bid'   => ACMS_RAM::entryBlog($eid),
                    'eid'   => $eid,
                ]),
            ];
            $type = 'type#' . $type;
            $Tpl->add([$type, 'history:loop']);
            $Tpl->add('history:loop', $_vars);
        }

        $Tpl->add(null, $vars);
        return $Tpl->get();
    }
}
