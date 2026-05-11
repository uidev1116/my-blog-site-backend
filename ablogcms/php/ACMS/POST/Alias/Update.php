<?php

class ACMS_POST_Alias_Update extends ACMS_POST_Alias
{
    public function post()
    {
        $aliasId = (int)$this->Get->get('aid');
        $Alias = $this->extract('alias');
        $Alias->setMethod('name', 'required');
        $Alias->setMethod('name', 'maxlength', '255');
        $Alias->setMethod('status', 'required');
        $Alias->setMethod('status', 'in', ['open', 'close']);
        $Alias->setMethod('indexing', 'in', ['on', 'off']);
        $Alias->setMethod('alias', 'operable', $this->isOperable($aliasId));

        $Alias->setMethod('domain', 'required');
        $Alias->setMethod('domain', 'maxlength', '128');
        $Alias->setMethod('domain', 'domain', Blog::isDomain($Alias->get('domain'), $aliasId, true, true));
        $Alias->setMethod('scope', 'deny', $this->checkScope($Alias->get('scope')));
        $Alias->setMethod('code', 'maxlength', '128');
        $Alias->setMethod('code', 'exists', Blog::isCodeExists($Alias->get('domain'), $Alias->get('code'), BID, $aliasId));
        $Alias->setMethod('code', 'reserved', !isReserved($Alias->get('code')));
        if ($Alias->get('code') !== '') {
            $Alias->setMethod('code', 'string', isValidCode($Alias->get('code'), true));
        }
        $Alias->setMethod('description', 'maxlength', '255');

        $Alias->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            $SQL = SQL::newUpdate('alias');
            $SQL->addUpdate('alias_status', $Alias->get('status'));
            $SQL->addUpdate('alias_domain', $Alias->get('domain'));
            $SQL->addUpdate('alias_code', strval($Alias->get('code')));
            $SQL->addUpdate('alias_name', $Alias->get('name'));
            $SQL->addUpdate('alias_scope', $Alias->get('scope', 'local'));
            $SQL->addUpdate('alias_indexing', $Alias->get('indexing', 'on'));
            $SQL->addUpdate('alias_blog_id', BID);
            $SQL->addWhereOpr('alias_blog_id', BID);
            $SQL->addWhereOpr('alias_id', $aliasId);
            $DB->query($SQL->get(dsn()), 'exec');

            ACMS_RAM::alias($aliasId, null);

            $this->Post->set('edit', 'update');

            AcmsLogger::info('エイリアス「' . $Alias->get('name') . '」を更新しました', [
                'aid' => $aliasId,
                'status' => $Alias->get('status'),
                'domain' => $Alias->get('domain'),
                'code' => $Alias->get('code'),
                'name' => $Alias->get('name'),
                'scope' => $Alias->get('scope'),
                'indexing' => $Alias->get('indexing'),
                'bid' => BID,
            ]);
        } else {
            AcmsLogger::info('エイリアスの更新に失敗しました', [
                'aid' => $aliasId,
                'validator' => $Alias->_aryV,
            ]);
        }

        return $this->Post;
    }

    /**
     * エイリアスが操作可能かどうかを判定する
     * @param int $aliasId
     * @return bool
     */
    protected function isOperable(int $aliasId): bool
    {
        if (!IS_LICENSED) {
            return false;
        }

        if (!sessionWithAdministration()) {
            return false;
        }

        if ($aliasId < 1) {
            return false;
        }

        return true;
    }
}
