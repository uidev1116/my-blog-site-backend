<?php

use Acms\Services\Facades\Config;

class ACMS_GET_Admin_Config_Set_Index extends ACMS_GET_Admin
{
    /**
     * コンフィグセットのタイプ
     * @var string
     */
    protected $type = null;

    /**
     * 編集ページ
     *
     * @var string
     */
    protected $editPage = 'config_set_base_edit';

    /**
     * コンフィグ一覧
     *
     * @var string
     */
    protected $configPage = 'config_index';

    function get()
    {
        if (!$this->validate()) {
            if (ADMIN === 'config_set_index') {
                die403();
            }
            return '';
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());
        $DB = DB::singleton(dsn());

        $SQL = $this->buildQuery();
        if (!$all = $DB->query($SQL->get(dsn()), 'all')) {
            $Tpl->add('notFound');
            return $Tpl->get();
        }
        $this->build($Tpl, $all);
        return $Tpl->get();
    }

    protected function validate()
    {
        if (!Config::canViewIndex(BID)) {
            return false;
        }
        return true;
    }

    protected function buildQuery()
    {
        $SQL = SQL::newSelect('config_set');
        $SQL->addLeftJoin('blog', 'blog_id', 'config_set_blog_id');
        ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');
        $SQL->addWhereOpr('config_set_type', $this->type);
        $Where = SQL::newWhere();
        $Where->addWhereOpr('config_set_blog_id', BID, '=', 'OR');
        $Where->addWhereOpr('config_set_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);
        $SQL->setOrder('config_set_sort', 'ASC');

        return $SQL;
    }

    protected function build(&$Tpl, $all)
    {
        $cnt = count($all);
        $sort = 1;
        while ($row = array_shift($all)) {
            $setid = intval($row['config_set_id']);
            if (BID !== intval($row['config_set_blog_id'])) {
                $row['config_set_scope'] = 'parental';
                $disabled = config('attr_disabled');
            } else {
                $disabled = '';
            }
            $Tpl->add('scope:touch#' . $row['config_set_scope']);

            for ($i = 1; $i <= $cnt; $i++) {
                $vars = [
                    'value' => $i,
                    'label' => $i,
                ];
                if ($sort == $i) {
                    $vars['selected'] = config('attr_selected');
                }
                $Tpl->add('sort:loop', $vars);
            }

            $vars = [
                'setid' => $setid,
                'sort' => $sort,
                'scope' => $row['config_set_scope'],
                'name' => $row['config_set_name'],
                'description' => $row['config_set_description'],
                'disabled' => $disabled,
            ];

            $setbid = intval($row['config_set_blog_id']);
            if (BID === $setbid) {
                $Tpl->add('mine', $this->getLinkVars(BID, $setid));
            } else {
                if (
                    0
                    or (roleAvailableUser() && roleAuthorization('rule_edit', $setbid))
                    or sessionWithAdministration($setbid)
                ) {
                    $Tpl->add('notMinePermit', $this->getLinkVars($setbid, $setid));
                } else {
                    $Tpl->add('notMine');
                }
            }
            $Tpl->add(['config_set:loop'], $vars);

            $sort++;
        }
    }

    protected function getLinkVars($bid, $setid)
    {
        return [
            'configSetId' => $setid,
            'itemUrl' => acmsLink([
                'bid' => $bid,
                'admin' => $this->editPage,
                'query' => new Field([
                    'setid' => $setid,
                ]),
            ]),
            'configUrl' => acmsLink([
                'bid' => $bid,
                'admin' => $this->configPage,
                'query' => new Field([
                    'setid' => $setid,
                    'rid'   => $this->Get->get('rid'),
                ]),
            ])
        ];
    }
}
