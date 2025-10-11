<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Services\Facades\Database;
use Field_Validation;
use ACMS_RAM;
use SQL;

class Form extends Base
{
    /**
     * ステップ
     *
     * @var string
     */
    protected $step = 'step';

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            $output = [];
            $this->step = $this->Post->get('error');
            if (!$this->step) {
                $this->step = $this->Get->get('step');
            }
            if ($this->Post->isValidAll()) {
                $this->step = $this->Post->get('step', $this->step);
            } else {
                $output['error'] = [
                    'formID' => $this->Post->get('id'),
                    'fields' => $this->error(),
                ];
            }
            if (!ACMS_POST) {
                $this->step = 'step';
            }
            $output['step'] = $this->step ? $this->step : 'step';
            $this->Post->delete('step');
            $this->addEntryFormId();
            $fields = $this->buildFieldTrait($this->Post, '') ?? [];
            $output['fields'] = $fields;

            return $output;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 動的フォームで、エントリー情報からフォームIDを追加
     *
     * @return void
     */
    protected function addEntryFormId(): void
    {
        if (defined('FORM_ENTRY_ID') && !!FORM_ENTRY_ID) {
            $entry = ACMS_RAM::entry(FORM_ENTRY_ID);
            $fmid = $entry['entry_form_id'];
            $sql = SQL::newSelect('form');
            $sql->addSelect('form_code');
            $sql->addWhereOpr('form_id', $fmid);
            $Where = SQL::newWhere();
            $Where->addWhereOpr('form_blog_id', BID, '=', 'OR');
            $Where->addWhereOpr('form_scope', 'global', '=', 'OR');
            $sql->addWhere($Where);
            $q = $sql->get(dsn());
            $fcode = Database::query($q, 'one');
            $this->Post->add('form_id', $fcode);
        }
    }

    /**
     * エラー処理
     */
    protected function error()
    {
        $errors = [];
        if (isset($this->Post->_aryChild['field'])) {
            $field = $this->Post->_aryChild['field'];
            if (!($field instanceof Field_Validation)) {
                return;
            }
            foreach ($field->_aryV as $key => $val) {
                foreach ($val as $valid) {
                    if (isset($valid[0]) && $valid[0] === false) {
                        $errors[] = $key;
                    }
                }
            }
        }
        return $errors;
    }
}
