<?php

class ACMS_POST_Config_Set_Insert extends ACMS_POST
{
    function post()
    {
        $type = $this->Post->get('type', null);

        $configSet = $this->extract('config_set');
        $configSet->setMethod('name', 'required');
        $configSet->setMethod('name', 'maxlength', '255');
        $configSet->setMethod('type', 'in', ['theme', 'editor']);
        $configSet->setMethod('confgiset', 'operable', IS_LICENSED and sessionWithAdministration());
        $configSet->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            $setId = $DB->query(SQL::nextval('config_set_id', dsn()), 'seq');

            $SQL = SQL::newSelect('config_set');
            $SQL->setSelect('config_set_sort');
            $SQL->addWhereOpr('config_set_blog_id', BID);
            $SQL->setOrder('config_set_sort', 'DESC');
            $sort = max(intval($DB->query($SQL->get(dsn()), 'one')), ACMS_RAM::blogAliasSort(BID)) + 1;

            $SQL = SQL::newInsert('config_set');
            $SQL->addInsert('config_set_id', $setId);
            $SQL->addInsert('config_set_sort', $sort);
            if ($type) {
                $SQL->addInsert('config_set_type', $type);
            }
            $SQL->addInsert('config_set_name', $configSet->get('name'));
            $SQL->addInsert('config_set_description', $configSet->get('description'));
            $SQL->addInsert('config_set_scope', $configSet->get('scope', 'local'));
            $SQL->addInsert('config_set_blog_id', BID);

            $DB->query($SQL->get(dsn()), 'exec');

            $this->Post->set('edit', 'insert');

            $label = $this->getLogName($type);
            AcmsLogger::info('「' . $configSet->get('name') . '」' . $label . 'を作成しました');
        } else {
            $label = $this->getLogName($type);
            AcmsLogger::info($label . 'の作成に失敗しました', [
                'configSet' => $configSet->_aryV,
            ]);
        }

        return $this->Post;
    }

    /**
     * ログに使うラベルを取得
     *
     * @param null|string $type
     * @return string
     */
    protected function getLogName(?string $type): string
    {
        if ($type === 'theme') {
            return 'テーマセット';
        }
        if ($type === 'editor') {
            return '編集画面セット';
        }
        return 'コンフィグセット';
    }
}
