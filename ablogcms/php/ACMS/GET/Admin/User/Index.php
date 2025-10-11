<?php

use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Admin_User_Index extends ACMS_GET_Admin
{
    public $_scope = [
        'field'     => 'global'
    ];

    /**
     * 絞り込むBID
     *
     * @var int
     */
    protected $targetBid = 0;

    /**
     * Main
     *
     * @return string
     */
    public function get(): string
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $this->targetBid = intval($this->Get->get('_bid', BID));
        $vars = [];

        if (!$this->validate()) {
            return '';
        }
        $this->refresh($tpl, $vars);
        $this->buildFilter($tpl, $vars);
        $sql = $this->buildQuery();
        $amount = $this->getAmount($sql, $tpl, $vars);
        if (empty($amount)) {
            return $tpl->get();
        }
        $limit = $this->getLimit();
        $order = ORDER ? ORDER : 'sort-asc';
        $vars += TemplateHelper::buildPager(
            PAGE,
            $limit,
            $amount,
            config('admin_pager_delta'),
            config('admin_pager_cur_attr'),
            $tpl,
            [],
            ['admin' => ADMIN]
        );
        $this->buildQuery2($sql, $limit, $order);
        $this->buildTemplate($sql, $tpl, $vars);

        return $tpl->get();
    }

    /**
     * Validate
     *
     * @return bool
     */
    protected function validate(): bool
    {
        if (sessionWithAdministration()) {
            return true;
        }
        return false;
    }

    /**
     * POST時
     *
     * @param Template $tpl
     * @param array $vars
     * @return void
     */
    protected function refresh(Template $tpl, array &$vars): void
    {
        if ($this->Post->isNull()) {
            return;
        }
        // user limit error（権限の一括変更時）
        if (!$this->Post->isValid('user', 'limit')) {
            $tpl->add('user:validator#limit');
        }
    }

    /**
     * フィルターを組み立て
     *
     * @param Template $tpl
     * @param array $vars
     * @return void
     */
    protected function buildFilter(Template $tpl, array &$vars): void
    {
        // axis
        if (1 < ACMS_RAM::blogRight($this->targetBid) - ACMS_RAM::blogLeft($this->targetBid)) {
            $axis = $this->Get->get('axis', 'self');
            $tpl->add('axis', [
                'axis:checked#' . $axis => config('attr_checked')
            ]);
        } else {
            $axis = 'self';
        }

        // auth
        $auth = $this->Get->get('auth');
        $vars['auth:selected#' . $auth] = config('attr_selected');

        // status
        $status = $this->Get->get('status');
        $vars['status:selected#' . $status] = config('attr_selected');

        // order
        $order = ORDER ? ORDER : 'sort-asc';
        $vars['order:selected#' . $order] = config('attr_selected');
        if (
            1
            && $order === 'sort-asc'
            && !KEYWORD
            && empty($status)
            && empty($auth)
            && $axis === 'self'
        ) {
            $vars['sortable'] = 'on';
        } else {
            $vars['sortable'] = 'off';
        }

        // limit
        $limits = configArray('admin_limit_option');
        $limit = $this->getLimit();
        foreach ($limits as $val) {
            $_vars = ['value' => $val];
            if ($limit === intval($val)) {
                $_vars['selected'] = config('attr_selected');
            }
            $tpl->add('limit:loop', $_vars);
        }
    }

    /**
     * get axis
     * @return string
     */
    protected function getAxis(): string
    {
        $axis = $this->Get->get('axis', 'self');
        if (1 >= ACMS_RAM::blogRight($this->targetBid) - ACMS_RAM::blogLeft($this->targetBid)) {
            $axis = 'self';
        }
        return $axis;
    }

    /**
     * リミット数を取得
     *
     * @return int
     */
    protected function getLimit(): int
    {
        $limits = configArray('admin_limit_option');
        $limit = LIMIT ? LIMIT : $limits[config('admin_limit_default')];
        return (int) $limit;
    }

    /**
     * SQLを組み立て
     *
     * @return SQL_Select
     */
    protected function buildQuery(): SQL_Select
    {
        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_pass', '', '<>');
        $sql->addLeftJoin('blog', 'blog_id', 'user_blog_id');
        ACMS_Filter::blogTree($sql, $this->targetBid, $this->getAxis());
        ACMS_Filter::blogStatus($sql);

        $this->filterKeyword($sql);
        $this->filterField($sql);
        $this->filterAuth($sql);
        $this->filterStatus($sql);

        return $sql;
    }

    /**
     * キーワードで絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterKeyword(SQL_Select $sql): void
    {
        if (!!KEYWORD) {
            $sql->addLeftJoin('fulltext', 'fulltext_uid', 'user_id');
            $keywords = preg_split(REGEX_SEPARATER, KEYWORD, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($keywords as $keyword) {
                $sql->addWhereOpr('fulltext_value', '%' . $keyword . '%', 'LIKE');
            }
        }
    }

    /**
     * 権限で絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterAuth(SQL_Select $sql): void
    {
        $auth = $this->Get->get('auth');
        if (!empty($auth)) {
            $sql->addWhereOpr('user_auth', $auth);
        }
    }

    /**
     * ステータスで絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterStatus(SQL_Select $sql): void
    {
        $status = $this->Get->get('status');
        if (!empty($status)) {
            $sql->addWhereOpr('user_status', $status);
        }
    }

    /**
     * カスタムフィールドで絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterField(SQL_Select $sql): void
    {
        if (!empty($this->Field)) {
            ACMS_Filter::userField($sql, $this->Field);
        }
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
        $sql->addLeftJoin('entry', 'entry_user_id', 'user_id');
        $sql->addLeftJoin('last_access', 'last_access_uid', 'user_id');
        $sql->addSelect('user_id');
        $sql->addSelect('user_sort');
        $sql->addSelect('user_name');
        $sql->addSelect('user_mail');
        $sql->addSelect('user_code');
        $sql->addSelect('user_auth');
        $sql->addSelect('user_status');
        $sql->addSelect('user_login_expire');
        $sql->addSelect('user_blog_id');
        $sql->addSelect('last_access_datetime');
        $sql->addSelect('last_access_ip');
        $sql->addSelect('last_access_ua');
        $sql->addSelect('entry_user_id', 'entry_amount', null, 'COUNT');

        $sql->setGroup('user_id');
        $sql->setLimit($limit, (PAGE - 1) * $limit);
        ACMS_Filter::userOrder($sql, $order);
    }

    /**
     * ヒットしたユーザー数を取得
     *
     * @param SQL_Select $sql
     * @param Template $tpl
     * @param array $vars
     * @return int
     */
    protected function getAmount(SQL_Select $sql, Template $tpl, array $vars): int
    {
        $amount = new SQL_Select($sql);
        $amount->setSelect('*', 'user_amount', null, 'count');
        $pageAmount = intval(DB::query($amount->get(dsn()), 'one'));
        if (empty($pageAmount)) {
            $tpl->add('index#notFound');
            $tpl->add(null, $vars);
        }
        return $pageAmount;
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
        $axis = $this->getAxis();
        $canSwitchUser = config('switch_user_enable') === 'on';
        if (config('switch_user_permission') === 'root' && RBID != SBID) {
            $canSwitchUser = false;
        }
        $statement = DB::query($q, 'exec');
        while ($row = DB::next($statement)) {
            $bid = $row['user_blog_id'];
            $uid = $row['user_id'];
            $auth = getAuthConsideringRole($uid);
            $tpl->add('auth#' . $auth);
            if (isRoleAvailableUser($uid)) {
                $tpl->add('auth_default#' . $row['user_auth']);
            }
            $tpl->add('status#' . $row['user_status']);

            $_vars = [
                'uid' => $uid,
                'name' => $row['user_name'],
                'icon' => loadUserIcon($uid),
                'mail' => $row['user_mail'],
                'code' => $row['user_code'],
                'amount' => $row['entry_amount'],
                'expiry' => strtotime($row['user_login_expire'] . ' 00:00:00') <= REQUEST_TIME ? 'expired' : '',
                'lastAccessDatetime' => $row['last_access_datetime'],
                'lastAccessAddr' => $row['last_access_ip'],
                'lastAccessUserAgent' => $row['last_access_ua'],
                'itemUrl' => acmsLink([
                    'admin' => 'user_edit',
                    'bid' => $bid,
                    'uid' => $uid,
                ]),
            ];

            // switch user
            if (
                1
                && $canSwitchUser
                && SUID != $uid
                && !(config('switch_user_same_level') !== 'on' && $row['user_auth'] === 'administrator')
                && canSwitchUser($uid)
            ) {
                $_vars['catSwitchUser'] = 'yes';
            }

            // sort
            if ('self' === $axis) {
                $_vars += [
                    'sort' => $row['user_sort'],
                    'sort#uid' => $uid,
                ];
            }

            // field
            $_vars += $this->buildField(loadUserField($uid), $tpl, 'user:loop');

            $tpl->add('user:loop', $_vars);
        }

        // sort header, action
        if ('self' == $axis) {
            $tpl->add('sort#header');
            $tpl->add('sort#action');
        }
        $tpl->add(null, $vars);
    }
}
