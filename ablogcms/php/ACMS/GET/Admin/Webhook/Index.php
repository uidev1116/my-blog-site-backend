<?php

class ACMS_GET_Admin_Webhook_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }

        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        if (!HOOK_ENABLE) {
            $Tpl->add('disabled');
            return $Tpl->get();
        }

        $sql = SQL::newSelect('webhook');
        $sql->addLeftJoin('blog', 'blog_id', 'webhook_blog_id');
        ACMS_Filter::blogTree($sql, BID, 'ancestor-or-self');

        $where  = SQL::newWhere();
        $where->addWhereOpr('webhook_blog_id', BID, '=', 'OR');
        $where->addWhereOpr('webhook_scope', 'global', '=', 'OR');
        $sql->addWhere($where);
        $sql->addOrder('webhook_id', 'DESC');
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');

        if (!$statement || !($row = DB::next($statement))) {
            $Tpl->add('notFound');
            return $Tpl->get();
        }

        do {
            $id = intval($row['webhook_id']);
            $bid = intval($row['webhook_blog_id']);

            if (BID !== $bid) {
                $row['webhook_scope'] = 'parental';
            }
            $Tpl->add('scope#' . $row['webhook_scope']);
            $Tpl->add('status#' . $row['webhook_status']);
            $vars = [
                'id' => $id,
                'bid' => $bid,
                'name' => $row['webhook_name'],
                'scope' => $row['webhook_scope'],
                'type' => $row['webhook_type'],
                'url' => $row['webhook_url'],
            ];

            if (BID === $bid) {
                $Tpl->add('mine', $this->getLinkVars(BID, $row));
            } elseif (sessionWithAdministration($bid)) {
                $Tpl->add('notMinePermit', $this->getLinkVars($bid, $row));
            } else {
                $Tpl->add('notMine');
            }
            $Tpl->add('webhook:loop', $vars);
        } while ($row = DB::next($statement));

        return $Tpl->get();
    }

    protected function getLinkVars($bid, $webhook)
    {
        $id = intval($webhook['webhook_id']);
        return [
            'itemUrl' => acmsLink([
                'bid' => $bid,
                'admin' => 'webhook_edit',
                'query' => [
                    'id' => $id,
                ],
            ]),
            'logUrl' => acmsLink([
                'bid' => $bid,
                'admin' => 'webhook_log',
                'query' => [
                    'id' => $id,
                ],
            ]),
        ];
    }
}
