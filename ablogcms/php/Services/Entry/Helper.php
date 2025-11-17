<?php

namespace Acms\Services\Entry;

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Preview;
use Acms\Services\Facades\Auth;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Entry;
use ACMS_RAM;
use Field;
use SQL;

class Helper
{
    use \Acms\Traits\Common\AssetsTrait;
    use \Acms\Traits\Unit\UnitModelTrait;

    /**
     * サマリーの表示で使うユニットの範囲を取得
     *
     * @var int
     */
    protected $summaryRange;

    /**
     * 苦肉の策で、新規アップロードされたファイルをここに一時保存する
     *
     * @var array
     */
    protected $uploadedFiles = [];

    /**
     * 苦肉の策で、新規バージョン作成か一時的に保存する
     *
     * @var mixed
     */
    protected $isNewVersion = false;

    /**
     * 一時保存したユニットデータ
     *
     * @var \Acms\Services\Unit\UnitCollection|array|null
     */
    protected $tempUnitData = null;

    /**
     * サマリーの表示で使うユニットの範囲を取得
     * extractUnits 後に決定
     *
     * @return int
     */
    public function getSummaryRange()
    {
        return $this->summaryRange;
    }

    /**
     * サマリーの表示で使うユニットの範囲を設定
     * extractUnits 時に設定
     * @param ?int $summaryRange
     * @return void
     */
    public function setSummaryRange(?int $summaryRange): void
    {
        $this->summaryRange = $summaryRange;
    }

    /**
     * アップロードされたファイルを取得
     * Entry::extractColumn 後に決定
     *
     * @return array
     */
    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    /**
     * アップロードされたファイルを取得
     * Entry::extractColumn 後に決定
     *
     * @param string $path
     * @return void
     */
    public function addUploadedFiles($path)
    {
        $this->uploadedFiles[] = $path;
    }

    /**
     * 新規バージョン作成の判定をセット
     *
     * @param boolean $flag
     * @return void
     */
    public function setNewVersion($flag)
    {
        $this->isNewVersion = $flag;
    }

    /**
     * 新規バージョン作成の判定を取得
     *
     * @return boolean
     */
    public function isNewVersion()
    {
        return $this->isNewVersion;
    }

    /**
     * 一時的にユニットを保存
     *
     * @param \Acms\Services\Unit\UnitCollection|array $data
     * @return void
     */
    public function setTempUnitData($data): void
    {
        $this->tempUnitData = $data;
    }

    /**
     * 一時ユニットデータを取得
     *
     * @return \Acms\Services\Unit\UnitCollection|array|null
     */
    public function getTempUnitData()
    {
        return $this->tempUnitData;
    }

    /**
     * エントリーコードの重複をチェック
     *
     * @param string $code
     * @param int $bid
     * @param int $cid
     * @param int $eid
     *
     * @return bool
     */
    public function validEntryCodeDouble($code, $bid = BID, $cid = null, $eid = null)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('entry');
        $SQL->addSelect('entry_id');
        $SQL->addWhereOpr('entry_code', $code);
        $SQL->addWhereOpr('entry_id', $eid, '<>');
        $SQL->addWhereOpr('entry_category_id', $cid);
        $SQL->addWhereOpr('entry_blog_id', $bid);

        if ($DB->query($SQL->get(dsn()), 'one')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * エントリーのタグをバリデート
     *
     * @param \Field_Validation $Entry
     *
     * @return \Field_Validation
     */
    public function validTag($Entry)
    {
        $tags = $Entry->get('tag');
        if (!empty($tags)) {
            $tags = Common::getTagsFromString($tags, false);
            foreach ($tags as $sort => $tag) {
                if (isReserved($tag)) {
                    $Entry->setMethod('tag', 'reserved', false);
                    break;
                }
                if (!preg_match(REGEX_INVALID_TAG_NAME, $tag)) {
                    $Entry->setMethod('tag', 'string', false);
                    break;
                }
            }
        }
        return $Entry;
    }

    /**
     * エントリーのサブカテゴリーをバリデート
     *
     * @param \Field_Validation $Entry
     *
     * @return \Field_Validation
     */
    public function validSubCategory($Entry)
    {
        $limit = config('entry_edit_sub_category_limit');
        if (is_numeric($limit)) {
            $subCategoryIds = $this->getSubCategoryFromString($Entry->get('sub_category_id'), ',');
            if (count($subCategoryIds) > intval($limit)) {
                $Entry->setMethod('sub_category_id', 'max_sub_category_id', false);
            }
        }
        return $Entry;
    }

    /**
     * エントリーの削除
     *
     * @param int $eid
     *
     * @return void
     */
    public function entryDelete($eid, $changeRevision = false)
    {
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);

        //------------
        // エントリ削除
        $sql = SQL::newDelete('entry');
        $sql->addWhereOpr('entry_id', $eid);
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::entry($eid, null);

        //-----------
        // タグ削除
        $sql = SQL::newDelete('tag');
        $sql->addWhereOpr('tag_entry_id', $eid);
        DB::query($sql->get(dsn()), 'exec');

        //-------------
        // コメント削除
        if ($changeRevision === false) {
            $sql = SQL::newDelete('comment');
            $sql->addWhereOpr('comment_entry_id', $eid);
            DB::query($sql->get(dsn()), 'exec');
        }

        //------------------
        // 動的フォームを削除
        if ($changeRevision === false) {
            $sql = SQL::newDelete('column');
            $sql->addWhereOpr('column_entry_id', $eid);
            $sql->addWhereOpr('column_attr', 'acms-form');
            DB::query($sql->get(dsn()), 'exec');
        }

        //------------------
        // サブカテゴリーを削除
        $sql = SQL::newDelete('entry_sub_category');
        $sql->addWhereOpr('entry_sub_category_eid', $eid);
        DB::query($sql->get(dsn()), 'exec');

        //-----------------
        // 関連エントリを削除
        $sql = SQL::newDelete('relationship');
        $sql->addWhereOpr('relation_id', $eid);
        DB::query($sql->get(dsn()), 'exec');

        //-----------------
        // フルテキストを削除
        $sql = SQL::newDelete('fulltext');
        $sql->addWhereOpr('fulltext_eid', $eid);
        DB::query($sql->get(dsn()), 'exec');

        //-------------------------
        // ユニット削除・アセット類削除
        if ($changeRevision === false) {
            // カスタムフィールドのファイル類を削除
            $field = loadEntryField($eid);
            $this->removeFieldAssetsTrait($field);
            // ユニットを削除 & ユニットのファイル類を削除
            $unitRepository->removeUnits($eid, null, true);
        } else {
            // ユニットデータのみ削除
            $unitRepository->removeUnits($eid, null, false);
        }

        //------------------
        // フィールドデータ削除
        Common::saveField('eid', $eid);

        //-----------------------
        // キャッシュクリア予約削除
        Entry::deleteCacheControl($eid);
    }

    /**
     * エントリーのバージョンを削除
     *
     * @param int $eid
     *
     * @return void
     */
    public function revisionDelete($eid)
    {
        //------
        // unit
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);

        $revisionIds = $unitRepository->getRevisionIds($eid);
        foreach ($revisionIds as $rvid) {
            if ($eid && $rvid) {
                $unitRepository->removeUnits($eid, $rvid, true);
            }
        }

        //-----
        // tag
        $SQL = SQL::newDelete('tag_rev');
        $SQL->addWhereOpr('tag_entry_id', $eid);
        DB::query($SQL->get(dsn()), 'exec');

        //---------------
        // sub category
        $SQL = SQL::newDelete('entry_sub_category_rev');
        $SQL->addWhereOpr('entry_sub_category_eid', $eid);
        DB::query($SQL->get(dsn()), 'exec');

        //-------
        // field
        $SQL = SQL::newSelect('entry_rev');
        $SQL->addSelect('entry_rev_id');
        $SQL->addWhereOpr('entry_id', $eid);
        if ($all = DB::query($SQL->get(dsn()), 'all')) {
            foreach ($all as $rev) {
                $rvid = $rev['entry_rev_id'];
                $field = loadEntryField($eid, $rvid);
                $this->removeFieldAssetsTrait($field);
                Common::saveField('eid', $eid, null, null, $rvid);
            }
        }

        //-------
        // entry
        $SQL = SQL::newDelete('entry_rev');
        $SQL->addWhereOpr('entry_id', $eid);
        DB::query($SQL->get(dsn()), 'exec');
    }

    /**
     * バージョンの切り替え
     *
     * @param int $rvid
     * @param int $eid
     * @param int $bid
     *
     * @return int|null|false カテゴリ-ID
     */
    function changeRevision($rvid, $eid, $bid)
    {
        $DB = DB::singleton(dsn());
        $cid = null;
        $primaryImageId = null;
        if (!is_numeric($rvid)) {
            return false;
        }
        $sql = SQL::newSelect('entry_rev');
        $sql->addWhereOpr('entry_id', $eid);
        $sql->addWhereOpr('entry_rev_id', $rvid);
        $revision = DB::query($sql->get(dsn()), 'row');
        if (empty($revision)) {
            return false;
        }
        $publicDatetime = $revision['entry_start_datetime'];
        if (strtotime($publicDatetime) > REQUEST_TIME) {
            $sql = SQL::newUpdate('entry');
            $sql->setUpdate('entry_reserve_rev_id', $rvid);
            $sql->addWhereOpr('entry_id', $eid);
            DB::query($sql->get(dsn()), 'exec');
            return ACMS_RAM::entryCategory($eid);
        }

        // エントリの情報を削除
        Entry::entryDelete($eid, true);

        //-------
        // entry
        $SQL = SQL::newSelect('entry_rev');
        $SQL->addWhereOpr('entry_id', $eid);
        $SQL->addWhereOpr('entry_rev_id', $rvid);
        $q = $SQL->get(dsn());

        $primaryImageUnitId = null;
        $Entry = SQL::newInsert('entry');
        if ($row = $DB->query($q, 'row')) {
            $cid = $row['entry_category_id'];
            foreach ($row as $key => $val) {
                if (!preg_match('@^(entry_rev|entry_approval)@', $key)) {
                    $Entry->addInsert($key, $val);
                }
            }
            $Entry->addInsert('entry_current_rev_id', $rvid);
            $Entry->addInsert('entry_reserve_rev_id', 0);
            if (SUID) {
                $Entry->addInsert('entry_last_update_user_id', SUID);
            }
            $DB->query($Entry->get(dsn()), 'exec');

            $primaryImageUnitId = (string)$row['entry_primary_image'];
        }

        //------
        // unit
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);
        $collection = $unitRepository->loadUnits($eid, $rvid, null, ['setPrimaryImage' => true]);
        $newCollection = $collection->clone();
        $savedCollection = $unitRepository->saveAllUnits($newCollection, $eid, $bid);

        //---------------------
        // primaryImageIdを更新
        $primaryImageUnit = $savedCollection->getPrimaryImageUnit();
        $newPrimaryImageUnitId = $primaryImageUnit !== null ? $primaryImageUnit->getId() : null;
        if ($newPrimaryImageUnitId !== null) {
            $primaryImageUpdateSql = SQL::newUpdate('entry');
            $primaryImageUpdateSql->addUpdate('entry_primary_image', $newPrimaryImageUnitId);
            $primaryImageUpdateSql->addWhereOpr('entry_id', $eid);
            $DB->query($primaryImageUpdateSql->get(dsn()), 'exec');
        }
        ACMS_RAM::entry($eid, null);

        //-------
        // field
        $Field = loadEntryField($eid, $rvid);
        Common::saveField('eid', $eid, $Field);

        //-------
        // tag
        $SQL = SQL::newSelect('tag_rev');
        $SQL->addWhereOpr('tag_entry_id', $eid);
        $SQL->addWhereOpr('tag_rev_id', $rvid);
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        $insert = SQL::newBulkInsert('tag');
        if ($statement && ($row = $DB->next($statement))) {
            do {
                unset($row['tag_rev_id']);
                $insert->addInsert($row);
            } while ($row = $DB->next($statement));
        }
        if ($insert->hasData()) {
            $DB->query($insert->get(dsn()), 'exec');
        }

        //---------------
        // sub category
        $SQL = SQL::newDelete('entry_sub_category');
        $SQL->addWhereOpr('entry_sub_category_eid', $eid);
        $DB->query($SQL->get(dsn()), 'exec');

        $SQL = SQL::newSelect('entry_sub_category_rev');
        $SQL->addWhereOpr('entry_sub_category_eid', $eid);
        $SQL->addWhereOpr('entry_sub_category_rev_id', $rvid);
        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        $subCategory = SQL::newBulkInsert('entry_sub_category');
        if ($statement && ($row = $DB->next($statement))) {
            do {
                unset($row['entry_sub_category_rev_id']);
                $subCategory->addInsert($row);
            } while ($row = $DB->next($statement));
        }
        if ($subCategory->hasData()) {
            $DB->query($subCategory->get(dsn()), 'exec');
        }

        //---------------
        // related entry
        $SQL = SQL::newSelect('relationship_rev');
        $SQL->addWhereOpr('relation_id', $eid);
        $SQL->addWhereOpr('relation_rev_id', $rvid);
        $relations = $DB->query($SQL->get(dsn()), 'all');

        $insert = SQL::newBulkInsert('relationship');
        foreach ($relations as $relation) {
            $insert->addInsert([
                'relation_id' => $eid,
                'relation_eid' => $relation['relation_eid'],
                'relation_type' => $relation['relation_type'],
                'relation_order' => $relation['relation_order'],
            ]);
        }
        if ($insert->hasData()) {
            $DB->query($insert->get(dsn()), 'exec');
        }

        //----------
        // fulltext
        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));

        return $cid;
    }

    /**
     * サブカテゴリーを保存
     *
     * @param int $eid
     * @param int|null $masterCid
     * @param string $cids
     * @param int $bid
     * @param int|null $rvid
     *
     * @return void
     */
    public function saveSubCategory($eid, $masterCid, $cids, $bid = BID, $rvid = null)
    {
        try {
            $DB = DB::singleton(dsn());
            $table = 'entry_sub_category';
            if (!empty($rvid)) {
                $table = 'entry_sub_category_rev';
            }
            $SQL = SQL::newDelete($table);
            $SQL->addWhereOpr('entry_sub_category_eid', $eid);
            if (!empty($rvid)) {
                $SQL->addWhereOpr('entry_sub_category_rev_id', $rvid);
            }
            $DB->query($SQL->get(dsn()), 'exec');

            $cidAry = $this->getSubCategoryFromString($cids, ',');

            $insert = SQL::newBulkInsert($table);
            foreach ($cidAry as $cid) {
                if ($masterCid == $cid) {
                    continue;
                }
                $data = [
                    'entry_sub_category_eid' => $eid,
                    'entry_sub_category_id' => $cid,
                    'entry_sub_category_blog_id' => $bid,
                ];
                if ($rvid) {
                    $data['entry_sub_category_rev_id'] = $rvid;
                }
                $insert->addInsert($data);
            }
            if ($insert->hasData()) {
                $DB->query($insert->get(dsn()), 'exec');
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param string $string
     * @param string $delimiter
     * @return array
     */
    public function getSubCategoryFromString($string, $delimiter = ',')
    {
        $delimiter = $delimiter ? $delimiter : ',';
        $cidAry = explode($delimiter, $string);
        $list = [];
        foreach ($cidAry as $item) {
            $item = preg_replace('/^[\s　]+|[\s　]+$/u', '', $item);
            if ($item !== '') {
                $list[] = $item;
            }
        }
        return $list;
    }

    /**
     * 関連エントリーを保存
     *
     * @param int $eid
     * @param array $entryAry
     * @param int $rvid
     * @param array $typeAry
     *
     * @return void
     */
    public function saveRelatedEntries($eid, $entryAry = [], $rvid = null, $typeAry = [], $loadedTypes = [])
    {
        $DB = DB::singleton(dsn());
        $table = 'relationship';
        if (!empty($rvid)) {
            $table = 'relationship_rev';
        }
        $SQL = SQL::newDelete($table);
        $SQL->addWhereOpr('relation_id', $eid);
        $SQL->addWhereIn('relation_type', $loadedTypes);
        if (!empty($rvid)) {
            $SQL->addWhereOpr('relation_rev_id', $rvid);
        }
        $DB->query($SQL->get(dsn()), 'exec');

        $exists = [];
        $insert = SQL::newBulkInsert($table);
        foreach ($entryAry as $i => $reid) {
            try {
                $type = $typeAry[$i] ?? '';
                if (!isset($exists[$type])) {
                    $exists[$type] = [];
                }
                if (in_array($reid, $exists[$type], true)) {
                    continue;
                }
                $data = [
                    'relation_id' => $eid,
                    'relation_eid' => $reid,
                    'relation_order' => $i,
                    'relation_type' => $type ? $type : 'default',
                ];
                if ($rvid) {
                    $data['relation_rev_id'] = $rvid;
                }
                $insert->addInsert($data);
                $exists[$type][] = $reid;
            } catch (\Exception $e) {
            }
        }
        if ($insert->hasData()) {
            $DB->query($insert->get(dsn()), 'exec');
        }
    }

    /**
     * エントリーのバージョンを保存
     *
     * @param int $eid
     * @param int $rvid
     * @param array $entryAry
     * @param string $type
     * @param string $memo
     *
     * @return int|false 保存したリビジョンID
     */
    public function saveEntryRevision($eid, $rvid, $entryAry, $type = '', $memo = '')
    {
        if (!enableRevision()) {
            return false;
        }
        if (empty($rvid) || empty($type)) {
            $rvid = 1;
        }
        $isNewRevision = false;

        if ($type === 'new') {
            // 新しいリビジョン番号取得
            $sql = SQL::newSelect('entry_rev');
            $sql->addSelect('entry_rev_id', 'max_rev_id', null, 'MAX');
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_blog_id', BID);

            $rvid = 2;
            if ($max = DB::query($sql->get(dsn()), 'one')) {
                $rvid = $max + 1;
            }
            if (empty($memo)) {
                $memo = sprintf(config('revision_default_memo'), $rvid);
            }
            $isNewRevision = true;
        } else {
            if ($rvid === 1) {
                $memo = config('revision_temp_memo');
            }
            $sql = SQL::newSelect('entry_rev');
            $sql->setSelect('entry_id');
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_rev_id', $rvid);
            $isNewRevision = !DB::query($sql->get(dsn()), 'one');
        }

        $entryData = [];
        if ($isNewRevision) {
            // 現在のエントリ情報を抜き出す
            $sql = SQL::newSelect('entry');
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_blog_id', BID);
            if ($row = DB::query($sql->get(dsn()), 'row')) {
                foreach ($row as $key => $val) {
                    $entryData[$key] = $val;
                }
            }
        }
        foreach ($entryAry as $key => $val) {
            $entryData[$key] = $val;
        }

        if ($isNewRevision) {
            // リビジョン作成
            $sql = SQL::newInsert('entry_rev');
            $sql->addInsert('entry_rev_id', $rvid);
            $sql->addInsert('entry_rev_user_id', SUID);
            $sql->addInsert('entry_rev_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $sql->addInsert('entry_rev_memo', $memo);
            if (sessionWithApprovalAdministrator(BID, $entryData['entry_category_id'])) {
                $sql->addInsert('entry_rev_status', 'approved');
            }
            foreach ($entryData as $key => $val) {
                if (!in_array($key, ['entry_current_rev_id', 'entry_reserve_rev_id', 'entry_last_update_user_id'], true)) {
                    $sql->addInsert($key, $val);
                }
            }
            DB::query($sql->get(dsn()), 'exec');
        } else {
            $sql = SQL::newUpdate('entry_rev');
            $sql->addUpdate('entry_rev_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            if (!empty($memo)) {
                $sql->addUpdate('entry_rev_memo', $memo);
            }
            if (sessionWithApprovalAdministrator(BID, $entryData['entry_category_id'])) {
                $sql->addUpdate('entry_rev_status', 'approved');
            }
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_rev_id', $rvid);
            foreach ($entryData as $key => $val) {
                if (!in_array($key, ['entry_current_rev_id', 'entry_last_update_user_id'], true)) {
                    $sql->addUpdate($key, $val);
                }
            }
            $sql->addUpdate('entry_blog_id', BID);
            DB::query($sql->get(dsn()), 'exec');
        }
        return $rvid;
    }

    /**
     * カスタムフィールドのバージョンを保存
     *
     * @param int $eid
     * @param Field $Field
     * @param int $rvid
     *
     * @return bool
     */
    public function saveFieldRevision($eid, $Field, $rvid)
    {
        if (!enableRevision()) {
            return false;
        }

        Common::saveField('eid', $eid, $Field, null, $rvid);

        return true;
    }

    /**
     * キャッシュ自動削除の情報を更新
     *
     * @param string $start
     * @param string $end
     * @param int $bid
     * @param int $eid
     *
     * @return bool
     */
    public function updateCacheControl($start, $end, $bid = BID, $eid = EID)
    {
        if (
            0
            || !$bid
            || !$eid
            || ACMS_RAM::entryStatus($eid) !== 'open'
        ) {
            return false;
        }

        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('cache_reserve');
        $SQL->addWhereOpr('cache_reserve_datetime', date('Y-m-d H:i:s', REQUEST_TIME), '<', 'OR');
        $W = SQL::newWhere();
        $W->addWhereOpr('cache_reserve_entry_id', $eid);
        $W->addWhereOpr('cache_reserve_blog_id', $bid);
        $SQL->addWhere($W, 'OR');
        $DB->query($SQL->get(dsn()), 'exec');

        if ($start > date('Y-m-d H:i:s', REQUEST_TIME)) {
            $SQL = SQL::newInsert('cache_reserve');
            $SQL->addInsert('cache_reserve_datetime', $start);
            $SQL->addInsert('cache_reserve_entry_id', $eid);
            $SQL->addInsert('cache_reserve_blog_id', $bid);
            $SQL->addInsert('cache_reserve_type', 'start');
            $DB->query($SQL->get(dsn()), 'exec');
        }

        if ($end > date('Y-m-d H:i:s', REQUEST_TIME) && $end < '3000/12/31 23:59:59') {
            $SQL = SQL::newInsert('cache_reserve');
            $SQL->addInsert('cache_reserve_datetime', $end);
            $SQL->addInsert('cache_reserve_entry_id', $eid);
            $SQL->addInsert('cache_reserve_blog_id', $bid);
            $SQL->addInsert('cache_reserve_type', 'end');
            $DB->query($SQL->get(dsn()), 'exec');
        }

        return true;
    }

    /**
     * キャッシュ自動削除の情報を削除
     *
     * @param int $eid
     *
     * @return bool
     */
    public function deleteCacheControl($eid = EID)
    {
        if (!$eid) {
            return false;
        }

        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('cache_reserve');
        $SQL->addWhereOpr('cache_reserve_datetime', date('Y-m-d H:i:s', REQUEST_TIME), '<', 'OR');
        $SQL->addWhereOpr('cache_reserve_entry_id', $eid, '=', 'OR');
        $DB->query($SQL->get(dsn()), 'exec');

        return true;
    }

    /**
     * 指定されたリビジョンを取得
     * @param int $eid
     * @param int $rvid
     * @return array
     */
    public function getRevision($eid, $rvid)
    {
        $sql = SQL::newSelect('entry_rev');
        $sql->addWhereOpr('entry_id', $eid);
        $sql->addWhereOpr('entry_rev_id', $rvid);

        return DB::query($sql->get(dsn()), 'row');
    }

    /**
     * 現在のログインユーザーがダイレクト編集を利用可能かどうかを判定する
     *
     * @return bool
     */
    public function canUseDirectEdit(): bool
    {
        if ('on' !== config('entry_edit_inplace')) {
            return false;
        }

        if (!defined('EID')) {
            return false;
        }

        /** @var int|null $entryId */
        $entryId = EID;
        if (is_null($entryId)) {
            return false;
        }

        if (VIEW !== 'entry') { // @phpstan-ignore-line
            return false;
        }

        if (ADMIN) { // @phpstan-ignore-line
            // 管理画面はダイレクト編集は利用不可
            return false;
        }

        if (defined('RVID') && RVID !== null && RVID > 0) {
            // バージョン詳細画面はダイレクト編集は利用不可
            return false;
        }

        if (Preview::isPreviewMode()) {
            // プレビューモードはダイレクト編集は利用不可
            return false;
        }

        $entry = ACMS_RAM::entry($entryId);

        if (is_null($entry)) {
            return false;
        }

        if ($entry['entry_approval'] === 'pre_approval') {
            return false;
        }

        if (enableApproval() && !sessionWithApprovalAdministrator()) {
            // 承認機能が有効で、かつ最終承認者でない場合はダイレクト編集は利用不可
            return false;
        }

        if (
            !roleEntryUpdateAuthorization(BID, $entry) &&
            !(sessionWithContribution() && SUID == ACMS_RAM::entryUser($entry['entry_id']))
        ) {
            // ロールによる編集権限がなく、かつエントリーの所有ユーザーでない場合はダイレクト編集は利用不可
            return false;
        }

        return true;
    }

    /**
     * 現在のログインユーザーのダイレクト編集機能が有効な状態かどうかを判定する
     *
     * @return bool
     */
    public function isDirectEditEnabled(): bool
    {
        if (!$this->canUseDirectEdit()) {
            // ダイレクト編集が利用可能な状態でない場合は無効とする
            return false;
        }

        if ('on' !== config('entry_edit_inplace_enable')) {
            return false;
        }

        return true;
    }

    /**
     * 現在のログインユーザーがエントリーを削除可能かどうかを判定する
     *
     * @param int $entryId
     * @return bool
    */
    public function canDelete(int $entryId): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }

        $blogId = ACMS_RAM::entryBlog($entryId);
        $categoryId = ACMS_RAM::entryCategory($entryId);

        if (enableApproval($blogId, $categoryId)) {
            return $this->canDeleteByApproval($blogId, $categoryId, $entryId);
        }

        if (roleAvailableUser()) {
            return $this->canDeleteByRole($blogId, $entryId);
        }

        return $this->canDeleteByDefault($blogId, $entryId);
    }


    /**
     * 現在のログインユーザーがエントリーを一括削除可能かどうかを判定する
     *
     * @param int $blogId
     * @param int|null $categoryId
     * @return bool
    */
    public function canBulkDelete(int $blogId, ?int $categoryId = null): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }


        if (enableApproval($blogId, $categoryId)) {
            return $this->canDeleteByApproval($blogId, $categoryId);
        }

        if (roleAvailableUser()) {
            return $this->canDeleteByRole($blogId);
        }

        return $this->canDeleteByDefault($blogId);
    }

    /**
     * 承認機能有効時にログインユーザーがエントリーを削除できるかどうかを判定する
     *
     * @param int $blogId
     * @param int|null $categoryId
     * @param int|null $entryId
     * @return bool
     */
    private function canDeleteByApproval(int $blogId, ?int $categoryId = null, ?int $entryId = null): bool
    {
        if (!enableApproval($blogId, $categoryId)) {
            throw new \BadMethodCallException('承認機能が無効です');
        }

        if (config('approval_contributor_edit_auth') === 'on') {
            // 投稿者が自身が投稿した記事のみ編集できる設定が有効な場合はロール及び通常の権限に従う
            if (roleAvailableUser()) {
                return $this->canDeleteByRole($blogId, $entryId);
            }

            return $this->canDeleteByDefault($blogId, $entryId);
        }


        if (sessionWithApprovalAdministrator($blogId, $categoryId)) {
            // 最終承認者またはルートブログの管理者の場合は削除可能
            return true;
        }

        if ($entryId !== null && $entryId > 0) {
            if (ACMS_RAM::entryApproval($entryId) === 'pre_approval') {
                // エントリーが承認前ステータスのときは、ロール及び通常の権限に従う
                if (roleAvailableUser()) {
                    return $this->canDeleteByRole($blogId, $entryId);
                }

                return $this->canDeleteByDefault($blogId, $entryId);
            }
        }

        return false;
    }

    /**
     * ロールが適用されたログインユーザーがエントリーを削除できるかどうかを判定する
     *
     * @param int $blogId
     * @param int|null $entryId
     * @return bool
     */
    private function canDeleteByRole(int $blogId, ?int $entryId = null): bool
    {
        if (!roleAvailableUser()) {
            throw new \BadMethodCallException('ロール機能が適用されているユーザーではありません。');
        }

        if (roleAuthorization('entry_delete', $blogId, $entryId)) {
            return true;
        }
        return false;
    }

    /**
     * ログインユーザーがエントリーを削除できるかどうかをエントリー毎に判定する
     *
     * @param int $blogId
     * @param int|null $entryId
     * @return bool
     */
    private function canDeleteByDefault(int $blogId, ?int $entryId = null): bool
    {
        if (!Auth::isControlBlog($blogId)) {
            // ブログに権限がなければ削除不可
            return false;
        }

        if (sessionWithCompilation($blogId)) {
            // 編集者以上の場合は削除可能
            return true;
        }
        if (
            $entryId !== null && $entryId > 0 &&
            sessionWithContribution() &&
            SUID == ACMS_RAM::entryUser($entryId)
        ) {
            // 投稿者の場合でも、エントリーの所有ユーザーの場合は削除可能
            return true;
        }

        return false;
    }

    /**
     * ログインユーザーがゴミ箱から全てのエントリーを削除できるかどうかを判定する
     * @param int $blogId
     * @param int|null $categoryId
     * @return bool
     */
    public function canDeleteAllFromTrash(int $blogId, ?int $categoryId = null): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (enableApproval($blogId, $categoryId)) {
            // 承認機能が有効な場合
            if (sessionWithApprovalAdministrator($blogId, $categoryId)) {
                // 最終承認者の場合は削除可能
                return true;
            }

            return false;
        }
        if (roleAvailableUser()) {
            if (roleAuthorization('admin_etc', $blogId)) {
                return true;
            };

            return false;
        }

        if (sessionWithAdministration($blogId)) {
            return true;
        }

        return false;
    }

    /**
     * 現在のログインユーザーがエントリーをゴミ箱から復元可能かどうかを判定する
     *
     * @param int $entryId
     * @return bool
    */
    public function canTrashRestore(int $entryId): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }

        $blogId = ACMS_RAM::entryBlog($entryId);
        $categoryId = ACMS_RAM::entryCategory($entryId);
        if (enableApproval($blogId, $categoryId)) {
            return $this->canTrashRestoreByApproval($blogId, $categoryId, $entryId);
        }

        if (roleAvailableUser()) {
            return $this->canTrashRestoreByRole($blogId, $entryId);
        }

        return $this->canTrashRestoreByDefault($blogId, $entryId);
    }

    /**
     * 現在のログインユーザーがエントリーをゴミ箱から一括で復元可能かどうかを判定する
     *
     * @param int $blogId
     * @param int|null $categoryId
     * @return bool
    */
    public function canBulkTrashRestore(int $blogId, ?int $categoryId = null): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (enableApproval($blogId, $categoryId)) {
            return $this->canTrashRestoreByApproval($blogId, $categoryId);
        }

        if (roleAvailableUser()) {
            return $this->canTrashRestoreByRole($blogId);
        }

        return $this->canTrashRestoreByDefault($blogId);
    }

    /**
     * 承認機能有効時にログインユーザーがエントリーをゴミ箱から復元可能かどうかを判定する
     *
     * @param int $blogId
     * @param int|null $categoryId
     * @param int|null $entryId
     * @return bool
     */
    private function canTrashRestoreByApproval(int $blogId, ?int $categoryId = null, ?int $entryId = null): bool
    {
        if (!enableApproval($blogId, $categoryId)) {
            throw new \BadMethodCallException('承認機能が無効です');
        }

        if (config('approval_contributor_edit_auth') === 'on') {
            // 投稿者が自身が投稿した記事のみ編集できる設定が有効な場合はロール及び通常の権限に従う
            if (roleAvailableUser()) {
                return $this->canTrashRestoreByRole($blogId, $entryId);
            }

            return $this->canTrashRestoreByDefault($blogId, $entryId);
        }


        if (sessionWithApprovalAdministrator($blogId, $categoryId)) {
            // 最終承認者またはルートブログの管理者の場合は復元可能
            return true;
        }

        if ($entryId !== null && $entryId > 0) {
            // エントリー個別の場合は、ロール及び通常の権限に従う
            if (roleAvailableUser()) {
                return $this->canTrashRestoreByRole($blogId, $entryId);
            }

            return $this->canTrashRestoreByDefault($blogId, $entryId);
        }

        return false;
    }

    /**
     * ロールが適用されたログインユーザーがエントリーをゴミ箱から復元可能かどうかを判定する
     *
     * @param int $blogId
     * @param int|null $entryId
     * @return bool
     */
    private function canTrashRestoreByRole(int $blogId, ?int $entryId = null): bool
    {
        if (!roleAvailableUser()) {
            throw new \BadMethodCallException('ロール機能が適用されているユーザーではありません。');
        }
        if ($this->canDeleteByRole($blogId, $entryId)) {
            // 削除可能な場合は復元可能
            return true;
        }
        return false;
    }

    /**
     * ログインユーザーがエントリーをゴミ箱から復元できるかどうかをエントリー毎に判定する
     *
     * @param int $blogId
     * @param int|null $entryId
     * @return bool
     */
    private function canTrashRestoreByDefault(int $blogId, ?int $entryId = null): bool
    {
        if ($this->canDeleteByDefault($blogId, $entryId)) {
            // 削除可能な場合は復元可能
            return true;
        }

        return false;
    }

    /**
     * 現在のログインユーザーがエントリーの表示順を変更可能かどうかを判定する
     * @param 'entry' | 'category' | 'user' $type
     * @param int $blogId
     * @return bool
     */
    public function canChangeOrder(string $type, int $blogId): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }

        if ($type === 'user') {
            return sessionWithContribution($blogId);
        }

        if (roleAvailableUser()) {
            return roleAuthorization('entry_edit_all', $blogId);
        }

        return sessionWithCompilation($blogId);
    }

    /**
     * 現在のログインユーザーが自分以外のユーザーで絞り込んだエントリーの表示順を変更可能かどうかを判定する
     * @param int $blogId
     * @return bool
     */
    public function canChangeOrderByOtherUser(int $blogId): bool
    {
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        if (is_null($sessionUserId)) {
            // ログインしていない場合は変更できない
            return false;
        }
        if (Preview::isPreviewMode()) {
            // プレビューモードは変更できない
            return false;
        }
        if (!$this->canChangeOrder('user', $blogId)) {
            // そもそもユーザーで絞り込んだ場合の表示順を変更できる権限がない場合は変更できない
            return false;
        }

        if (sessionWithCompilation($blogId)) {
            // 編集者以上の場合は変更できる
            return true;
        }

        if (roleAvailableUser()) {
            if (roleAuthorization('entry_edit_all', $blogId)) {
                // 全エントリーの編集権限がある場合は変更できる
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * 現在のログインユーザーがエントリーステータス一括で変更可能かどうかを判定する
     * @param int $blogId
     * @param int|null $categoryId
     * @return bool
     */
    public function canBulkStatusChange(int $blogId, ?int $categoryId = null): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (config('approval_contributor_edit_auth') !== 'on' && enableApproval($blogId, $categoryId)) {
            return sessionWithApprovalAdministrator($blogId, $categoryId);
        }
        if (roleAvailableUser()) {
            return roleAuthorization('entry_edit', $blogId);
        }
        return sessionWithCompilation($blogId);
    }

    /**
     * 現在のログインユーザーがエントリーの所有ユーザーを一括で変更可能かどうかを判定する
     * @param int $blogId
     * @param int|null $categoryId
     * @return bool
     */
    public function canBulkUserChange(int $blogId, ?int $categoryId = null): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (enableApproval($blogId, $categoryId)) {
            return sessionWithApprovalAdministrator($blogId, $categoryId);
        }
        if (roleAvailableUser()) {
            return roleAuthorization('entry_edit', $blogId);
        }
        return sessionWithCompilation($blogId);
    }

    /**
     * 現在のログインユーザーがエントリーのカテゴリーを一括で変更可能かどうかを判定する
     * @param int $blogId
     * @param int|null $categoryId
     * @return bool
     */
    public function canBulkCategoryChange(int $blogId, ?int $categoryId = null): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (enableApproval($blogId, $categoryId)) {
            return sessionWithApprovalAdministrator($blogId, $categoryId);
        }
        if (roleAvailableUser()) {
            return roleAuthorization('entry_edit', $blogId);
        }
        // 投稿者以上の場合は変更可能（投稿者の場合は自分のエントリーのみ変更可能）
        return sessionWithContribution($blogId);
    }

    /**
     * 現在のログインユーザーがエントリーの所属ブログを一括で変更可能かどうかを判定する
     * @param int $blogId
     * @return bool
     */
    public function canBulkBlogChange(int $blogId): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (enableApproval($blogId, null)) {
            return sessionWithApprovalAdministrator($blogId, null);
        }
        if (roleAvailableUser()) {
            return roleAuthorization('admin_etc', $blogId);
        }
        return sessionWithAdministration($blogId);
    }

    /**
     * 現在のログインユーザーがエントリーの承認履歴を閲覧可能かどうかを判定する
     * @param int $entryId
     * @return bool
     */
    public function canViewApprovalHistory(int $entryId): bool
    {
        $blogId = ACMS_RAM::entryBlog($entryId);
        $categoryId = ACMS_RAM::entryCategory($entryId);

        if (!enableApproval($blogId, $categoryId)) {
            return false;
        }

        if (!sessionWithApprovalAdministrator($blogId, $categoryId)) {
            return false;
        }
        return true;
    }

    /**
     * 現在のログインユーザーがエントリーの複製が可能かどうかを判定する
     * @param int $entryId
     * @return bool
     */
    public function canDuplicate(int $entryId): bool
    {
        $blogId = ACMS_RAM::entryBlog($entryId);
        if (roleAvailableUser()) {
            if (roleAuthorization('entry_edit', $blogId, $entryId)) {
                return true;
            }
            return false;
        }
        if (sessionWithCompilation($blogId)) {
            // 編集者以上の場合は削除可能
            return true;
        }
        if (
            sessionWithContribution() &&
            SUID == ACMS_RAM::entryUser($entryId)
        ) {
            // 投稿者の場合でも、エントリーの所有ユーザーの場合は削除可能
            return true;
        }
        return false;
    }

    /**
     * 現在のログインユーザーが指定したブログでエントリーの一括複製が可能かどうかを判定する
     * @param int $blogId
     * @return bool
     */
    public function canBulkDuplicate(int $blogId): bool
    {
        if (sessionWithCompilation($blogId)) {
            return true;
        }
        return false;
    }

    /**
     * 現在のログインユーザーが指定したブログでエントリーのエクスポートが可能かどうかを判定する
     * @param int $blogId
     * @return bool
     */
    public function canExport(int $blogId): bool
    {
        if (sessionWithCompilation($blogId)) {
            return true;
        }
        return false;
    }

    /*
     * 現在のログインユーザーがエントリーの更新権限を持っているかどうかを判定する
     *
     * @param int $eid
     * @param int $bid
     * @param int|null $cid
     * @param int|null $rvid
     * @return boolean
     */
    public function canUpdate(int $eid, int $bid, ?int $cid = null, ?int $rvid = null): bool
    {
        if ($eid <= 0) {
            return false;
        }
        if (!$this->canEditView($eid, $bid, $cid)) {
            return false;
        }
        if (enableRevision() && $rvid && $rvid > 1) {
            if ($this->isNewVersion()) {
                return true;
            }
            $currentEntry = ACMS_RAM::entry($eid);
            if (intval($currentEntry['entry_current_rev_id']) === $rvid && !sessionWithApprovalAdministrator($bid, $cid)) {
                return false;
            }
            $sql = SQL::newSelect('entry_rev');
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_rev_id', $rvid);
            $q = $sql->get(dsn());
            $revision = DB::query($q, 'row');
            if ($revision) {
                if (intval($revision['entry_rev_user_id']) !== SUID && !sessionWithApprovalAdministrator($bid, $cid)) { // @phpstan-ignore-line
                    return false;
                }
                if (enableApproval($bid, $cid) && !sessionWithApprovalAdministrator($bid, $cid)) {
                    if ($revision['entry_rev_status'] === 'approved') {
                        // 承認済みバージョンなので変更不可
                        return false;
                    }
                    if ($revision['entry_rev_status'] === 'reject') {
                        // 承認却下バージョンなので変更不可
                        return false;
                    }
                    if ($revision['entry_rev_status'] === 'trash') {
                        // 削除依頼バージョンなので変更不可
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * 現在のログインユーザーがエントリーの編集画面の閲覧権限を持っているかどうかを判定する
     *
     * @param int $eid
     * @param int $bid
     * @param int|null $cid
     * @return boolean
     */
    public function canEditView(int $eid, int $bid, ?int $cid = null): bool
    {
        if ($eid <= 0) {
            return false;
        }
        if (roleAvailableUser()) {
            if (!roleAuthorization('entry_edit', $bid, $eid)) {
                return false;
            }
        } else {
            if (!sessionWithCompilation($bid)) {
                if (!sessionWithContribution($bid)) {
                    return false;
                }
                if (SUID !== ACMS_RAM::entryUser($eid) && (config('approval_contributor_edit_auth') === 'on' || !enableApproval($bid, $cid))) { // @phpstan-ignore-line
                    return false;
                }
            }
        }
        return true;
    }
}
