<?php

class ACMS_GET_Admin_Schedule_Labels extends ACMS_GET_Admin
{
    function get()
    {
        $scid = (int) $this->Get->get('scid', 0);
        if (!sessionWithScheduleAdministration(BID, $scid)) {
            die403();
        }
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $config = Config::loadDefaultField();
        $config->overload(Config::loadBlogConfig(BID));

        $labels     = $config->getArray('schedule_label@' . $scid);
        $takeover   = $this->Post->getChild('schedule');
        $isNull     = $takeover->listFields();
        $add  = 3;
        $sort = 0;
        $max  = count($labels) + 1 + $add;

        if (is_array($labels) && !empty($labels)) {
            $separator = config('schedule_label_separator', '__sep__');
            $separator = $separator ? $separator : '__sep__';
            foreach ($labels as $label) {
                $sort++;
                $_label = explode($separator, $label);

                for ($i = 1; $i < $max; $i++) {
                    $vars   = ['i' => $i];
                    if ($i == $sort) {
                        $vars['selected'] = config('attr_selected');
                    }
                    $Tpl->add(['sort:loop', 'label:loop'], $vars);
                }

                $Tpl->add('label:loop', [
                    'sort'  => $sort,
                    'name'  => $_label[0],
                    'key'   => isset($_label[1]) ? $_label[1] : '',
                    'classStr' => isset($_label[2]) ? $_label[2] : '',
                ]);
            }

            for ($n = 0; $n < $add; $n++) {
                $sort++;
                for ($i = 1; $i < $max; $i++) {
                    $vars   = ['i' => $i];
                    if ($i == $sort) {
                        $vars['selected'] = config('attr_selected');
                    }
                    $Tpl->add(['sort:loop', 'label:loop'], $vars);
                }
                $Tpl->add('label:loop');
            }
        } else {
            for ($n = 0; $n < $add; $n++) {
                $sort++;
                for ($i = 1; $i < $max; $i++) {
                    $vars   = ['i' => $i];
                    if ($i == $sort) {
                        $vars['selected'] = config('attr_selected');
                    }
                    $Tpl->add(['sort:loop', 'label:loop'], $vars);
                }
                $Tpl->add('label:loop');
            }
        }

        return $Tpl->get();
    }
}
