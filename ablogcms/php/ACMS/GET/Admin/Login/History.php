<?php

use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Admin_Login_History extends ACMS_GET_Admin_User_Index
{
    /**
     * リミット数を取得
     *
     * @return int
     */
    protected function getLimit(): int
    {
        if (ADMIN === 'login_history') {
            $limits = configArray('admin_limit_option');
            $limit = LIMIT ? LIMIT : $limits[config('admin_limit_default')];
            return (int) $limit;
        }
        return 5;
    }

    protected function buildQuery(): SQL_Select
    {
        $sql = SQL::newSelect('last_access');
        $sql->addLeftJoin('user', 'user_id', 'last_access_uid');
        $sql->addLeftJoin('blog', 'blog_id', 'user_blog_id');
        ACMS_Filter::blogTree($sql, $this->targetBid, $this->getAxis());
        $this->filterKeyword($sql);
        $this->filterField($sql);
        $this->filterAuth($sql);
        $this->filterStatus($sql);

        return $sql;
    }

    /**
     * SQLを組み立て2
     *
     * @param SQL_Select $sql
     * @param int $limit
     * @param string $order
     * @return void
     */
    protected function buildQuery2(SQL_Select $sql, int $limit, string $order): void
    {
        $sql->setGroup('user_id');
        $sql->setLimit($limit, (PAGE - 1) * $limit);
        $order = ORDER ? ORDER : 'last_access_datetime-desc';
        $aryOrder = explode('-', $order);
        $orderField = $aryOrder[0] ?? 'last_access_datetime';
        $orderSeq = $aryOrder[1] ?? 'desc';
        $sql->setOrder($orderField, $orderSeq);
    }

    /**
     * テンプレートを組み立て
     *
     * @param SQL_Select $sql
     * @param Template $tpl
     * @param array $vars
     * @return void
     */
    protected function buildTemplate(SQL_Select $sql, Template $tpl, array $vars): void
    {
        $q = $sql->get(dsn());
        $users = DB::query($q, 'all');

        foreach ($users as $user) {
            $bid = (int) $user['user_blog_id'];
            $uid = (int) $user['user_id'];
            $data = [
                'uid' => $uid,
                'name' => $user['user_name'],
                'icon' => loadUserIcon($uid),
                'mail' => $user['user_mail'],
                'code' => $user['user_code'],
                'expiry' => strtotime($user['user_login_expire'] . ' 00:00:00') <= REQUEST_TIME ? 'expired' : '',
                'itemUrl' => acmsLink([
                    'admin' => 'user_edit',
                    'bid' => $bid,
                    'uid' => $uid,
                ]),
                'datetime' => $user['last_access_datetime'],
                'remoteAddr' => $user['last_access_ip'],
                'userAgent' => $user['last_access_ua'],
            ];
            $tpl->add(['status#' . $user['user_status'], 'user:loop']);
            $tpl->add(['auth#' . getAuthConsideringRole($uid), 'user:loop']);
            $tpl->add('user:loop', $data);
        }

        $vars['indexUrl'] = acmsLink([
            'bid' => BID,
            'admin' => 'login_history',
        ]);

        $tpl->add(null, $vars);
    }
}
