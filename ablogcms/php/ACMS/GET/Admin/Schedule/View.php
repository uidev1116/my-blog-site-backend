<?php

class ACMS_GET_Admin_Schedule_View extends ACMS_GET_Schedule
{
    protected $plan;

    public function get()
    {
        $scid = (int) $this->Get->get('scid', 0);
        if (!sessionWithScheduleAdministration(BID, $scid)) {
            die403();
        }
        $config = Config::loadDefaultField();
        $config->overload(Config::loadBlogConfig(BID));
        $this->unit = config('schedule_unit');

        // POSTから年月日を取得
        if (!empty($_POST['dateArgs'])) {
            $dateArgs = explode('-', $_POST['dateArgs']);
        } elseif (!empty($_POST['yearArg']) && !empty($_POST['monthArg'])) {
            $dateArgs = [];
            $dateArgs[0] = $_POST['yearArg'];
            $dateArgs[1] = $_POST['monthArg'];
        } elseif (!empty($_POST['year']) && !empty($_POST['month'])) {
            $dateArgs = [];
            $dateArgs[0] = $_POST['year'];
            $dateArgs[1] = $_POST['month'];
        }

        $this->year     = !empty($dateArgs[0])     ? intval($dateArgs[0])     : date('Y');
        $this->month    = !empty($dateArgs[1])     ? intval($dateArgs[1])     : date('n');
        $this->day      = !empty($dateArgs[2])     ? intval($dateArgs[2])     : 1;

        // リストモード固定＆コンフィグの代入
        $this->listmode = true;
        $this->sep      = $config->get('schedule_label_separator');
        $this->plan     = $config->get('schedule_multi_plan');
        $this->forwardM = 0; //$config->get('schedule_forwardM');
        $this->backM    = 0; //$config->get('schedule_backM');
        $this->forwardD = 0; //$config->get('schedule_forwardD');
        $this->backD    = 0; //$config->get('schedule_backD');
        $this->formatY  = $config->get('schedule_formatY');
        $this->formatM  = $config->get('schedule_formatM');
        $this->formatD  = $config->get('schedule_formatD');
        $this->formatD  = $config->get('schedule_formatD');
        $this->formatW  = $config->get('schedule_formatW');
        $this->key      = $this->Get->get('scid');
        $this->labels   = $config->getArray('schedule_label@' . $this->key);

        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        $this->monthView($Tpl);

        return $Tpl->get();
    }
}
