<?php

class ACMS_GET_Admin_Schedule_Edit extends ACMS_GET_Admin_Edit
{
    public function auth()
    {
        $scid = (int) $this->Get->get('scid', 0);
        return sessionWithScheduleAdministration(BID, $scid);
    }

    function edit(&$Tpl)
    {
        $Schedule =& $this->Post->getChild('schedule');
        $_Schedule = $this->loadSchedule($this->Get->get('scid'));
        $Schedule->overload($_Schedule);

        for ($x = 1; $x < 3; $x++) {
            $year   = date('Y');
            for ($i = 0; $i < 5; $i++) {
                $Tpl->add(['year:loop#' . $x], ['year' => $year + $i]);
            }

            $month  = date('m');
            for ($i = 1; $i < 13; $i++) {
                $vars = ['month' => sprintf("%02d", $i)];
                if (sprintf("%02d", $i) == $month) {
                    $vars += ['selected' => config('attr_selected')];
                }
                $Tpl->add(['month:loop#' . $x], $vars);
            }
        }

        return true;
    }

    function loadSchedule($scid)
    {
        $Schedule   = new Field_Validation();
        if (!empty($scid)) {
            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newSelect('schedule');
            $SQL->addWhereOpr('schedule_id', intval($scid));
            if ($row = $DB->query($SQL->get(dsn()), 'row')) {
                foreach ($row as $key => $val) {
                    $Schedule->set(substr($key, strlen('schedule_')), $val);
                }
            }
        }
        return $Schedule;
    }
}
