<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Webhook;

class ACMS_POST_Unit_Insert extends ACMS_POST_Unit
{
    public function post()
    {
        $sort = (int) $this->Post->get('unit_sort');
        /** @var non-empty-string|null $parentId */
        $parentId = $this->Post->get('unit_parent_id') !== '' ? $this->Post->get('unit_parent_id') : null;
        $bid = (int) $this->Post->get('bid');
        $eid = (int) $this->Post->get('eid');
        $entry = ACMS_RAM::entry($eid);

        if ($sort < 1) {
            httpStatusCode('400 Bad Request');
            die400();
        }

        if (is_null($entry)) {
            httpStatusCode('404 Not Found Entry');
            page404();
        }

        if (!roleEntryUpdateAuthorization($bid, $entry)) {
            httpStatusCode('403 Forbidden');
            die403();
        }

        /** @var \Acms\Services\Unit\Repository $unitRepository */
        $unitRepository = Application::make('unit-repository');

        $primaryImageUnitId = $this->Post->get('primary_image') !== '' ? $this->Post->get('primary_image') : null;
        ['collection' => $collection] = $unitRepository->extractUnits(null, $primaryImageUnitId);
        $unitRepository->saveAssets($collection);
        $position = [
            'sort' => $sort,
            'parentId' => $parentId,
        ];
        $unitRepository->insertUnits($collection, $position, $eid, $bid);

        $primaryImageUnit = $collection->getPrimaryImageUnit();

        if ($primaryImageUnit !== null) {
            // メイン画像に設定されているときだけ更新する
            $sql = SQL::newUpdate('entry');
            $sql->addUpdate('entry_primary_image', $primaryImageUnit->getId());
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_blog_id', $bid);
            $query = $sql->get(dsn());
            Database::query($query, 'exec');
            ACMS_RAM::entry($eid, null);
        }

        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
        $this->fixEntry($eid);
        // キャッシュクリア
        ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除

        //------
        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, 1]);
            Webhook::call(BID, 'entry', 'entry:updated', [$eid, null]);
        }
        $log = isset($collection->flat()[0]) ? $collection->flat()[0]->getLegacyData() : [];

        Logger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーのユニットを追加しました', $log);

        return $this->Post;
    }
}
