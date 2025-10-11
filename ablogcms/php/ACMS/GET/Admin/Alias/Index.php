<?php

/*
 * aid:null を aid:0 として扱う
 * htmlのフォーム上で <input type="checkbox" name="..." value="{aid}" />
 * とあるときに、value=""が値がなくなってしまうため。
 */

class ACMS_GET_Admin_Alias_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if ('alias_index' != ADMIN) {
            return '';
        }
        if (!sessionWithAdministration()) {
            die403();
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        //-----
        // msg
        if (!$this->Post->isNull()) {
            if ($this->Post->isValidAll()) {
                $Tpl->add('msg#success');
            } else {
                $Tpl->add('msg#error', $this->buildField($this->Post, $Tpl, 'msg#error'));
            }
        }

        $DB = DB::singleton(dsn());

        $WHERE = SQL::newWhere();
        $WHERE->addWhereOpr('alias_scope', 'global', '=');
        $WHERE->addWhereOpr('blog_left', ACMS_RAM::blogLeft(BID), '<');
        $WHERE->addWhereOpr('blog_right', ACMS_RAM::blogRight(BID), '>');

        $SQL = SQL::newSelect('alias');
        $SQL->addLeftJoin('blog', 'alias_blog_id', 'blog_id');
        $SQL->addWhereOpr('alias_blog_id', BID, '=', 'OR');
        $SQL->addWhere($WHERE, 'OR');
        $SQL->setOrder('alias_sort');
        $all = $DB->query($SQL->get(dsn()), 'all');

        $offset = intval(ACMS_RAM::blogAliasSort(BID)) - 1;
        if (0 > $offset) {
            $offset = 0;
        }
        array_splice($all, $offset, 0, [[
            'alias_name' => ACMS_RAM::blogName(BID),
            'alias_domain' => ACMS_RAM::blogDomain(BID),
            'alias_code' => ACMS_RAM::blogCode(BID),
            'alias_status' => ACMS_RAM::blogAliasStatus(BID),
            'alias_scope' => 'self',
            'alias_id' => 0,
        ]
        ]);

        $primary = intval(ACMS_RAM::blogAliasPrimary(BID));
        $count = count($all);

        foreach ($all as $i => $row) {
            $aid = intval($row['alias_id']);
            $url = 'http://' . $row['alias_domain'];

            // 元ブログのドメインと同じなら，スクリプトルートは同一とみなし，DIR_OFFSETを適用する
            if (
                1
                && ACMS_RAM::blogDomain(BID) === $row['alias_domain']
                && DIR_OFFSET
            ) {
                $url .= '/' . rtrim(DIR_OFFSET, '/');
            }
            if (!empty($row['alias_code'])) {
                $url .= '/' . $row['alias_code'];
            }
            $url .= '/';
            $name = $row['alias_name'];
            $domain = $row['alias_domain'];
            $code = $row['alias_code'];
            $bcode = ACMS_RAM::blogCode(BID) . '/';

            $var = [
                'sort' => $i + 1,
                'name' => $name,
                'domain' => $domain,
                'code' => $code,
                'aid' => $row['alias_id'],
                'urlLable' => $url,
                'urlValue' => $url,
            ];

            if ($row['alias_scope'] === 'global' && $row['alias_blog_id'] != BID) {
                $var['urlLable'] = $url . $bcode;
                $var['urlValue'] = $url . $bcode;
                $var['code'] = $bcode;
                $var['disabled'] = config('attr_disabled');
                $Tpl->add('action#root');
            } elseif (!empty($aid)) {
                $var['aidLabel'] = $aid;
                $var['itemUrl'] = acmsLink([
                    'bid' => BID,
                    'admin' => 'alias_edit',
                    'query' => [
                        'aid' => $aid,
                    ],
                ]);
            } else {
                $Tpl->add('aid#null');
                $Tpl->add('action#default');
            }

            if ($primary == $aid) {
                $var['aid:checked'] = config('attr_checked');
            }

            $Tpl->add(['status:touch#' . $row['alias_status']]);

            if (
                1
                and isset($row['alias_scope'])
                and $row['alias_scope'] === 'global'
            ) {
                $Tpl->add(['scope:touch#global']);
            } else {
                $Tpl->add(['scope:touch#local']);
            }

            for ($j = 0; $j < $count; $j++) {
                $value = $j + 1;
                $_var = [
                    'value' => $value,
                    'label' => $value,
                ];
                if ($i == $j) {
                    $_var['selected'] = config('attr_selected');
                }

                $Tpl->add('sort:loop', $_var);
            }

            $Tpl->add('alias:loop', $var);
        }

        if (!$this->Post->isNull()) {
            $Tpl->add(null, ['notice_mess' => 'show']);
        }

        return $Tpl->get();
    }
}
