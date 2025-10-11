<?php

use Acms\Services\Facades\Template as Tpl;

class ACMS_GET_Admin_Form2_Add extends ACMS_GET_Admin
{
    use \Acms\Traits\Unit\UnitModelTrait;

    public function get()
    {
        if ('entry-add' !== substr(ADMIN, 0, 9)) {
            return '';
        }
        if (!sessionWithContribution()) {
            die403();
        }

        $addType = substr(ADMIN, 10);

        $aryTypeLabel = [];
        foreach (configArray('column_form_add_type') as $i => $type) {
            $aryTypeLabel[$type] = config('column_form_add_type_label', '', $i);
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());

        $data['type'] = $addType;
        $data['id'] = $this->generateNewIdTrait();

        Tpl::buildAdminFormColumn($data, $tpl);

        //--------
        // option
        for ($i = 0; $i < 3; $i++) {
            $tpl->add(['option:loop'], [
                'id'    => $data['id'],
                'unique' => 'new-' . ($i + 1),
            ]);
        }

        $tpl->add('column:loop', [
            'unit_type'    => $addType,
            'unit_id'    => $data['id'],
            'unit_name'    => ite($aryTypeLabel, $addType),
        ]);

        return $tpl->get();
    }
}
