<?php

class ACMS_POST_Schedule_EditData extends ACMS_POST_Schedule
{
    public function post()
    {
        $scid = (int) $this->Get->get('scid', 0);
        $Conf = $this->extract('schedule');
        $Conf->setMethod('year', 'regex', '@^[0-9]{4}$@');
        $Conf->setMethod('month', 'regex', '@^[0-9]{2}$@');
        $Conf->setMethod('schedule', 'operative', sessionWithScheduleAdministration(BID, $scid));
        $Conf->validate(new ACMS_Validator());

        $year   = $Conf->get('year');
        $month  = $Conf->get('month');
        $limit  = date('t', mktime(0, 0, 0, (int) $month, 1, (int) $year)) + 1;

        $sche   = [];
        $sfds   = [];

        $build  = $this->buildSchedule($sche, $sfds, $limit);

        // validation result & serialize
        if (!$Conf->isValid() || $build == false) {
            $this->Post->set('step', 'reapply');
            $this->Post->set('reapply', [
                'data' => $sche,
                'field' => $sfds,
            ]);

            AcmsLogger::info('スケジュールのデータ登録に失敗しました');
            return $this->Post;
        }

        $DB     = DB::singleton(dsn());

        // delete
        $SQL    = SQL::newDelete('schedule');
        $SQL->addWhereOpr('schedule_id', $scid);
        $SQL->addWhereOpr('schedule_year', $Conf->get('year'));
        $SQL->addWhereOpr('schedule_month', $Conf->get('month'));
        $SQL->addWhereOpr('schedule_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        // load define
        $define = $this->loadDefine($scid);

        // insert
        $SQL    = SQL::newInsert('schedule');
        $SQL->addInsert('schedule_id', $scid);
        $SQL->addInsert('schedule_name', $define['name']);
        $SQL->addInsert('schedule_desc', $define['desc']);
        $SQL->addInsert('schedule_year', $Conf->get('year'));
        $SQL->addInsert('schedule_month', $Conf->get('month'));
        $SQL->addInsert('schedule_data', $sche);
        $SQL->addInsert('schedule_field', $sfds);
        $SQL->addInsert('schedule_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        $this->Post->set('edit', 'update');

        AcmsLogger::info('スケジュールにデータ登録をしました', [
            'id' => $scid,
            'name' => $define['name'],
            'desc' => $define['desc'],
            'year' => $Conf->get('year'),
            'month' => $Conf->get('month'),
            'data' => $sche,
            'field' => $sfds,
        ]);

        return $this->Post;
    }
}
