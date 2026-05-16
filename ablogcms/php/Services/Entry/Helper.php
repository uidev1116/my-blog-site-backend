<?php

namespace Acms\Services\Entry;

use Acms\Services\Entry\Enums\EntryApprovalStatus;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Preview;
use Acms\Services\Facades\Auth;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Entry;
use Acms\Services\Entry\Exceptions\TagValidationException;
use Acms\Services\Entry\Exceptions\SubCategoryValidationException;
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
     * @var int|null
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
     * @return int|null
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
     * @param string $fieldName
     * @return \Field_Validation
     */
    public function validTag($Entry, string $fieldName = 'tag')
    {
        $tags = $Entry->get($fieldName);
        if ($tags !== '') {
            $tags = Common::getTagsFromString($tags, false);
            try {
                $this->validateTagNames($tags);
            } catch (TagValidationException $e) {
                $errors = $e->getErrors();
                foreach ($errors as $error) {
                    if ($error['type'] === 'reserved') {
                        $Entry->setMethod($fieldName, 'reserved', false);
                    } elseif ($error['type'] === 'invalid_format') {
                        $Entry->setMethod($fieldName, 'string', false);
                    }
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
    public function validSubCategory($Entry, string $fieldName = 'sub_category_id')
    {
        $subCategoryIds = $this->getSubCategoryFromString($Entry->get($fieldName), ',');
        if (count($subCategoryIds) > 0) {
            try {
                $this->validateSubCategoryIds($subCategoryIds);
            } catch (SubCategoryValidationException $e) {
                $errors = $e->getErrors();
                foreach ($errors as $error) {
                    if ($error['type'] === 'limit_exceeded') {
                        $Entry->setMethod($fieldName, 'max_sub_category_id', false);
                    }
                }
            }
        }
        return $Entry;
    }

    /**
     * タグ名の配列をバリデート
     *
     * Field オブジェクトを使わずにタグ名の配列を直接バリデートする
     * すべてのタグをチェックし、複数のエラーを一度に検知する
     *
     * @param array<int, string> $tags タグ名の配列
     * @return void
     * @throws TagValidationException タグのバリデーションエラーがある場合（複数のエラーをまとめて保持）
     */
    public function validateTagNames(array $tags): void
    {
        $errors = [];

        foreach ($tags as $index => $tag) {
            // 予約語チェック
            if (isReserved($tag)) {
                $errors[] = [
                    'tag' => $tag,
                    'index' => $index,
                    'type' => 'reserved',
                    'message' => 'タグ名に予約語が使用されています（entry_tag: ' . $tag . '）。別のタグ名を指定してください。',
                ];
            }

            // 形式チェック
            if (!preg_match(REGEX_INVALID_TAG_NAME, $tag)) {
                $errors[] = [
                    'tag' => $tag,
                    'index' => $index,
                    'type' => 'invalid_format',
                    'message' => 'タグ名の形式が正しくありません（entry_tag: ' . $tag . '）。#, / を含むことはできません。',
                ];
            }
        }

        if (count($errors) > 0) {
            throw new TagValidationException($errors);
        }
    }

    /**
     * サブカテゴリーIDの配列をバリデート
     *
     * Field オブジェクトを使わずにサブカテゴリーIDの配列を直接バリデートする
     * すべてのサブカテゴリーをチェックし、複数のエラーを一度に検知する
     *
     * @param array<int> $subCategoryIds サブカテゴリーIDの配列
     * @return void
     * @throws SubCategoryValidationException サブカテゴリーのバリデーションエラーがある場合（複数のエラーをまとめて保持）
     */
    public function validateSubCategoryIds(array $subCategoryIds): void
    {
        $errors = [];

        // 上限チェック
        $limit = config('entry_edit_sub_category_limit');
        if (is_numeric($limit)) {
            $count = count($subCategoryIds);
            $limitInt = intval($limit);
            if ($count > $limitInt) {
                $errors[] = [
                    'type' => 'limit_exceeded',
                    'message' => 'サブカテゴリーの数が上限を超えています（entry_sub_category）。最大 ' . $limitInt . ' 個まで指定できます。現在: ' . $count . ' 個',
                    'limit' => $limitInt,
                    'count' => $count,
                ];
            }
        }

        if (count($errors) > 0) {
            throw new SubCategoryValidationException($errors);
        }
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
            // changeRevision 経路: column_rev にも参照がない孤児ファイルのみ物理削除する
            $unitRepository->removeUnitsWithReferenceCheck($eid, null);
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
     * 予約公開バージョンの登録
     *
     * @param int $rvid
     * @param int $eid
     *
     * @return void
     */
    public function reserveRevision($rvid, $eid): void
    {
        $sql = SQL::newUpdate('entry');
        $sql->setUpdate('entry_reserve_rev_id', $rvid);
        // 承認フロー完了のため承認ステータスをクリアする。
        // フロント側の表示制御は entry_current_rev_id / entry_reserve_rev_id の組み合わせで行う。
        $sql->addUpdate('entry_approval', EntryApprovalStatus::None->value);
        $sql->addWhereOpr('entry_id', $eid);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * バージョンの切り替え（即時公開）
     *
     * @param int $rvid
     * @param int $eid
     * @param int $bid
     *
     * @return void
     */
    public function changeRevision($rvid, $eid, $bid): void
    {
        $revision = $this->getRevision($eid, $rvid);
        if ($revision === false) {
            return;
        }

        // エントリの情報を削除
        $this->entryDelete($eid, true);

        $entryInsertSql = SQL::newInsert('entry');
        foreach ($revision as $key => $val) {
            if (!preg_match('@^(entry_rev|entry_approval)@', $key)) {
                $entryInsertSql->addInsert($key, $val);
            }
        }
        $entryInsertSql->addInsert('entry_current_rev_id', $rvid);
        $entryInsertSql->addInsert('entry_reserve_rev_id', 0);
        if (Login::isLoggedIn()) {
            /** @var int|null $sessionUserId */
            $sessionUserId = SUID;
            assert(is_int($sessionUserId)); // ログインしていることが保証されている
            $entryInsertSql->addInsert('entry_last_update_user_id', $sessionUserId);
        }
        DB::query($entryInsertSql->get(dsn()), 'exec');

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
            DB::query($primaryImageUpdateSql->get(dsn()), 'exec');
        }
        ACMS_RAM::entry($eid, null);

        //-------
        // field
        $field = loadEntryField($eid, $rvid);
        Common::saveField('eid', $eid, $field);

        //-------
        // tag
        $tagRevSql = SQL::newSelect('tag_rev');
        $tagRevSql->addWhereOpr('tag_entry_id', $eid);
        $tagRevSql->addWhereOpr('tag_rev_id', $rvid);
        $q = $tagRevSql->get(dsn());
        $tagRevStatement = DB::query($q, 'exec');

        $tagInsertSql = SQL::newBulkInsert('tag');
        if ($tagRevStatement && ($row = DB::next($tagRevStatement))) {
            do {
                unset($row['tag_rev_id']);
                $tagInsertSql->addInsert($row);
            } while ($row = DB::next($tagRevStatement));
        }
        if ($tagInsertSql->hasData()) {
            DB::query($tagInsertSql->get(dsn()), 'exec');
        }

        //---------------
        // sub category
        $subCategoryDeleteSql = SQL::newDelete('entry_sub_category');
        $subCategoryDeleteSql->addWhereOpr('entry_sub_category_eid', $eid);
        DB::query($subCategoryDeleteSql->get(dsn()), 'exec');

        $subCategoryRevSql = SQL::newSelect('entry_sub_category_rev');
        $subCategoryRevSql->addWhereOpr('entry_sub_category_eid', $eid);
        $subCategoryRevSql->addWhereOpr('entry_sub_category_rev_id', $rvid);
        $q = $subCategoryRevSql->get(dsn());
        $subCategoryRevStatement = DB::query($q, 'exec');

        $subCategoryInsertSql = SQL::newBulkInsert('entry_sub_category');
        if ($subCategoryRevStatement && ($row = DB::next($subCategoryRevStatement))) {
            do {
                unset($row['entry_sub_category_rev_id']);
                $subCategoryInsertSql->addInsert($row);
            } while ($row = DB::next($subCategoryRevStatement));
        }
        if ($subCategoryInsertSql->hasData()) {
            DB::query($subCategoryInsertSql->get(dsn()), 'exec');
        }

        //---------------
        // related entry
        $relationshipRevSql = SQL::newSelect('relationship_rev');
        $relationshipRevSql->addWhereOpr('relation_id', $eid);
        $relationshipRevSql->addWhereOpr('relation_rev_id', $rvid);
        $relations = DB::query($relationshipRevSql->get(dsn()), 'all');

        $relationshipInsertSql = SQL::newBulkInsert('relationship');
        foreach ($relations as $relation) {
            $relationshipInsertSql->addInsert([
                'relation_id' => $eid,
                'relation_eid' => $relation['relation_eid'],
                'relation_type' => $relation['relation_type'],
                'relation_order' => $relation['relation_order'],
            ]);
        }
        if ($relationshipInsertSql->hasData()) {
            DB::query($relationshipInsertSql->get(dsn()), 'exec');
        }

        //----------
        // fulltext
        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
    }

    /**
     * 現時点で公開可能な予約公開バージョンを取得
     *
     * @return array<int, array<string, mixed>>
     */
    public function findPublishableReservedRevisions(): array
    {
        $revisionAliasName = 'revision';
        $entryAliasName = 'entry';

        // entry_rev の主キーは (entry_id, entry_rev_id) なので、entry_rev_id だけで JOIN すると
        // 同番リビジョンを持つ別エントリーまで巻き込まれる。entry_id 一致条件を ON に加える。
        $joinWhere = SQL::newWhere();
        $joinWhere->addWhereOpr(
            'entry_id',
            SQL::newField('entry_id', $entryAliasName),
            '=',
            'AND',
            $revisionAliasName
        );

        $reservedRevisionsSql = SQL::newSelect('entry_rev', $revisionAliasName);
        $reservedRevisionsSql->addSelect('*', null, $revisionAliasName);
        $reservedRevisionsSql->addInnerJoin(
            'entry',
            'entry_reserve_rev_id',
            'entry_rev_id',
            $entryAliasName,
            $revisionAliasName,
            $joinWhere
        );
        // 予約があるエントリーに絞り込み、entry_reserve_filter インデックスのレンジスキャンを誘導する。
        // addWhereOpr で数値を渡すと左辺が "(col + 0)" にラップされて索引が無効化されるため、
        // 右辺を quote=false な SQL_Field として埋め込む（既存ビルダ慣習）。
        // entry_reserve_rev_id は int(11) NOT NULL DEFAULT 0 で、有効な予約 ID は必ず 1 以上なので
        // "> 0" を意味的に等価な ">= 1" に置き換えている。
        $reservedRevisionsSql->addWhereOpr(
            'entry_reserve_rev_id',
            SQL::newField('1', null, false),
            '>=',
            'AND',
            $entryAliasName
        );
        $reservedRevisionsSql->addWhereOpr('entry_start_datetime', date('Y-m-d H:i:s', REQUEST_TIME), '<', 'AND', $revisionAliasName);
        $reservedRevisions = DB::query($reservedRevisionsSql->get(dsn()), 'all');
        return $reservedRevisions;
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
            $table = 'entry_sub_category';
            if (!empty($rvid)) {
                $table = 'entry_sub_category_rev';
            }
            $SQL = SQL::newDelete($table);
            $SQL->addWhereOpr('entry_sub_category_eid', $eid);
            if (!empty($rvid)) {
                $SQL->addWhereOpr('entry_sub_category_rev_id', $rvid);
            }
            DB::query($SQL->get(dsn()), 'exec');

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
                DB::query($insert->get(dsn()), 'exec');
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * @param string $string
     * @param string $delimiter
     * @return int<1, max>[]
     */
    public function getSubCategoryFromString(string $string, string $delimiter = ','): array
    {
        $delimiter = $delimiter ? $delimiter : ',';
        $cidAry = explode($delimiter, $string);
        $list = [];
        foreach ($cidAry as $item) {
            $item = preg_replace('/^[\s　]+|[\s　]+$/u', '', $item);
            if ($item !== '' && $item !== null) {
                $list[] = $item;
            }
        }
        $list = array_map('trim', $list);
        $list = array_map('intval', $list);
        $list = array_filter($list, function ($item) {
            return $item > 0;
        });
        return $list;
    }

    /**
     * 関連エントリーのサムネイルフィールドを解決する。
     * 指定されたフィールドが空の場合は main_image_field_name にフォールバックする。
     *
     * @param string|null $thumbnailField サムネイルフィールド名（空の場合はフォールバック）
     * @return string 解決されたフィールド名（空の場合は unit ターゲットを使用することを示す）
     */
    public function resolveRelatedEntryThumbnailField(?string $thumbnailField): string
    {
        if ($thumbnailField !== null && $thumbnailField !== '') {
            return $thumbnailField;
        }

        return config('main_image_field_name', '');
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
        DB::query($SQL->get(dsn()), 'exec');

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
            DB::query($insert->get(dsn()), 'exec');
        }
    }

    /**
     * エントリーのバージョンを保存
     *
     * @param int $eid
     * @param int|null $rvid
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

        Common::saveField('eid', $eid, $Field, null, $rvid, BID);

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
     * @return array<string, mixed>|false
     */
    public function getRevision($eid, $rvid)
    {
        $sql = SQL::newSelect('entry_rev');
        $sql->addWhereOpr('entry_id', $eid);
        $sql->addWhereOpr('entry_rev_id', $rvid);

        return DB::query($sql->get(dsn()), 'row');
    }

    /**
     * 現行リビジョンIDと予約リビジョンIDを1クエリで取得する。
     *
     * @param int $eid
     * @return array{current: int, reserve: int}
     */
    public function getRevisionIds(int $eid): array
    {
        $sql = SQL::newSelect('entry');
        $sql->addSelect('entry_current_rev_id');
        $sql->addSelect('entry_reserve_rev_id');
        $sql->addWhereOpr('entry_id', $eid);
        $row = DB::query($sql->get(dsn()), 'row');
        return [
            'current' => intval($row['entry_current_rev_id'] ?? 0),
            'reserve' => intval($row['entry_reserve_rev_id'] ?? 0),
        ];
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

        // 承認前エントリーはリビジョン1（作業領域）に変更内容が保存されており、
        // メインエントリーをダイレクト編集で書き換えると作業領域と乖離するため不可とする。
        if ($entry['entry_approval'] === EntryApprovalStatus::PreApproval->value) {
            return false;
        }

        if ($this->requiresApproval(BID, CID)) {
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
            // 承認前エントリーはまだ公開されておらず、承認フローの途中であるため、
            // 承認管理者の特権ではなくロール・通常の権限に基づいて削除可否を判定する。
            if (ACMS_RAM::entryApproval($entryId) === EntryApprovalStatus::PreApproval->value) {
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
     * 現在のログインユーザーがエントリー保存時に承認フローを経る必要があるかどうかを判定する。
     *
     * 承認機能が有効かつ承認管理者でない場合に true を返す。
     * 承認管理者はフローをバイパスして即時公開できるため false となる。
     *
     * @param int $bid ブログID
     * @param int|null $cid カテゴリID
     * @return bool
     */
    public function requiresApproval(int $bid, ?int $cid): bool
    {
        return enableApproval($bid, $cid) && !sessionWithApprovalAdministrator($bid, $cid);
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
            // 承認機能が無効な場合は承認履歴は存在しないため閲覧不可
            return false;
        }

        if (!sessionWithApprovalAdministrator($blogId, $categoryId)) {
            // 承認管理者でない場合は承認履歴を閲覧不可
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
     * 現在のログインユーザーが指定したエントリーリビジョンを削除可能かどうかを判定する
     *
     * 承認機能有効時は承認管理者のみ許可。承認機能無効時は `sessionWithCompilation`
     * またはエントリー所有者の投稿者のみ許可。作業領域 (rvid=1)、公開中のリビジョン、
     * 公開予約中のリビジョンは削除不可。
     *
     * @param int $entryId
     * @param int $revisionId
     * @return bool
     */
    public function canDeleteEntryRevision(int $entryId, int $revisionId): bool
    {
        if (Preview::isPreviewMode()) {
            // プレビューモード中はリビジョンの削除を行わない
            return false;
        }
        if (!enableRevision()) {
            // リビジョン機能が無効な場合は削除不可
            return false;
        }
        if ($entryId <= 0 || $revisionId <= 1) {
            // 作業領域 (rvid=1) は削除不可、不正なIDも弾く
            return false;
        }

        $entry = ACMS_RAM::entry($entryId);
        if (!is_array($entry) || $entry === []) {
            // 対象エントリーが存在しない場合は削除不可
            return false;
        }

        $blogId = intval($entry['entry_blog_id']);
        $categoryId = ACMS_RAM::entryCategory($entryId);

        if (intval($entry['entry_current_rev_id']) === $revisionId) {
            // 公開中のリビジョンは削除不可
            return false;
        }
        if (intval($entry['entry_reserve_rev_id']) === $revisionId) {
            // 公開予約中のリビジョンは削除不可
            return false;
        }

        $revision = Entry::getRevision($entryId, $revisionId);
        if (!is_array($revision) || $revision === []) {
            // 対象リビジョンが存在しない場合は削除不可
            return false;
        }

        if (enableApproval($blogId, $categoryId)) {
            // 承認機能が有効な場合は、承認フローをバイパスできる承認管理者のみ削除可能
            // (requiresApproval が false となるのは承認管理者のケース)
            return !$this->requiresApproval($blogId, $categoryId);
        }

        if (roleAvailableUser()) {
            // ロール機能が適用されているユーザーはロールの権限に従う
            return roleAuthorization('entry_edit', $blogId, $entryId);
        }

        if (sessionWithCompilation($blogId)) {
            // 編集者以上の場合は削除可能
            return true;
        }

        if (
            sessionWithContribution($blogId) &&
            intval(SUID) === intval(ACMS_RAM::entryUser($entryId))
        ) {
            // 投稿者の場合でも、エントリーの所有ユーザーの場合は削除可能
            return true;
        }

        return false;
    }

    /**
     * 現在のログインユーザーが指定したエントリーのリビジョンを複製可能かどうかを判定する
     *
     * 本機能は「作業領域から承認依頼」ボタンからのみ実行される承認機能専用の動線であり、
     * 承認フローを経由する必要があるユーザー（= requiresApproval が true）のみ許可する。
     *
     * @param int $entryId
     * @return bool
     */
    public function canDuplicateEntryRevision(int $entryId): bool
    {
        if (Preview::isPreviewMode()) {
            // プレビューモード中はリビジョンの複製を行わない
            return false;
        }
        if (!enableRevision()) {
            // リビジョン機能が無効な場合は複製不可
            return false;
        }
        if ($entryId <= 0) {
            return false;
        }

        $entry = ACMS_RAM::entry($entryId);
        if (!is_array($entry) || $entry === []) {
            // 対象エントリーが存在しない場合は複製不可
            return false;
        }

        $blogId = intval($entry['entry_blog_id']);
        $categoryId = ACMS_RAM::entryCategory($entryId);

        if (!enableApproval($blogId, $categoryId)) {
            // 承認機能専用の動線のため、承認機能無効時は複製不可
            return false;
        }

        if (!$this->requiresApproval($blogId, $categoryId)) {
            // 承認管理者は「作業領域から承認依頼」の対象外
            return false;
        }

        if (roleAvailableUser()) {
            // ロール機能が適用されているユーザーはロールの権限に従う
            return roleAuthorization('entry_edit', $blogId, $entryId);
        }

        if (sessionWithContribution($blogId)) {
            // 承認フローを経由する投稿者権限以上のユーザーは作業領域から承認依頼可能
            return true;
        }

        return false;
    }

    /**
     * 現在のログインユーザーが指定したエントリーのリビジョンを公開バージョンへ切り替え可能かどうかを判定する
     *
     * 作業領域 (rvid=1)、公開中バージョン、公開予約中バージョンは切り替え対象外。
     * 承認機能有効時は、対象リビジョンが承認済み (approved) かつ承認管理者のみ許可。
     * 承認機能無効時は、ロールの権限、編集者以上、または投稿者本人 (エントリー所有者) のみ許可。
     *
     * @param int $entryId
     * @param int $revisionId
     * @return bool
     */
    public function canChangeEntryRevision(int $entryId, int $revisionId): bool
    {
        if (Preview::isPreviewMode()) {
            // プレビューモード中はリビジョンの切り替えを行わない
            return false;
        }
        if (!enableRevision()) {
            // リビジョン機能が無効な場合は切り替え不可
            return false;
        }
        if ($entryId <= 0 || $revisionId <= 1) {
            // 作業領域 (rvid=1) は切り替え対象外、不正なIDも弾く
            return false;
        }

        $entry = ACMS_RAM::entry($entryId);
        if (!is_array($entry) || $entry === []) {
            // 対象エントリーが存在しない場合は切り替え不可
            return false;
        }

        $blogId = intval($entry['entry_blog_id']);
        $categoryId = ACMS_RAM::entryCategory($entryId);

        if (intval($entry['entry_current_rev_id']) === $revisionId) {
            // 既に公開中のリビジョンは切り替え不要
            return false;
        }
        if (intval($entry['entry_reserve_rev_id']) === $revisionId) {
            // 既に公開予約中のリビジョンは重複予約を防止するため切り替え不可
            return false;
        }

        $revision = Entry::getRevision($entryId, $revisionId);
        if (!is_array($revision) || $revision === []) {
            // 対象リビジョンが存在しない場合は切り替え不可
            return false;
        }

        if (enableApproval($blogId, $categoryId)) {
            // 承認機能有効時は承認済みのリビジョンのみ、かつ承認管理者のみ切り替え可能
            if (($revision['entry_rev_status'] ?? '') !== 'approved') {
                return false;
            }
            return sessionWithApprovalAdministrator($blogId, $categoryId);
        }

        if (roleAvailableUser()) {
            // ロール機能が適用されているユーザーはロールの権限に従う
            return roleAuthorization('entry_edit', $blogId, $entryId);
        }

        if (sessionWithCompilation($blogId)) {
            // 編集者以上の場合は切り替え可能
            return true;
        }

        if (
            sessionWithContribution($blogId) &&
            intval(SUID) === intval(ACMS_RAM::entryUser($entryId))
        ) {
            // 投稿者でもエントリー所有者なら切り替え可能
            return true;
        }

        return false;
    }

    /**
     * 現在のログインユーザーが指定したエントリーリビジョンの編集画面 (admin=entry_editor) を
     * 開いて更新可能な状態かどうかを判定する
     *
     * @param int $entryId
     * @param int $revisionId
     * @return bool
     */
    public function canUpdateEntryRevision(int $entryId, int $revisionId): bool
    {
        if (!enableRevision()) {
            return false;
        }
        if ($entryId <= 0 || $revisionId <= 0) {
            return false;
        }

        $entry = ACMS_RAM::entry($entryId);
        if (!is_array($entry) || $entry === []) {
            return false;
        }
        $blogId = intval($entry['entry_blog_id']);
        $categoryId = ACMS_RAM::entryCategory($entryId);

        $revision = $this->getRevision($entryId, $revisionId);
        if (!is_array($revision) || $revision === []) {
            return false;
        }

        return $this->canEditView($entryId, $blogId, $categoryId);
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
                if ($this->requiresApproval($bid, $cid)) {
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

    /**
     * エントリーコードに拡張子を追加
     *
     * 既に拡張子が含まれている場合はそのまま返し、含まれていない場合は拡張子を追加する
     *
     * @param non-empty-string $code 拡張子を含まない可能性のあるエントリーコード
     * @return non-empty-string 拡張子が追加されたエントリーコード
     */
    public function formatEntryCode(string $code): string
    {
        // 既に拡張子が含まれている場合はそのまま返す
        if (preg_match('@\.([^\.]+)$@', $code)) {
            return $code;
        }

        // 拡張子を追加
        $extension = $this->getEntryCodeExtension();
        return $code . $extension;
    }

    /**
     * 完全なエントリーコードを生成
     *
     * プレフィックス + ID + 拡張子で完全なエントリーコードを生成する
     *
     * @param int $entryId エントリーID
     * @return non-empty-string 完全なエントリーコード（例: prefix123.html）
     */
    public function generateEntryCode(int $entryId): string
    {
        $prefix = config('entry_code_prefix');
        $extension = $this->getEntryCodeExtension();
        return $prefix . $entryId . $extension;
    }

    /**
     * タイトルまたはIDからエントリーコードを生成
     *
     * entry_code_title 設定に応じて、タイトルから生成するか、プレフィックス+IDで生成するかを決定する
     * 生成後、拡張子を追加して返す
     *
     * @param non-empty-string $title エントリータイトル
     * @param int $entryId エントリーID
     * @return non-empty-string 完全なエントリーコード（拡張子を含む）
     */
    public function generateEntryCodeFromTitleOrId(string $title, int $entryId): string
    {
        if (config('entry_code_title') === 'on') {
            $code = stripWhitespace($title);
            assert($code !== '');
            return $this->formatEntryCode($code);
        } else {
            return $this->generateEntryCode($entryId);
        }
    }

    /**
     * エントリーコードの拡張子を取得
     *
     * 設定から拡張子を取得し、先頭にドットを含む形式で返す
     * 設定が空の場合は空文字列を返す
     *
     * @return string 拡張子文字列（例: .html または ''）
     */
    public function getEntryCodeExtension(): string
    {
        $extension = config('entry_code_extension');
        return ($extension === '') ? '' : '.' . $extension;
    }
}
