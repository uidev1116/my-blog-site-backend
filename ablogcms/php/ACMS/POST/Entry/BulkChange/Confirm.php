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
            $this->fix();
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
     */
    protected function set()
    {
        $this->targetEids = $this->Post->getArray('checks');
        $this->entryActions = $this->Post->getArray('action_entry');
        $this->fieldActions = $this->Post->getArray('action_field');
        array_shift($this->entryActions); // dummyを除去
        array_shift($this->fieldActions); // dummyを除去
    }

    protected function fix()
    {
        // entry base
        $entry = $this->extract('entry');
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
        $field = $this->extract('field');
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
}
