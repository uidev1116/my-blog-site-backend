<?php

class ACMS_POST_Entry_BulkChange_Confirm extends ACMS_POST_Entry_BulkChange
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
     * @inheritDoc
     */
    public function post()
    {
        try {
            $this->set();
            $this->validate();
            $entry = $this->extract('entry');
            $field = $this->extract('field');
            $this->fix(entry: $entry, field: $field);
            $this->validateEntry(entry: $entry, field: $field);
            if (!$this->Post->isValidAll()) {
                $this->Post->set('step', '2');
                $this->Post->set('error', 'validationFailed');
                return $this->Post;
            }
            $this->Post->set('step', '3');
        } catch (ACMS_POST_Entry_BulkChange_Exceptions_PermissionDenied $e) {
            die403('Permission denied.');
        } catch (ACMS_POST_Entry_BulkChange_Exceptions_TargetEmpty $e) {
            $this->Post->set('step', '1');
            $this->Post->set('error', 'targetEmpty');
        } catch (ACMS_POST_Entry_BulkChange_Exceptions_OperationEmpty $e) {
            $this->Post->set('step', '2');
            $this->Post->set('error', 'operationEmpty');
        }
        return $this->Post;
    }

    /**
     * Validator
     *
     * @throws ACMS_POST_Entry_BulkChange_Exceptions_OperationEmpty
     * @throws ACMS_POST_Entry_BulkChange_Exceptions_PermissionDenied
     * @throws ACMS_POST_Entry_BulkChange_Exceptions_TargetEmpty
     */
    protected function validate()
    {
        parent::validate();
        if (empty($this->targetEids)) {
            throw new ACMS_POST_Entry_BulkChange_Exceptions_TargetEmpty();
        }
        if (empty($this->entryActions) && empty($this->fieldActions)) {
            throw new ACMS_POST_Entry_BulkChange_Exceptions_OperationEmpty();
        }
    }

    /**
     * Set data
     * @return void
     */
    protected function set(): void
    {
        $this->targetEids = $this->Post->getArray('checks');
        $this->entryActions = $this->Post->getArray('action_entry');
        $this->fieldActions = $this->Post->getArray('action_field');
        array_shift($this->entryActions); // dummyを除去
        array_shift($this->fieldActions); // dummyを除去
    }

    /**
     * Fix entry and field
     * @param \Field_Validation $entry
     * @param \Field_Validation $field
     * @return void
     */
    protected function fix(\Field_Validation $entry, \Field_Validation $field): void
    {
        foreach ($this->entryActions as $action) {
            switch ($action) {
                case 'entry_datetime':
                    $date = $entry->get('entry_date', date('Y-m-d'));
                    $time = $entry->get('entry_time', date('H:i:s'));
                    $entry->set('entry_datetime', $date . ' ' . $time);
                    break;
                case 'entry_start_datetime':
                    $date = $entry->get('entry_start_date', '1000-01-01');
                    $time = $entry->get('entry_start_time', '00:00:00');
                    $entry->set('entry_start_datetime', $date . ' ' . $time);
                    break;
                case 'entry_end_datetime':
                    $date = $entry->get('entry_end_date', '9999-12-31');
                    $time = $entry->get('entry_end_time', '23:59:59');
                    $entry->set('entry_end_datetime', $date . ' ' . $time);
                    break;
            }
        }

        // entry field
        $list = $this->fieldActions;
        foreach ($this->fieldActions as $key) {
            if (preg_match('/^@(.*)$/', $key)) {
                $list = array_merge($list, $field->getArray($key));
            }
        }
        $regex = '/^(' . implode('|', $list) . ')(@[^@]+)?$/';
        foreach ($field->listFields() as $fd) {
            if (!preg_match($regex, $fd)) {
                $field->delete($fd);
            }
        }
    }

    /**
     * Validate entry and field
     * @param \Field_Validation $entry
     * @param \Field_Validation $field
     * @return void
     */
    protected function validateEntry(\Field_Validation $entry, \Field_Validation $field): void
    {
        if (in_array('entry_status', $this->entryActions, true)) {
            $entry->setMethod('entry_status', 'required');
            $entry->setMethod('entry_status', 'in', ['open', 'close', 'draft', 'trash']);
        }
        if (in_array('entry_title', $this->entryActions, true)) {
            $entry->setMethod('entry_title', 'required');
            $entry->setMethod('entry_title', 'maxlength', '255');
        }
        if (in_array('entry_link', $this->entryActions, true)) {
            $entry->setMethod('entry_link', 'maxlength', '255');
        }
        if (in_array('entry_indexing', $this->entryActions, true)) {
            $entry->setMethod('entry_indexing', 'required');
            $entry->setMethod('entry_indexing', 'in', ['on', 'off']);
        }

        if (in_array('entry_tag', $this->entryActions, true)) {
            $entry = Entry::validTag($entry, 'entry_tag');
        }
        if (in_array('entry_sub_category_id', $this->entryActions, true)) {
            $entry = Entry::validSubCategory($entry, 'entry_sub_category_id');
        }
        $entry->validate(new ACMS_Validator());
        $field->validate(new ACMS_Validator());
    }
}
