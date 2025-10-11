<?php

use Acms\Services\Facades\Application;
use Acms\Services\Unit\UnitCollection;

class ACMS_POST_Entry_Update_Detail extends ACMS_POST_Entry_Update
{
    /**
     *
     *
     * @inheritdoc
     */
    protected function saveUnit(UnitCollection $collection, int $eid): UnitCollection
    {
        // ユニットを保存しない
        return $collection;
    }

    public function post()
    {
        $this->unitRepository = Application::make('unit-repository');
        $this->lockService = Application::make('entry.lock');
        assert($this->unitRepository instanceof \Acms\Services\Unit\Repository);
        assert($this->lockService instanceof \Acms\Services\Entry\Lock);

        $updatedResponse = $this->update();

        if (is_array($updatedResponse)) {
            $Session =& Field::singleton('session');
            $Session->add('entry_action', 'update');
            $info = [
                'bid'   => BID,
                'cid'   => $updatedResponse['cid'],
                'eid'   => EID,
            ];
            if ($updatedResponse['trash'] == 'trash') {
                $info['query'] = ['trash' => 'show'];
            }
            $this->redirect(acmsLink($info));
        }
        $this->redirect(acmsLink([
            'bid' => BID,
            'eid' => EID,
            'admin' => 'entry-edit',
        ]));
    }
}
