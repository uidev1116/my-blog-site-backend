<?php

class ACMS_GET_Form2_Unit extends ACMS_GET
{
    public function get()
    {
        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        if ('form-edit' === ADMIN) {
            return '';
        }

        $eid = (!!$this->eid) ? $this->eid : EID;
        if (empty($eid)) {
            $eid  = $this->Post->get('eid');
        }
        if (!defined('FORM_ENTRY_ID') && !!$eid) {
            define('FORM_ENTRY_ID', intval($eid));
        }

        $Unit   = loadFormUnit($eid);
        $this->buildFormUnit($Unit, $Tpl, $eid);

        return $Tpl->get();
    }

    public function buildFormUnit(&$Unit, &$Tpl, $eid)
    {
        foreach ($Unit as $data) {
            $type   = $data['type'];
            $utid   = $data['id'];

            $vars   = [
                'label'     => $data['label'],
                'caption'   => $data['caption'],
            ];
            $vars['utid']       = $utid;
            $vars['unit_eid']   = $eid;

            //----------------
            // text, textarea
            if (in_array($type, ['text', 'textarea'], true)) {
                if (empty($data['label'])) {
                    continue;
                }
            //-------------------------
            // radio, select, checkbox
            } elseif (in_array($type, ['radio', 'select', 'checkbox'], true)) {
                if (
                    1
                    && isset($data['values'])
                    && $values = acmsDangerUnserialize($data['values'])
                ) {
                    if (is_array($values)) {
                        foreach ($values as $i => $val) {
                            if (!empty($val)) {
                                $Tpl->add([$type . '#val:loop', $type, 'column:loop'], [
                                    'i'     => ++$i,
                                    'value' => $val,
                                    'utid'  => $utid,
                                ]);
                            }
                        }
                    }
                }
            } else {
                continue;
            }

            //------------
            // validator
            $validatorSet   = acmsDangerUnserialize($data['validatorSet']);

            if (is_array($validatorSet) && isset($validatorSet['validator'])) {
                $valid          = $validatorSet['validator'];
                $validValue     = $validatorSet['validator-value'];
                $validMess      = $validatorSet['validator-message'];
                $validator      = array_combine($valid, $validValue);
                $validatorMess  = array_combine($valid, $validMess);
            } else {
                $valid          = [];
                $validValue     = [];
                $validMess      = [];
                $validator      = [];
                $validatorMess  = [];
            }

            $required   = false;
            foreach ($validator as $key => $val) {
                if (empty($key)) {
                    continue;
                }
                if ($key === 'converter') {
                    $Tpl->add(['converter:loop', $type, 'column:loop'], [
                        'vutid' => $utid,
                        'val'   => $val,
                    ]);
                } else {
                    if ($key === 'required') {
                        $required = true;
                    }
                    $Tpl->add(['validator:loop', $type, 'column:loop'], [
                        'vutid' => $utid,
                        'valid' => $key,
                        'val'   => $val,
                    ]);
                }
            }
            if ($required) {
                $Tpl->add(['required', $type, 'column:loop']);
            }
            foreach ($validatorMess as $key => $val) {
                if (empty($key)) {
                    continue;
                }
                $Tpl->add(['validatorMessage:loop', $type, 'column:loop'], [
                    'vutid'     => $utid,
                    'valid'     => $key,
                    'message'   => $val,
                ]);
            }

            $Tpl->add([$type, 'column:loop'], $vars);
            $Tpl->add('column:loop');
        }
        return true;
    }
}
