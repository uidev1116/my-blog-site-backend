<?php

class ACMS_GET_Admin_Rule_SelectGlobal extends ACMS_GET_Admin
{
    function get()
    {
        if (roleAvailableUser()) {
            if (!roleAuthorization('rule_edit', BID)) {
                die403();
            }
        } else {
            if (!sessionWithAdministration()) {
                die403();
            }
        }
        if (
            (is_string(ADMIN) && strpos(ADMIN, 'module_') === false) && // @phpstan-ignore-line
            (is_string(ADMIN) && strpos(ADMIN, 'config_') === false) && // @phpstan-ignore-line
            (is_string(ADMIN) && strpos(ADMIN, 'config_set_') === false) && // @phpstan-ignore-line
            (is_string(TPL) && strpos(TPL, 'ajax/module') === false) // @phpstan-ignore-line
        ) {
            return '';
        }
        $Tpl        = new Template($this->tpl, new ACMS_Corrector());
        $DB         = DB::singleton(dsn());
        $rootVars   = [];
        $rid        = $this->Get->get('rid');
        $query      = parseQuery(QUERY);

        if (!empty($rid)) {
            $rootVars['currentRule'] = ACMS_RAM::ruleName($rid);
        }

        $tmpQuery   = $query;
        unset($tmpQuery['rid']);
        $rootVars['defaultUrl'] = acmsLink([
            'bid'   => BID,
            'admin' => ADMIN,
            'query' => $tmpQuery,
        ], true);

        $SQL    = SQL::newSelect('rule');
        $SQL->addLeftJoin('blog', 'blog_id', 'rule_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');

        $Where  = SQL::newWhere();
        $Where->addWhereOpr('rule_blog_id', BID, '=', 'OR');
        $Where->addWhereOpr('rule_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);
        $SQL->addWhereOpr('rule_status', 'open');

        $SQL->setOrder('rule_sort');
        $all    = $DB->query($SQL->get(dsn()), 'all');

        $sort   = 1;
        while ($row = array_shift($all)) {
            $rid            = intval($row['rule_id']);
            $query['rid']   = $rid;
            $vars           = [
                'rid'   => $rid,
                'label' => $row['rule_name'],
                'url'   => acmsLink([
                    'bid'   => BID,
                    'admin' => ADMIN,
                    'query' => $query,
                ], true),
            ];
            $Tpl->add('rule:loop', $vars);

            $sort++;
        }
        $Tpl->add(null, $rootVars);

        return $Tpl->get();
    }

    function getLinkVars($bid, $rid)
    {
        return [
            'itemUrl'   => acmsLink([
                'bid'   => $bid,
                'admin' => 'rule_edit',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
            'configUrl' => acmsLink([
                'bid'   => $bid,
                'admin' => 'config_index',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
            'moduleUrl' => acmsLink([
                'bid'   => $bid,
                'admin' => 'module_index',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
        ];
    }
}
