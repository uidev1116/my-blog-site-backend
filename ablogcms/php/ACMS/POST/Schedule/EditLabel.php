<?php

class ACMS_POST_Schedule_EditLabel extends ACMS_POST
{
    function post()
    {
        $scid = (int) $this->Get->get('scid', 0);
        $Config = $this->extract('schedule');
        $Config->setMethod('schedule', 'operative', sessionWithScheduleAdministration(BID, $scid));
        $Config->validate(new ACMS_Validator());

        if (!$Config->isValid()) {
            AcmsLogger::info('スケジュールのラベル設定に失敗しました');
            return $this->Post;
        }

        $sort   = $Config->getArray('schedule_label_sort');
        $name   = $Config->getArray('schedule_label_name');
        $class  = $Config->getArray('schedule_label_class');
        $key    = $Config->getArray('schedule_label_key');

        $rows   = count($name);
        $fds    = [];

        /**
         * build Labels
         */
        for ($i = 0; $i < $rows; $i++) {
            if (empty($name[$i])) {
                continue;
            }
            if (empty($key[$i])) {
                $key[$i] = uniqueString();
            }

            $_tmp   = [$name[$i], $key[$i]];
            if (!empty($class[$i])) {
                $_tmp[] = $class[$i];
            }

            $fds[implode(config('schedule_label_separator'), $_tmp)] = $sort[$i];

            unset($_tmp);
        }

        /**
         * save config
         */

        // database
        $DB     = DB::singleton(dsn());

        // delete
        $SQL    = SQL::newDelete('config');
        $SQL->addWhereOpr('config_key', 'schedule_label@' . $scid);
        $SQL->addWhereOpr('config_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');

        Config::forgetCache(BID);

        // insert
        asort($fds);
        $cnt    = 1;
        $Config =& Field::singleton('config');
        $Config->delete('schedule_label@' . $scid);

        $results = [];
        $sql = SQL::newBulkInsert('config');
        foreach ($fds as $label => $num) {
            $sql->addInsert([
                'config_key' => 'schedule_label@' . $scid,
                'config_value' => $label,
                'config_sort' => $cnt++,
                'config_blog_id' => BID,
            ]);
            $Config->add('schedule_label@' . $scid, $label);
            $results[] = $label;
        }
        if ($sql->hasData()) {
            $DB->query($sql->get(dsn()), 'exec');
        }
        Config::forgetCache(BID);
        $this->Post->set('edit', 'update');

        AcmsLogger::info('スケジュールのラベルを設定しました', $results);

        return $this->Post;
    }
}
