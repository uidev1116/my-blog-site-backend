<?php

class ACMS_POST_Fix_Sequence extends ACMS_POST_Fix
{
    function post()
    {
        if (!sessionWithAdministration()) {
            return false;
        }

        $DB  = DB::singleton(dsn());
        $seq = [
            'blog_id'                   => '1',
            'alias_id'                  => '0',
            'config_set_id'             => '0',
            'user_id'                   => '0',
            'category_id'               => '0',
            'entry_id'                  => '0',
            'comment_id'                => '0',
            'rule_id'                   => '0',
            'module_id'                 => '0',
            'form_id'                   => '0',
            'media_id'                  => '0',
            'role_id'                   => '0',
            'usergroup_id'              => '0',
            'approval_id'               => '0',
            'schedule_id'               => '0',
            'shop_address_id'           => '0',
            'webhook_id'                => '0',
            'audit_log_id'              => '0',
            'system_version'            => VERSION,
        ];

        foreach ($seq as $fd => $val) {
            if ($fd == 'system_version') {
                continue;
            }
            $fd_ = $fd;
            $SQL = SQL::newSelect(str_replace('_id', '', $fd));
            if (preg_match('/^shop_/', $fd)) {
                $fd = preg_replace('/^shop_/', '', $fd);
            }
            $SQL->addSelect($fd, $fd . '_max', null, 'max');
            $seq[$fd_] = $DB->query($SQL->get(dsn()), 'one');
            if ($seq[$fd_] == null) {
                $seq[$fd_] = 0;
            }
        }

        $SQL = SQL::newUpdate('sequence');
        foreach ($seq as $key => $val) {
            $SQL->addUpdate('sequence_' . $key, $val);
        }
        $DB->query($SQL->get(dsn()), 'exec');
        $this->Post->set('message', 'success');

        AcmsLogger::info('データ修正ツールで、シーケンスを修正しました');

        return $this->Post;
    }
}
