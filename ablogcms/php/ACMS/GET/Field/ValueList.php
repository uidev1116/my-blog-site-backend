<?php

use Acms\Modules\Get\Helpers\FieldValueHelper;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Field_ValueList extends ACMS_GET
{
    public $_scope = [
        'bid' => 'global',
        'field' => 'global',
    ];

    public $_axis = [
        'bid' => 'self',
    ];

    function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);

        $limit = (int) config('field_value-list_limit', 100);
        $order = strtoupper(config('field_value-list_order', 'ASC')) === 'ASC' ? 'ASC' : 'DESC';
        $fieldValueHelper = new FieldValueHelper($this->getBaseParams([]));
        $items = $fieldValueHelper->getFieldValueData($limit, $order);

        $lastIndex = count($items) - 1;
        foreach ($items as $index => $value) {
            // 最後の要素でない場合のみ 'glue' を追加
            if ($index !== $lastIndex) {
                $tpl->add(['glue', 'value:loop']);
            }
            $tpl->add('value:loop', ['value' => $value]);
        }
        return $tpl->get();
    }
}
