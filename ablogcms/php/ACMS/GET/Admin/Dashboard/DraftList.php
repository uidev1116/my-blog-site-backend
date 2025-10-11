<?php

class ACMS_GET_Admin_Dashboard_DraftList extends ACMS_GET
{
    function get()
    {
        if (!sessionWithContribution()) {
            return '';
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];

        $limit  = LIMIT ? LIMIT : 5;
        $pagerDelta = 3;
        $pagerCurAttr = ' class="cur"';

        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('entry');
        $SQL->addWhereOpr('entry_status', 'draft');
        $SQL->addWhereOpr('entry_blog_id', BID);

        if (roleAvailableUser()) {
            $UID = !roleAuthorization('entry_edit_all', BID) ? SUID : UID;
        } else {
            if (!sessionWithCompilation()) {
                $UID = SUID;
            } else {
                $UID = UID;
            }
        }
        if ($UID) {
            $SQL->addWhereOpr('entry_user_id', $UID);
        }

        $Pager  = new SQL_Select($SQL);
        $Pager->setSelect('*', 'entry_amount', null, 'count');
        if (!$pageAmount = intval($DB->query($Pager->get(dsn()), 'one'))) {
            $Tpl->add('draft#notFound');
            $Tpl->add(null, $vars);
            return $Tpl->get();
        }

        $vars   += $this->buildPager(
            PAGE,
            $limit,
            $pageAmount,
            $pagerDelta,
            $pagerCurAttr,
            $Tpl,
            [],
            ['admin' => ADMIN]
        );

        $SQL->setLimit($limit, (PAGE - 1) * $limit);
        $SQL->addOrder('entry_updated_datetime', 'DESC');
        $q = $SQL->get(dsn());
        $all = $DB->query($q, 'all');

        foreach ($all as $row) {
            $eid = $row['entry_id'];
            $cid    = $row['entry_category_id'];
            $uid    = $row['entry_user_id'];
            $bid    = $row['entry_blog_id'];

            $_vars = [
                'userName'  => ACMS_RAM::userName($uid),
                'datetime'  => $row['entry_datetime'],
                'title' => $row['entry_title'],
                'entryUrl'  => acmsLink([
                    'admin' => null,
                    'bid'   => $bid,
                    'eid'   => $eid,
                ]),
                'editUrl' => acmsLink([
                    'admin'    => 'entry-edit',
                    'eid'   => $eid,
                ]),
            ];

            if ($cid) {
                $_vars   += [
                    'categoryName'  => ACMS_RAM::categoryName($cid),
                    'categoryUrl'   => acmsLink([
                        'admin' => 'category_edit',
                        'cid'   => $cid,
                    ]),
                ];
            }

            $Tpl->add('entry:loop', $_vars);
        }

        $Tpl->add(null, $vars);
        return $Tpl->get();
    }
}
