<?php

class ACMS_POST_Layout_Save extends ACMS_POST_Layout
{
    function post()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        $DB = DB::singleton(dsn());
        $identifier = $this->Post->get('id');
        $ids = $this->Post->getArray('ids');

        $SQL = SQL::newDelete('layout_grid');
        $SQL->addWhereOpr('layout_grid_identifier', $identifier);
        $DB->query($SQL->get(dsn()), 'exec');

        $map = [];
        foreach ($ids as $i => $id) {
            $map[$id] = $i + 1;
        }
        foreach ($ids as $i => $id) {
            $pid = $this->Post->get('parent_' . $id);
            $pid = !empty($pid) ? $map[$pid] : 0;
            $data = [
                'id'        => $id,
                'serial'    => $i + 1,
                'identifier' => $identifier,
                'class'     => $this->Post->get('class_' . $id),
                'pid'       => $pid,
                'col'       => $this->Post->get('col_' . $id),
                'row'       => $this->Post->get('row_' . $id),
                'mid'       => $this->Post->get('mid_' . $id),
                'tpl'       => $this->Post->get('tpl_' . $id),
            ];
            $this->save($data);
        }

        AcmsLogger::info('「' . $identifier . '」レイアウトを保存しました');

        $Get = $this->Get;
        $query = '';
        $url = HTTP_REQUEST_URL;

        if (!$Get->isNull()) {
            foreach ($Get->listFields() as $fd) {
                if ($fd === 'layout' || !$aryVal = $Get->get($fd)) {
                    continue;
                }
                $query  .= ($fd . '=' . $aryVal);
            }
        }
        if (!empty($query)) {
            $url .= ('?' . $query);
        }

        $this->redirect($url);
    }
}
