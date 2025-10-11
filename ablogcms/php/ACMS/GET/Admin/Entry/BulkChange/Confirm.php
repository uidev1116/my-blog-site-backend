<?php

class ACMS_GET_Admin_Entry_BulkChange_Confirm extends ACMS_GET_Admin_Entry_BulkChange
{
    /**
     * @var array
     */
    protected $targetEids = [];

    /**
     * @var array
     */
    protected $entryActions = [];

    /**
     * @var array
     */
    protected $fieldActions = [];

    /**
     * Run
     *
     * @return string
     */
    function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());

        try {
            $this->targetEids = $this->Post->getArray('checks');
            $this->entryActions = $this->Post->getArray('action_entry');
            $this->fieldActions = $this->Post->getArray('action_field');
            array_shift($this->entryActions); // dummyを除去
            array_shift($this->fieldActions); // dummyを除去

            $this->validate();
            $sql = $this->buildQuery();
            $data = $this->buildData($sql);
            $data = $this->buildChangeField($data);

            return $tpl->render($data);
        } catch (\Exception $e) {
            AcmsLogger::debug($e->getMessage(), Common::exceptionArray($e));
        }
        return '';
    }

    /**
     * Validator
     *
     * @return void
     * @throws \RuntimeException
     */
    protected function validate()
    {
        if (!sessionWithAdministration()) {
            throw new \RuntimeException('Permission denied.');
        }
        if (empty($this->targetEids)) {
            throw new \RuntimeException('Target empty.');
        }
        if (empty($this->entryActions) && empty($this->fieldActions)) {
            throw new \RuntimeException('Process empty.');
        }
        if (!in_array($this->Post->get('step'), ['3', '4'], true)) {
            throw new \RuntimeException('Access denied.');
        }
    }

    /**
     * Build query
     *
     * @return SQL_Select
     */
    protected function buildQuery(): SQL_Select
    {
        $SQL = SQL::newSelect('entry');
        $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $SQL->addLeftJoin('category', 'category_id', 'entry_category_id');
        $SQL->addLeftJoin('user', 'user_id', 'entry_user_id');
        $SQL->addWhereIn('entry_id', $this->eids);

        return $SQL;
    }

    /**
     * Build data
     *
     * @param SQL_Select $sql
     * @return array
     */
    protected function buildData(SQL_Select $sql)
    {
        $data = [];
        $DB = DB::singleton(dsn());
        $query = $sql->get(dsn());
        $statement = $DB->query($query, 'exec');

        $entries = [];

        while ($row = $DB->next($statement)) {
            $eid = $row['entry_id'];
            $uid = $row['entry_user_id'];
            $entries[] = [
                'eid' => $eid,
                'url' => acmsLink(['eid' => $eid]),
                'code' => $row['entry_code'],
                'datetime' => $row['entry_datetime'],
                'title' => addPrefixEntryTitle(
                    $row['entry_title'],
                    $row['entry_status'],
                    $row['entry_start_datetime'],
                    $row['entry_end_datetime'],
                    $row['entry_approval']
                ),
                'categoryName' => $row['category_name'],
                'userIcon' => loadUserIcon($uid),
                'userName' => $row['user_name'],
                'status#' . $row['entry_status'] => (object)[],
            ];
        }
        $data['entry'] = $entries;

        return $data;
    }

    /**
     * Build change field.
     *
     * @param array $data
     * @return array
     */
    protected function buildChangeField($data)
    {
        // base entry
        $actions = [];
        $entry = Common::extract('entry');
        foreach ($this->entryActions as $action) {
            $method = Common::camelize($action);
            if (method_exists($this, $method)) {
                $value = $this->{$method}($entry); // @phpstan-ignore-line
            } else {
                $value = $entry->get($action);
            }
            $actions[] = [
                'action' => $action,
                'value' => $value,
            ];
        }
        $data['action'] = $actions;

        // entry field
        $field_actions = [];
        foreach ($this->fieldActions as $action) {
            $field_actions[] = [
                'field#' . $action => (object)[],
                'label' => $this->Post->get('action_field_label_' . $action),
            ];
        }
        $data['action_field'] = $field_actions;

        return $data;
    }

    protected function entryUserId($entry)
    {
        $uid = $entry->get('entry_user_id');
        return ACMS_RAM::userName($uid);
    }

    protected function entryCategoryId($entry)
    {
        $cid = $entry->get('entry_category_id');
        return ACMS_RAM::categoryName($cid);
    }

    protected function entrySubCategoryId($entry)
    {
        $subCategory = $entry->get('entry_sub_category_id');
        $temp = [];
        foreach (explode(',', $subCategory) as $cid) {
            $temp[] = ACMS_RAM::categoryName($cid);
        }
        return implode(', ', $temp);
    }
}
