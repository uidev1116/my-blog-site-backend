<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Facades\Common;

class ACMS_POST_Unit_Remove extends ACMS_POST_Unit
{
    public function post()
    {
        /** @var int|null $eid */
        $eid = EID;
        if (is_null($eid)) {
            return $this->Post;
        }
        $entry  = ACMS_RAM::entry($eid);
        if (!roleEntryUpdateAuthorization(BID, $entry)) {
            die403();
        }
        try {
            // ユニットを削除
            /** @var \Acms\Services\Unit\Repository $unitRepository */
            $unitRepository = Application::make('unit-repository');
            $removedUnit = $unitRepository->removeUnit(UTID); // @phpstan-ignore-line
            // エントリ情報を更新
            $this->fixEntry($eid);
            // キャッシュクリア
            ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除
            // ログ
            AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーの指定ユニットを削除しました', $removedUnit->getLegacyData());
        } catch (Exception $e) {
            AcmsLogger::error('「' . ACMS_RAM::entryTitle($eid) . '」エントリーの指定ユニットの削除に失敗しました', Common::exceptionArray($e));
        }

        return $this->Post;
    }
}
