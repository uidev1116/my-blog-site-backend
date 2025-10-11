<?php

class ACMS_GET_Admin_Rule_Edit extends ACMS_GET_Admin_Edit
{
    function auth()
    {
        if (roleAvailableUser()) {
            if (!roleAuthorization('rule_edit', BID)) {
                die403();
            }
        } else {
            if (!sessionWithAdministration()) {
                die403();
            }
        }
        return true;
    }

    function edit(&$Tpl)
    {
        $Rule   =& $this->Post->getChild('rule');
        if ($Rule->isNull() and (!!$rid = $this->Get->get('rid'))) {
            $Rule->overload(loadRule($rid));
        }

        if (!$Rule->isNull('term_start')) {
            list($date, $time)  = explode(' ', $Rule->get('term_start'));
            $Rule->set('term_start_date', $date);
            $Rule->set('term_start_time', $time);
        }

        if (!$Rule->isNull('term_end')) {
            list($date, $time)  = explode(' ', $Rule->get('term_end'));
            $Rule->set('term_end_date', $date);
            $Rule->set('term_end_time', $time);
        }

        $ua     = $Rule->get('ua', config('ua_value', '', intval(config('ua_default'))));

        $i  = null;
        foreach (configArray('ua_value') as $key => $value) {
            if ($ua == $value) {
                $i = $key;
            }
        }
        if ($Rule->get('ua')) {
            $Rule->set('uaLabel', (!is_null($i) and ($label = config('ua_label', '', $i))) ? $label : $ua);
        }

        foreach (configArray('ua_value', true) as $id => $value) {
            $vars  = [
                'value' => $value,
                'label' => config('ua_label', '', $id),
            ];
            if ($ua == $value) {
                $vars['selected'] = config('attr_selected');
            }
            $Tpl->add(['uaoption:loop'], $vars);
        }

        $this->buildArgLabels($Rule);

        return true;
    }
}
