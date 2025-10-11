<?php

class ACMS_POST_Entry_Lock_Check extends ACMS_POST_Entry
{
    public function post()
    {
        $eid = (int)$this->Post->get('eid');
        $rvid = $this->Post->get('rvid') ? (int)$this->Post->get('rvid') : null;

        try {
            $service = App::make('entry.lock');
            assert($service instanceof \Acms\Services\Entry\Lock);
            $lockedUser = $service->getLockedUser($eid, $rvid, SUID);

            if ($lockedUser === false) {
                Common::responseJson([
                    'locked' => false,
                    'message' => 'Not locked.',
                ]);
            } else {
                Common::responseJson([
                    'locked' => true,
                    'name' => $lockedUser['name'],
                    'icon' => loadUserIcon($lockedUser['uid']),
                    'datetime' => date('Y年m月d日 H:i', strtotime($lockedUser['datetime'])),
                    'expire' => date('Y年m月d日 H:i', strtotime($lockedUser['expire'])),
                    'viewLink' => acmsLink([
                        'eid' => $eid,
                        'query' => [],
                    ]),
                    'alertOnly' => $service->isAlertOnly(),
                ]);
            }
        } catch (Exception $e) {
            Common::responseJson([
                'locked' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
