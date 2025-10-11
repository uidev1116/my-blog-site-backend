<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger as AcmsLogger;

class ACMS_POST_Unit_Duplicate extends ACMS_POST_Unit
{
    public function post()
    {
        /** @var non-empty-string|null */
        $utid = UTID;
        /** @var positive-int|null */
        $eid = EID;

        if ($eid === null || $utid === null) {
            die500();
        }
        assert($eid !== null);
        assert($utid !== null);

        $entry = ACMS_RAM::entry($eid);
        if ($entry === null) {
            die500();
        }
        assert(is_array($entry));
        /** @var array{entry_id?: int, entry_user_id?: int, entry_status?: string, entry_start_datetime?: string, entry_end_datetime?: string} $entry */
        if (!roleEntryAuthorization(BID, $entry)) {
            die403();
        }
        try {
            // ユニットをコピー
            $unitRepository = Application::make('unit-repository');
            assert($unitRepository instanceof \Acms\Services\Unit\Repository);
            $copiedUnit = $unitRepository->duplicateUnit($utid, $eid);
            // フルテキストを再生成
            Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
            // エントリー情報を更新
            $this->fixEntry($eid);
            // キャッシュクリア
            ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除
            // ログ
            AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーの指定ユニットを複製しました', $copiedUnit->getLegacyData());

            // リダイレクト
            $this->redirect(acmsLink([
                'tpl'   => 'include/unit-fetch.html',
                'eid'   => $eid,
            ], ['ignoreTplIfAjax' => false]));
        } catch (Exception $e) {
            AcmsLogger::error('「' . ACMS_RAM::entryTitle($eid) . '」エントリーの指定ユニットの複製に失敗しました', Common::exceptionArray($e));
        }
        return $this->Post;
    }
}
