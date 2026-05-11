<?php

class ACMS_POST_Schedule_Update extends ACMS_POST_Schedule
{
    function post()
    {
        $Conf = $this->extract('schedule');
        $Conf->setMethod('name', 'required');
        $Conf->setMethod('name', 'maxlength', '255');
        $Conf->setMethod('schedule', 'operative', sessionWithScheduleAdministration());
        $Conf->setMethod('desc', 'maxlength', '255');
        $Conf->validate(new ACMS_Validator());

        if (!$Conf->isValid()) {
            $this->Post->set('step', 'reapply');
            AcmsLogger::info('スケジュールセットの更新に失敗しました');

            return $this->Post;
        }

        // update
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newUpdate('schedule');
        $SQL->addUpdate('schedule_name', $Conf->get('name'));
        $SQL->addUpdate('schedule_desc', $Conf->get('desc'));
        $SQL->addWhereOpr('schedule_id', $this->Get->get('scid'));
        $SQL->addWhereOpr('schedule_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        $this->Post->set('edit', 'update');

        AcmsLogger::info('スケジュールセット「' . $Conf->get('name') . '」を更新しました');

        return $this->Post;
    }
}
