<?php

class ACMS_GET_Admin_Schedule_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if ('schedule_index' <> ADMIN) {
            return '';
        }
        if (!sessionWithScheduleAdministration()) {
            die403();
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('schedule');

        $SQL->addWhereOpr('schedule_blog_id', BID);
        $SQL->setGroup('schedule_id');
        $all    = $DB->query($SQL->get(dsn()), 'all');

        if (empty($all)) {
            $Tpl->add('notFound');
            $Tpl->add(null, ['notice_mess' => 'show']);
        } else {
            foreach ($all as $row) {
                foreach ($row as $key => $val) {
                    $vars[str_replace('schedule_', '', $key)] = $val;
                }
                $vars   += [
                    'itemUrl'   => acmsLink([
                        'bid'   => BID,
                        'admin' => 'schedule_edit',
                        'query' => [
                            'scid'  => $vars['id'],
                        ],
                    ]),
                    'labelUrl'   => acmsLink([
                        'bid'   => BID,
                        'admin' => 'schedule_edit-label',
                        'query' => [
                            'scid'  => $vars['id'],
                        ],
                    ]),
                    'dataUrl'   => acmsLink([
                        'bid'   => BID,
                        'admin' => 'schedule_edit-data',
                        'query' => [
                            'scid'  => $vars['id'],
                        ],
                    ]),
                ];
                $Tpl->add('schedule:loop', $vars);
                unset($vars);
            }
        }
        return $Tpl->get();
    }
}
