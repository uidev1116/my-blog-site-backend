<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Webhook;

class ACMS_POST_Unit_Update extends ACMS_POST_Unit
{
    public function post()
    {
        $bid = (int) $this->Post->get('bid');
        /** @var non-empty-string|null $unitId */
        $unitId = UTID;
        if ($unitId === null) {
            httpStatusCode('400 Bad Request');
            die400();
        }
        $eid = (int) $this->Post->get('eid');
        $entry = ACMS_RAM::entry($eid);

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

        $unit = $unitRepository->loadUnit($unitId);

        if (is_null($unit)) {
            httpStatusCode('404 Not Found Unit');
            page404();
        }

        $primaryImageUnitId = $this->Post->get('primary_image') !== '' ? $this->Post->get('primary_image') : null;
        ['collection' => $collection] = $unitRepository->extractUnits(null, $primaryImageUnitId);
        $unitRepository->saveAssets($collection);
        // extractUnitsではソート順が変更されているので、ユニットのソート順を更新
        $collection->resort($unit->getSort());
        $unitRepository->updateUnits($collection, $eid, $bid);

        $primaryImageUnit = $collection->getPrimaryImageUnit();

        if ($primaryImageUnit !== null) {
            // メイン画像に設定されているときだけ更新する
            $sql = SQL::newUpdate('entry');
            $sql->addUpdate('entry_primary_image', $primaryImageUnit->getId());
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_blog_id', $bid);
            Database::query($sql->get(dsn()), 'exec');
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

        Logger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーのユニットを更新しました', $log);

        return $this->Post;
    }
}
