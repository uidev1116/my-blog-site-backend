<?php

class ACMS_GET_User_Profile extends ACMS_GET
{
    function get()
    {
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('user');
        $SQL->addWhereOpr('user_pass', '', '<>');

        $SQL->addSelect('user_id');
        $SQL->addSelect('user_code');
        $SQL->addSelect('user_status');
        $SQL->addSelect('user_name');
        $SQL->addSelect('user_mail');
        $SQL->addSelect('user_mail_mobile');
        $SQL->addSelect('user_mail_magazine');
        $SQL->addSelect('user_mail_mobile_magazine');
        $SQL->addSelect('user_url');
        $SQL->addSelect('user_auth');
        $SQL->addWhereOpr('user_status', 'open');
        $SQL->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=');
        $SQL->addWhereOpr('user_blog_id', $this->bid);
        $SQL->addLeftJoin('entry', 'entry_user_id', 'user_id');

        if (config('user_profile_geolocation_on') === 'on') {
            $SQL->addLeftJoin('geo', 'geo_uid', 'user_id');
            $SQL->addSelect('geo_geometry', 'longitude', null, 'ST_X');
            $SQL->addSelect('geo_geometry', 'latitude', null, 'ST_Y');
        }

        $SQL->setGroup('user_id');

        // indexing
        if ('on' === config('user_profile_indexing')) {
            $SQL->addWhereOpr('user_indexing', 'on');
        }

        $aryAuth    = [];
        foreach (
            [
                'administrator', 'editor', 'contributor', 'subscriber'
            ] as $auth
        ) {
            if ('on' === config('user_profile_' . $auth)) {
                $aryAuth[] = $auth;
            }
        }
        $SQL->addWhereIn('user_auth', $aryAuth);

        if (!!($uid = intval($this->uid))) {
            $SQL->addWhereOpr('user_id', $uid);
            $SQL->setLimit(1);
        } else {
            ACMS_Filter::userOrder($SQL, config('user_profile_order'));
            $SQL->setLimit(intval(config('user_profile_limit')));
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        $q = $SQL->get(dsn());
        if (!($all = $DB->query($q, 'all'))) {
            $Tpl->add('notFound');
            return $Tpl->get();
        }

        foreach ($all as $row) {
            $vars           = $this->buildField(loadUserField(intval($row['user_id'])), $Tpl);
            foreach ($row as $key => $val) {
                if ($key === 'user_mail_magazine' || $key === 'user_mail_mobile_magazine') {
                    $val = $val === 'on' ? 'on' : 'off';
                }
                $vars[substr($key, strlen('user_'))] = $val;
            }
            $uid = intval($row['user_id']);
            $vars['icon'] = loadUserIcon($uid);
            if ($large = loadUserLargeIcon($uid)) {
                $vars['largeIcon'] = $large;
            }
            if ($orig = loadUserOriginalIcon($uid)) {
                $vars['origIcon'] = $orig;
            }
            if (isset($row['latitude'])) {
                $vars['geo_lat'] = $row['latitude'];
            }
            if (isset($row['longitude'])) {
                $vars['geo_lng'] = $row['longitude'];
            }
            $Tpl->add('user:loop', $vars);
        }

        return $Tpl->get();
    }
}
