<?php

class ACMS_GET_Admin_Dashboard_LicenseStatus extends ACMS_GET
{
    function get()
    {
        if (!sessionWithAdministration()) {
            return '';
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $vars = [];
        $vars['domain'] = LICENSE_DOMAIN;

        switch (LICENSE_EDITION) {
            case 'enterprise':
                $vars['edition'] = 'Enterprise';
                break;
            case 'professional':
                $vars['edition'] = 'Professional';
                break;
            case 'standard':
            default:
                $vars['edition'] = 'Standard';
                break;
        }

        $offset = strlen(DOMAIN) - strlen(LICENSE_DOMAIN);
        if (
            1
            && !((0 <= $offset)
            && LICENSE_DOMAIN == substr(DOMAIN, $offset))
            && !is_private_ip(DOMAIN)
        ) {
            $vars['matchDomain'] = gettext('ドメインが一致していません');
            $vars['caution'] = 'caution';
        }

        if (!IS_DEVELOPMENT && !!LICENSE_EXPIRE) {
            $vars['expire'] = date('Y-m-d H:i', strtotime(LICENSE_EXPIRE));
        }

        if (licenseHasUnlimitedUsers()) {
            $Tpl->add('unlimited');
        } else {
            $SQL = SQL::newSelect('user');
            $SQL->addSelect('*', 'amount', null, 'count');
            $SQL->addWhereOpr('user_auth', 'subscriber', '!=');
            $amount = DB::query($SQL->get(dsn()), 'one');
            $vars['amount'] = $amount;
            $vars['max'] = LICENSE_BLOG_LIMIT;
        }

        $type = [];
        if (defined('LICENSE_OPTION_SUBDOMAIN') && !!LICENSE_OPTION_SUBDOMAIN) {
            $type[] = gettext('サブドメイン拡張オプション');
        }
        if (defined('LICENSE_OPTION_OWNDOMAIN') && !!LICENSE_OPTION_OWNDOMAIN) {
            $type[] = gettext('独自ドメイン拡張オプション');
        }
        if (defined('LICENSE_OPTION_PLUSDOMAIN') && intval(LICENSE_OPTION_PLUSDOMAIN) > 0) {
            $type[] = gettext('独自ドメイン追加オプション') . '(' . LICENSE_OPTION_PLUSDOMAIN . ')';
        }
        if (defined('LICENSE_OPTION_OEM') && !!LICENSE_OPTION_OEM) {
            $type[] = gettext('OEMライセンス');
        }
        if (licenseHasUnlimitedUsers()) {
            $type[] = gettext('ユーザー数無制限オプション');
        }
        if (LICENSE_BLOG_LIMIT == 1 && !defined('LICENSE_PLAN')) {
            $type[] = gettext('Oneライセンス');
        }

        foreach ($type as $i => $val) {
            if ($i > 0) {
                $Tpl->add(['licenseType:glue', 'licenseType:loop']);
            }
            $Tpl->add(['licenseType:loop'], [
                'type' => $val,
            ]);
        }

        $Tpl->add(null, $vars);
        return $Tpl->get();
    }
}
