<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Storage;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger as AcmsLogger;

class ACMS_POST_Revision_Delete extends ACMS_POST
{
    use \Acms\Traits\Common\AssetsTrait;

    public function post()
    {
        try {
            if (!EID) {
                throw new \RuntimeException('エントリーが指定されていません');
            }
            if (!RVID) {
                throw new \RuntimeException('バージョンが指定されていません');
            }
            if (roleAvailableUser()) {
                if (!roleAuthorization('entry_edit', BID, EID)) {
                    throw new \RuntimeException('権限がありません');
                }
            } else {
                if (!sessionWithCompilation(BID)) {
                    if (!sessionWithContribution(BID)) {
                        throw new \RuntimeException('権限がありません');
                    }
                    if (SUID != ACMS_RAM::entryUser(EID)) {
                        throw new \RuntimeException('権限がありません');
                    }
                }
            }
            if (roleAvailableUser()) {
                if (!roleAuthorization('entry_edit', BID, EID)) {
                    die403();
                }
            } else {
                if (!sessionWithCompilation(BID)) {
                    if (!sessionWithContribution(BID)) {
                        die403();
                    }

                    if (SUID != ACMS_RAM::entryUser(EID)) {
                        die403();
                    }
                }
            }
            $DB = DB::singleton(dsn());
            $revision = Entry::getRevision(EID, RVID);

            // entry
            $SQL = SQL::newDelete('entry_rev');
            $SQL->addWhereOpr('entry_id', EID);
            $SQL->addWhereOpr('entry_rev_id', RVID);
            $SQL->addWhereOpr('entry_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            // unit
            $unitRepository = Application::make('unit-repository');
            assert($unitRepository instanceof \Acms\Services\Unit\Repository);
            $unitRepository->removeUnits(EID, RVID, true);

            // field
            $field = loadEntryField(EID, RVID);
            $this->removeFieldAssetsTrait($field);
            Common::saveField('eid', EID, null, null, RVID);

            // tag
            $SQL = SQL::newDelete('tag_rev');
            $SQL->addWhereOpr('tag_entry_id', EID);
            $SQL->addWhereOpr('tag_rev_id', RVID);
            $SQL->addWhereOpr('tag_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            // relation entry
            $SQL = SQL::newDelete('relationship_rev');
            $SQL->addWhereOpr('relation_id', EID);
            $SQL->addWhereOpr('relation_rev_id', RVID);
            $DB->query($SQL->get(dsn()), 'exec');

            AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '（' . $revision['entry_rev_memo'] . '）」バージョンを削除しました', [
                'eid' => EID,
                'rvid' => RVID,
            ]);
        } catch (\Exception $e) {
            AcmsLogger::info('バージョンを削除できませんでした。' . $e->getMessage(), Common::exceptionArray($e));
        }
        Common::setSafeHeadersWithoutCache(200, 'text/plain');
        die('OK');
    }
}
