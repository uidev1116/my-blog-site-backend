<?php

class ACMS_GET_Admin_Rule_Index extends ACMS_GET_Admin
{
    public function get()
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

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('rule');
        $SQL->addLeftJoin('blog', 'blog_id', 'rule_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');

        $Where  = SQL::newWhere();
        $Where->addWhereOpr('rule_blog_id', BID, '=', 'OR');
        $Where->addWhereOpr('rule_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);

        $SQL->setOrder('rule_sort');
        $SQL->addOrder('rule_blog_id', 'DESC');

        if (!$all = $DB->query($SQL->get(dsn()), 'all')) {
            $Tpl->add('index#notFound');
            $Tpl->add(null, ['notice_mess' => 'show']);
            return $Tpl->get();
        }
        $cnt    = count($all);
        $sort   = 1;
        while ($row = array_shift($all)) {
            $rid    = intval($row['rule_id']);
            $Tpl->add('status#' . $row['rule_status']);

            if (BID !== intval($row['rule_blog_id'])) {
                $row['rule_scope'] = 'parental';
                $disabled              = config('attr_disabled');
            } else {
                $disabled              = '';
            }
            $Tpl->add('scope:touch#' . $row['rule_scope']);

            for ($i = 1; $i <= $cnt; $i++) {
                $vars   = [
                    'value' => $i,
                    'label' => $i,
                ];
                if ($sort == $i) {
                    $vars['selected'] = config('attr_selected');
                }
                $Tpl->add('sort:loop', $vars);
            }

            $vars   = [
                'rid'   => $rid,
                'sort'  => $sort,
                'scope' => $row['rule_scope'],
                'name'  => $row['rule_name'],
                'disabled'  => $disabled,
            ];

            $rbid       = intval($row['rule_blog_id']);
            if (BID === $rbid) {
                $Tpl->add('mine', $this->getLinkVars(BID, $rid));
            } elseif (
                0
                or ( roleAvailableUser() && roleAuthorization('rule_edit', $rbid) )
                or sessionWithAdministration($rbid)
            ) {
                $Tpl->add('notMinePermit', $this->getLinkVars($rbid, $rid));
            } else {
                $Tpl->add('notMine');
            }

            $Tpl->add('rule:loop', $vars);

            $sort++;
        }

        if (!$this->Post->isNull()) {
            $Tpl->add(null, ['notice_mess' => 'show']);
        }

        return $Tpl->get();
    }

    protected function getLinkVars($bid, $rid)
    {
        return [
            'itemUrl'   => acmsLink([
                'bid'   => $bid,
                'admin' => 'rule_edit',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
            'configSetBaseUrl' => acmsLink([
                'bid'   => BID,
                'admin' => 'config_set_base_index',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
            'configSetThemeUrl' => acmsLink([
                'bid'   => BID,
                'admin' => 'config_set_theme_index',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
            'configSetEditorUrl' => acmsLink([
                'bid'   => BID,
                'admin' => 'config_set_editor_index',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
            'moduleUrl' => acmsLink([
                'bid'   => BID,
                'admin' => 'module_index',
                'query' => new Field([
                    'rid'   => $rid,
                ]),
            ]),
        ];
    }
}
