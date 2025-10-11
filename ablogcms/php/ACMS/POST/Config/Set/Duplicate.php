<?php

class ACMS_POST_Config_Set_Duplicate extends ACMS_POST_Config_Set_Insert
{
    public function post()
    {
        $configSetId = intval($this->Post->get('config_set_id'));
        $configSetType = $this->Post->get('config_set_type', null);
        $configSetTypeName = $this->getLogName($configSetType);

        try {
            $this->validate();
            $db = DB::singleton(dsn());

            $name = 'このブログのコンフィグ（レガシー設定）';
            if ($configSetType === 'theme') {
                $name = 'このブログのテーマ（レガシー設定）';
            }
            if ($configSetType === 'editor') {
                $name = 'このブログの編集画面（レガシー設定）';
            }
            $scope = 'local';

            if ($configSetId) {
                $configSet = $this->getConfigSet($configSetId);
                $name = $configSet['config_set_name'];
                $scope = $configSet['config_set_scope'];
            }
            $name = $name . config('entry_title_duplicate_suffix');

            $newSetId = intval($db->query(SQL::nextval('config_set_id', dsn()), 'seq'));
            $sql = SQL::newInsert('config_set');
            $sql->addInsert('config_set_id', $newSetId);
            $sql->addInsert('config_set_sort', $this->getConfigSetSort());
            if ($configSetType) {
                $sql->addInsert('config_set_type', $configSetType);
            }
            $sql->addInsert('config_set_name', $name);
            $sql->addInsert('config_set_description', '');
            $sql->addInsert('config_set_scope', $scope);
            $sql->addInsert('config_set_blog_id', BID);
            $db->query($sql->get(dsn()), 'exec');

            $this->copyConfig($configSetId, $newSetId);

            $this->addMessage($configSetTypeName . 'を複製しました');

            AcmsLogger::info('「' . $name . '」' . $configSetTypeName . 'を複製しました');
        } catch (\Exception $e) {
            $this->addError($e->getMessage());

            AcmsLogger::info('「' . ACMS_RAM::configSetName($configSetId) . '」' . $configSetTypeName . 'の複製に失敗しました');
        }
        return $this->Post;
    }

    /**
     * コンフィグを複製
     *
     * @param int $id
     * @param int $newId
     * @return void
     */
    protected function copyConfig(int $id, int $newId): void
    {
        if (empty($id)) {
            throw new RuntimeException('不正な操作です');
        }
        $db = DB::singleton(dsn());
        $sql = SQL::newSelect('config');
        $sql->addWhereOpr('config_set_id', $id);
        $q = $sql->get(dsn());
        $statement = $db->query($q, 'exec');

        $insert = SQL::newBulkInsert('config');
        while ($config = $db->next($statement)) {
            $config['config_set_id'] = $newId;
            $config['config_blog_id'] = BID;
            $insert->addInsert($config);
        }
        if ($insert->hasData()) {
            $db->query($insert->get(dsn()), 'exec');
        }
    }

    protected function getConfigSet($id)
    {
        $sql = SQL::newSelect('config_set');
        $sql->addWhereOpr('config_set_id', $id);
        $config = DB::query($sql->get(dsn()), 'row');

        if (empty($config)) {
            throw new \RuntimeException('Not found config set.');
        }
        return $config;
    }

    protected function getConfigSetSort()
    {
        $sql = SQL::newSelect('config_set');
        $sql->setSelect('config_set_sort');
        $sql->addWhereOpr('config_set_blog_id', BID);
        $sql->setOrder('config_set_sort', 'DESC');

        return max(intval(DB::query($sql->get(dsn()), 'one')), ACMS_RAM::blogAliasSort(BID)) + 1;
    }

    protected function validate()
    {
        if (!sessionWithAdministration()) {
            throw new \RuntimeException('Permission denied.');
        }
    }
}
