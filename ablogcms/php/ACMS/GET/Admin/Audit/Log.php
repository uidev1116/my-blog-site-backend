<?php

class ACMS_GET_Admin_Audit_Log extends ACMS_GET_Admin
{
    public function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $vars = [];
        $limits = configArray('admin_limit_option');
        $limit = LIMIT ? LIMIT : $limits[config('admin_limit_default')];
        $limit = intval($limit);
        $levels = $this->Get->getArray('level');
        $suid = $this->Get->get('suid', 0);
        $repository = App::make('acms-logger-repository');

        list($sql, $count) = $repository->getIndexSql($limit, PAGE, $levels, $suid, START, END);

        // 絞り込みフォームを組み立て
        if (!empty($levels)) {
            foreach ($levels as $level) {
                $vars['level:checked#' . $level] = config('attr_checked');
            }
        } else {
            foreach (['ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO'] as $level) {
                $vars['level:checked#' . $level] = config('attr_checked');
            }
        }
        if (!empty($suid)) {
            $vars['suid:selected#' . $suid] = config('attr_selected');
        }
        if (START && strpos(START, '1000-01-01') === false) {
            $vars['start'] = date('Y-m-d', strtotime(START));
        }
        if (END && strpos(END, '9999-12-31') === false) {
            $vars['end'] = date('Y-m-d', strtotime(END));
        }
        foreach ($limits as $val) {
            $limitVars = [
                'limit' => $val,
            ];
            if ($limit === $val) {
                $limitVars['selected'] = config('attr_selected');
            }
            $tpl->add('limit:loop', $limitVars);
        }

        // Not Found
        if (empty($count)) {
            $tpl->add('notFound');
            $tpl->add(null, $vars);
            return $tpl->get();
        }

        // Build Pagenation
        $vars += $this->buildPager(
            PAGE,
            $limit,
            $count,
            10,
            config('admin_pager_cur_attr'),
            $tpl,
            [],
            [
                'admin' => ADMIN,
            ]
        );

        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');

        // データを組み立て
        while ($log = DB::next($statement)) {
            $msgTemp = $log['audit_log_message'];
            $msgTemp = str_replace(["\r\n", "\r", "\n"], "\n", $msgTemp);
            $msgTemp = explode("\n", $msgTemp);
            $message = $msgTemp[0];
            if (isset($msgTemp[1])) {
                $message .= ' ...';
            }

            $item = [
                'id' => $log['audit_log_id'],
                'datetime' => $log['audit_log_datetime'],
                'level' => \Acms\Services\Logger\Level::getLevelNameJa($log['audit_log_level']),
                'levelClass' => $this->getLevelColorClass($log['audit_log_level_name']),
                'bid' => $log['audit_log_blog_id'],
                'blogName' => ACMS_RAM::blogName($log['audit_log_blog_id']),
                'message' => $message,
            ];
            if ($suid = $log['audit_log_session_uid']) {
                $item['suid'] = $suid;
                $item['userName'] = ACMS_RAM::userName($suid);
            }
            $tpl->add('log:loop', $item);
        }

        $tpl->add(null, $vars);
        return $tpl->get();
    }

    protected function getLevelColorClass($level)
    {
        switch ($level) {
            case 'DEBUG':
                return 'acms-admin-label-default';
            case 'INFO':
                return 'acms-admin-label-info';
            case 'NOTICE':
                return 'acms-admin-label-success';
            case 'WARNING':
                return 'acms-admin-label-warning';
            case 'ERROR':
                return 'acms-admin-label-warning';
            case 'CRITICAL':
                return 'acms-admin-label-danger';
            case 'ALERT':
                return 'acms-admin-label-danger';
            case 'EMERGENCY':
                return 'acms-admin-label-danger';
        }
        return 'acms-admin-label-default';
    }
}
