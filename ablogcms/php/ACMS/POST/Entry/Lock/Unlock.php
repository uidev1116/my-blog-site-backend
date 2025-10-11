<?php

use Acms\Services\Facades\Logger;

class ACMS_POST_Entry_Lock_Unlock extends ACMS_POST_Entry
{
    public function post()
    {
        $eid = intval($this->Post->get('eid'));

        try {
            $service = App::make('entry.lock');
            assert($service instanceof \Acms\Services\Entry\Lock);
            $service->unlock($eid, 0);

            Logger::info('「' . ACMS_RAM::entryTitle($eid) . '」のロックを解除しました');
            $this->addMessage('「' . ACMS_RAM::entryTitle($eid) . '」のロックを解除しました');
        } catch (Exception $e) {
            Logger::error('「' . ACMS_RAM::entryTitle($eid) . '」のロック解除に失敗しました');
        }
        return $this->Post;
    }
}
