<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Unit\Contracts\Model;

class ACMS_POST_Entry_Duplicate extends ACMS_POST_Entry
{
    use \Acms\Traits\Common\AssetsTrait;

    public function post()
    {
        $eid = idval($this->Post->get('eid', EID));
        if (!$this->validate($eid)) {
            AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーを複製に失敗しました');
        }
        /** @var int<1, max> $eid */
        $newEid = $this->duplicate($eid);
        $cid = idval($this->Post->get('cid'));

        AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーを複製しました', [
            'newEID' => $newEid,
        ]);

        if ($eid === EID) { // @phpstan-ignore-line
            // 詳細画面からの複製の場合は、複製先のエントリー詳細画面にリダイレクト
            $this->redirect(acmsLink([
                'bid'   => BID,
                'cid'   => $cid,
                'eid'   => $newEid,
            ]));
        }
        return $this->Post;
    }

    /**
     * エントリーを複製する
     * @param int $eid 複製元のエントリーID
     * @return int<1, max> 複製先のエントリーID
     */
    protected function duplicate($eid)
    {
        $DB = DB::singleton(dsn());
        $newEid = (int)$DB->query(SQL::nextval('entry_id', dsn()), 'seq');
        if ($newEid < 1) {
            throw new \RuntimeException('Failed to generate new entry id');
        }
        if (enableApproval(BID, CID) && !sessionWithApprovalAdministrator(BID, CID)) {
            $this->approvalDupe($eid, $newEid);
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('saveEntry', [$newEid, 1]);
            }
        } else {
            $this->dupe($eid, $newEid);
            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('saveEntry', [$newEid, null]);
            }
        }
        return $newEid;
    }

    /**
     * エントリーの複製を許可するかどうかを検証する
     * @param int $eid エントリーID
     * @return bool
     */
    protected function validate($eid)
    {
        if (empty($eid)) {
            return false;
        }
        return Entry::canDuplicate($eid);
    }

    /**
     * 関連エントリーの複製
     * @param int $eid 複製元のエントリーID
     * @param int $newEid 複製先のエントリーID
     * @return void
     */
    protected function relationDupe($eid, $newEid)
    {
        $SQL = SQL::newSelect('relationship');
        $SQL->addWhereOpr('relation_id', $eid);
        $all = DB::query($SQL->get(dsn()), 'all');

        $sql = SQL::newBulkInsert('relationship');
        foreach ($all as $row) {
            $sql->addInsert([
                'relation_id' => $newEid,
                'relation_eid' => $row['relation_eid'],
                'relation_type' => $row['relation_type'],
                'relation_order' => $row['relation_order'],
            ]);
        }
        if ($sql->hasData()) {
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    /**
     * 位置情報の複製
     * @param int $eid 複製元のエントリーID
     * @param int $newEid 複製先のエントリーID
     * @return void
     */
    protected function geoDuplicate($eid, $newEid)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('geo');
        $SQL->addWhereOpr('geo_eid', $eid);
        if ($row = $DB->query($SQL->get(dsn()), 'row')) {
            $SQL = SQL::newInsert('geo');
            $SQL->addInsert('geo_eid', $newEid);
            $SQL->addInsert('geo_geometry', $row['geo_geometry']);
            $SQL->addInsert('geo_zoom', $row['geo_zoom']);
            $SQL->addInsert('geo_blog_id', $row['geo_blog_id']);
            $DB->query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * 承認機能が有効な場合のエントリーの複製
     * @param int $eid 複製元のエントリーID
     * @param int $newEid 複製先のエントリーID
     * @return void
     */
    protected function approvalDupe($eid, $newEid)
    {
        $DB = DB::singleton(dsn());
        $bid = ACMS_RAM::entryBlog($eid);
        $approval = ACMS_RAM::entryApproval($eid);
        $sourceRev = false;

        if ($approval === 'pre_approval') {
            $sourceRev = true;
        }

        //------
        // unit
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);
        $rvid = $sourceRev ? 1 : null;
        $collection = $unitRepository->duplicateUnits($eid, $newEid, $rvid, 1);


        //-------
        // entry
        $entryRepository = Application::make('entry.repository');
        assert($entryRepository instanceof \Acms\Services\Entry\EntryRepository);
        if ($sourceRev) {
            $SQL = SQL::newSelect('entry_rev');
            $SQL->addWhereOpr('entry_rev_id', 1);
        } else {
            $SQL = SQL::newSelect('entry');
        }
        $SQL->addWhereOpr('entry_id', $eid);
        $SQL->addWhereOpr('entry_blog_id', $bid);
        $row = $DB->query($SQL->get(dsn()), 'row');
        $title = $row['entry_title'] . config('entry_title_duplicate_suffix');
        $code = ('on' == config('entry_code_title')) ? stripWhitespace($title) : config('entry_code_prefix') . $newEid;
        if (!!config('entry_code_extension') and !strpos($code, '.')) {
            $code .= ('.' . config('entry_code_extension'));
        }

        $uid = intval($row['entry_user_id']);
        if (!($cid = intval($row['entry_category_id']))) {
            $cid = null;
        };

        //------
        // sort
        $esort = $entryRepository->nextSort($bid);
        $usort = $entryRepository->nextUserSort($uid, $bid);
        $csort = $entryRepository->nextCategorySort($cid, $bid);

        $row['entry_id'] = $newEid;
        $row['entry_status'] = 'close';
        $row['entry_title'] = $title;
        $row['entry_code'] = $code;
        if (config('update_datetime_as_duplicate_entry') !== 'off') {
            $row['entry_datetime'] = date('Y-m-d H:i:s', REQUEST_TIME);
        }
        $row['entry_posted_datetime'] = date('Y-m-d H:i:s', REQUEST_TIME);
        $row['entry_updated_datetime'] = date('Y-m-d H:i:s', REQUEST_TIME);
        $row['entry_hash'] = md5(SYSTEM_GENERATED_DATETIME . date('Y-m-d H:i:s', REQUEST_TIME));
        $primaryImageUnit = $collection->getPrimaryImageUnit();
        $row['entry_primary_image'] = $primaryImageUnit ? $primaryImageUnit->getId() : null;
        $row['entry_sort'] = $esort;
        $row['entry_user_sort'] = $usort;
        $row['entry_category_sort'] = $csort;
        $row['entry_user_id'] = SUID;
        $SQL = SQL::newInsert('entry');
        foreach ($row as $fd => $val) {
            if (
                !in_array($fd, [
                    'entry_approval',
                    'entry_approval_public_point',
                    'entry_approval_reject_point',
                    'entry_last_update_user_id',
                    'entry_rev_id',
                    'entry_rev_status',
                    'entry_rev_memo',
                    'entry_rev_user_id',
                    'entry_rev_datetime',
                    'entry_current_rev_id',
                    'entry_reserve_rev_id',
                    'entry_lock_datetime',
                    'entry_lock_uid',
                ], true)
            ) {
                $SQL->addInsert($fd, $val);
            }
        }
        $SQL->addInsert('entry_approval', 'pre_approval');
        $SQL->addInsert('entry_last_update_user_id', SUID);
        $DB->query($SQL->get(dsn()), 'exec');

        $SQL = SQL::newInsert('entry_rev');
        foreach ($row as $fd => $val) {
            if (
                !in_array($fd, [
                    'entry_current_rev_id',
                    'entry_reserve_rev_id',
                    'entry_last_update_user_id',
                    'entry_rev_id',
                    'entry_rev_user_id',
                    'entry_rev_datetime'
                ], true)
            ) {
                $SQL->addInsert($fd, $val);
            }
        }
        $SQL->addInsert('entry_rev_id', 1);
        $SQL->addInsert('entry_rev_user_id', SUID);
        $SQL->addInsert('entry_rev_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $DB->query($SQL->get(dsn()), 'exec');

        //-----
        // tag
        $SQL = SQL::newSelect($sourceRev ? 'tag_rev' : 'tag');
        $SQL->addWhereOpr('tag_entry_id', $eid);
        $SQL->addWhereOpr('tag_blog_id', $bid);
        if ($sourceRev) {
            $SQL->addWhereOpr('tag_rev_id', 1);
        }
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');
        if ($statement && ($row = $DB->next($statement))) {
            $insert = SQL::newBulkInsert('tag_rev');
            do {
                $row['tag_entry_id'] = $newEid;
                if (!$sourceRev) {
                    $row['tag_rev_id'] = 1;
                }
                $insert->addInsert($row);
            } while ($row = $DB->next($statement));
            if ($insert->hasData()) {
                $DB->query($insert->get(dsn()), 'exec');
            }
        }

        //--------------
        // sub category
        if ($sourceRev) {
            $subCategory = loadSubCategories($eid, 1);
        } else {
            $subCategory = loadSubCategories($eid);
        }
        Entry::saveSubCategory($newEid, $cid, implode(',', $subCategory['id']), $bid, 1);

        //-------
        // field
        if ($sourceRev) {
            $Field = loadEntryField($eid, 1);
        } else {
            $Field = loadEntryField($eid);
        }
        $this->duplicateFieldsTrait($Field);
        Common::saveField('eid', $newEid, $Field);
        Entry::saveFieldRevision($newEid, $Field, 1);
        Common::saveFulltext('eid', $newEid, Common::loadEntryFulltext($newEid));
    }

    /**
     * エントリーの複製
     * @param int $eid 複製元のエントリーID
     * @param int $newEid 複製先のエントリーID
     * @return void
     */
    protected function dupe($eid, $newEid)
    {
        $DB = DB::singleton(dsn());
        $bid = ACMS_RAM::entryBlog($eid);
        $approval = ACMS_RAM::entryApproval($eid);
        $sourceRvid = null;
        if ($approval === 'pre_approval') {
            $sourceRvid = 1;
        }

        //-------
        // unit
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);
        $collection = $unitRepository->duplicateUnits($eid, $newEid, $sourceRvid, null);

        //-------
        // entry
        $entryRepository = Application::make('entry.repository');
        assert($entryRepository instanceof \Acms\Services\Entry\EntryRepository);
        $SQL    = SQL::newSelect('entry');
        $SQL->addWhereOpr('entry_id', $eid);
        $SQL->addWhereOpr('entry_blog_id', $bid);
        $row = $DB->query($SQL->get(dsn()), 'row');
        $title  = $row['entry_title'] . config('entry_title_duplicate_suffix');
        $code   = ('on' == config('entry_code_title')) ? stripWhitespace($title) : config('entry_code_prefix') . $newEid;
        if (!!config('entry_code_extension') and !strpos($code, '.')) {
            $code .= ('.' . config('entry_code_extension'));
        }

        $uid    = intval($row['entry_user_id']);
        if (!($cid = intval($row['entry_category_id']))) {
            $cid = null;
        };

        //------
        // sort
        $esort = $entryRepository->nextSort($bid);
        $usort = $entryRepository->nextUserSort($uid, $bid);
        $csort = $entryRepository->nextCategorySort($cid, $bid);

        $row['entry_id'] = $newEid;
        $row['entry_status'] = 'close';
        $row['entry_approval'] = 'none';
        $row['entry_title'] = $title;
        $row['entry_code'] = $code;
        if (config('update_datetime_as_duplicate_entry') !== 'off') {
            $row['entry_datetime'] = date('Y-m-d H:i:s', REQUEST_TIME);
        }
        $row['entry_posted_datetime'] = date('Y-m-d H:i:s', REQUEST_TIME);
        $row['entry_updated_datetime'] = date('Y-m-d H:i:s', REQUEST_TIME);
        $row['entry_hash'] = md5(SYSTEM_GENERATED_DATETIME . date('Y-m-d H:i:s', REQUEST_TIME));
        $primaryImageUnit = $collection->getPrimaryImageUnit();
        $row['entry_primary_image'] = $primaryImageUnit ? $primaryImageUnit->getId() : null;
        $row['entry_sort'] = $esort;
        $row['entry_user_sort'] = $usort;
        $row['entry_category_sort'] = $csort;
        $row['entry_user_id'] = SUID;
        $SQL = SQL::newInsert('entry');
        foreach ($row as $fd => $val) {
            if ($fd === 'entry_current_rev_id' || $fd === 'entry_reserve_rev_id') {
                continue;
            }
            $SQL->addInsert($fd, $val);
        }
        $DB->query($SQL->get(dsn()), 'exec');

        //-----
        // tag
        $SQL = SQL::newSelect('tag');
        $SQL->addWhereOpr('tag_entry_id', $eid);
        $SQL->addWhereOpr('tag_blog_id', $bid);
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        if ($statement && ($row = $DB->next($statement))) {
            $insert = SQL::newBulkInsert('tag');
            do {
                $row['tag_entry_id'] = $newEid;
                $insert->addInsert($row);
            } while ($row = $DB->next($statement));
            if ($insert->hasData()) {
                $DB->query($insert->get(dsn()), 'exec');
            }
        }

        //--------------
        // sub category
        $subCategory = loadSubCategories($eid);
        Entry::saveSubCategory($newEid, $cid, implode(',', $subCategory['id']));

        //-------
        // field
        $Field  = loadEntryField($eid);
        $this->duplicateFieldsTrait($Field);
        Common::saveField('eid', $newEid, $Field);
        Common::saveFulltext('eid', $newEid, Common::loadEntryFulltext($newEid));

        //---------------
        // related entry
        $this->relationDupe($eid, $newEid);

        //----------
        // geo data
        $this->geoDuplicate($eid, $newEid);
    }
}
