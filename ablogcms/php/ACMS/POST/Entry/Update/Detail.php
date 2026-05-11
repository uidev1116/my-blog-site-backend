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

        /** @var int<1, max>|null $entryId */
        $entryId = EID;
        if ($entryId === null) {
            throw new \LogicException('Entry ID is required.');
        }
        $updatedResponse = $this->update($entryId); // バージョンの更新には非対応のため、revisionIdをnullに設定

        if (is_array($updatedResponse)) {
            $info = [
                'bid'   => BID,
                'cid'   => $updatedResponse['cid'],
                'eid'   => $entryId,
            ];
            if ($updatedResponse['status'] === 'trash') {
                $info['query'] = ['trash' => 'show'];
            }
            $this->redirect(acmsLink($info));
        }
        $this->redirect(acmsLink([
            'bid' => BID,
            'eid' => $entryId,
            'admin' => 'entry-edit',
        ]));
    }
}
