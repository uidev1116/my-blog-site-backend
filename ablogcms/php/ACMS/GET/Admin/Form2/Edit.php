<?php

use Acms\Services\Facades\Template as Tpl;

/**
 * @phpstan-import-type FormColumn from ACMS_POST_Form2_Update
 */
class ACMS_GET_Admin_Form2_Edit extends ACMS_GET_Admin
{
    use \Acms\Traits\Unit\UnitModelTrait;

    public function get()
    {
        if (!sessionWithContribution()) {
            die403();
        }
        if ('form2-edit' !== ADMIN) {
            return '';
        }
        if (!EID) {
            return false;
        }
        if (config('form_edit_action_direct') !== 'on') {
            return false;
        }

        $DB         = DB::singleton(dsn());
        $Tpl        = new Template($this->tpl, new ACMS_Corrector());

        if (!$this->Post->isNull()) {
            $step       = $this->Post->get('step');
            $action     = $this->Post->get('action');
            $formId     = $this->Post->get('form_id');
            $formStatus = $this->Post->get('form_status');
            $Form       =& $this->Post->getChild('form');
            /** @var FormColumn[] $Column */
            $Column = Entry::getTempUnitData() ?? [];
        } else {
            $Form       = new Field();
            $Column     = [];
            $step       = 'reapply';
            $action     = 'update';

            $row        = ACMS_RAM::entry(EID);
            $formId     = $row['entry_form_id'];
            $formStatus = $row['entry_form_status'];

            //--------
            // column
            $Column = loadFormUnit(EID);
        }

        $vars   = [];
        $rootBlock  = 'step#' . $step;

        //----------
        // form set
        $SQL = SQL::newSelect('form');
        $Where  = SQL::newWhere();
        $Where->addWhereOpr('form_blog_id', BID, '=', 'OR');
        $Where->addWhereOpr('form_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);
        $SQL->setOrder('form_current_serial');

        if ($all = $DB->query($SQL->get(dsn()), 'all')) {
            foreach ($all as $val) {
                if ($val['form_id'] === $formId) {
                    $val['selected'] = config('attr_selected');
                }
                $Tpl->add(['form:loop', $rootBlock], $val);
            }
        }

        //--------
        // column
        foreach (configArray('column_form_add_type') as $i => $type) {
            $aryTypeLabel[$type]    = config('column_form_add_type_label', '', $i);
        }

        if (count($Column) > 0) {
            foreach ($Column as $i => $data) {
                $id     = $data['id'];
                $type   = $data['type'];
                $sort   = $i + 1;

                //--------------
                // build column
                if (!Tpl::buildAdminFormColumn($data, $Tpl, $rootBlock)) {
                    continue;
                }

                $Tpl->add(['column:loop', $rootBlock], [
                    'unit_id' => $id,
                    'unit_type' => $type,
                    'unit_name' => ite($aryTypeLabel, $type),
                    'unit_sort' => $sort,
                ]);
            }
        } else {
            //-----------
            // [CMS-608]
            $Tpl->add(['adminEntryColumn', $rootBlock]);
        }

        //--------------
        // Form
        $vars   += $this->buildField($Form, $Tpl, $rootBlock);

        //--------
        // action
        if (IS_LICENSED) {
            $Tpl->add(['action#confirm', $rootBlock]);
            $Tpl->add(['action#' . $action, $rootBlock]);
        }
        if ('update' == $action) {
            $Tpl->add(['action#delete', $rootBlock]);
        }

        //--------
        // status
        if (!empty($formStatus)) {
            $vars['form_status:selected#' . $formStatus] = config('attr_selected');
        }

        $Tpl->add($rootBlock, $vars);

        return $Tpl->get();
    }
}
