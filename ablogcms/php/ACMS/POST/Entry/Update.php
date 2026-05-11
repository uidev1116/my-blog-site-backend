<?php

use Acms\Services\Entry\Enums\EntryApprovalStatus;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Common;

class ACMS_POST_Entry_Update extends ACMS_POST_Entry
{
    /**
     * 専用のカスタムフィールドを別テーブルに保存するためのフィールド名
     *
     * @var array
     */
    protected $fieldNames = [];

    /**
     * @var \Acms\Services\Entry\Lock
     */
    protected $lockService;

    /**
     * @var \Acms\Services\Unit\Repository $unitRepository
     */
    protected $unitRepository;


    /**
     * @var \Acms\Services\Entry\EntryRepository $entryRepository
     */
    protected $entryRepository;

    /**
     * constructor
     */
    public function __construct()
    {
        $this->unitRepository = Application::make('unit-repository');
        $this->lockService = Application::make('entry.lock');
        $this->entryRepository = Application::make('entry.repository');
    }

    /**
     * 専用のカスタムフィールドを別テーブルに保存する
     *
     * @param string $fieldName
     * @param int $eid
     * @param Field_Validation $Field
     * @return void
     */
    protected function saveCustomField($fieldName, $eid, $Field)
    {
    }

    /**
     * エントリーを更新
     *
     * @inheritDoc
     */
    public function post()
    {
        /** @var int<1, max>|null $entryId */
        $entryId = EID;
        if ($entryId === null) {
            throw new \LogicException('Entry ID is required.');
        }
        /** @var int<1, max>|null $revisionId */
        $revisionId = RVID;
        $updatedResponse = $this->update($entryId, $revisionId);
        $redirect = $this->Post->get('redirect');

        setCookieDelFlag();

        if (is_array($updatedResponse) && $redirect !== '' && Common::isSafeUrl($redirect)) {
            $this->responseRedirect($redirect);
        }

        if (is_array($updatedResponse)) {
            $info = [
                'bid' => BID,
                'cid' => $updatedResponse['cid'],
                'eid' => $entryId,
            ];
            if ($updatedResponse['status'] === 'trash') {
                $info['query'] = ['trash' => 'show'];
            }
            if (ADMIN === 'entry_editor') {
                $query = ['success' => $updatedResponse['success']];
                if ($updatedResponse['rvid']) {
                    $query['rvid'] = $updatedResponse['rvid'];
                }
                $redirect = acmsLink([
                    'bid' => BID,
                    'cid' => $updatedResponse['cid'],
                    'eid' => $entryId,
                    'admin' => 'entry_editor',
                    'query' => $query,
                ]);
                $this->responseRedirect($redirect);
            }
            $this->responseRedirect(acmsLink($info));
        }
        return $this->responseGet();
    }

    /**
     * エントリー更新
     *
     * @param int<1, max> $entryId
     * @param int<1, max>|null $revisionId
     * @return array{eid: int, cid: int|null, status: string, rvid: int|null, success: int}|false
     */
    public function update(int $entryId, ?int $revisionId = null)
    {
        ACMS_RAM::entry($entryId, null);

        $postEntry = $this->extract('entry');
        $this->preprocess($postEntry, $entryId);
        $customFieldCollection = [];
        $cid = (int)$postEntry->get('category_id');
        if ($cid === 0) {
            $cid = null;
        }

        $preEntry = ACMS_RAM::entry($entryId);
        if ($preEntry === null) {
            throw new \RuntimeException('Entry does not exist.');
        }
        $isUpdateableForMainEntry = $this->isUpdateableForMainEntry($preEntry, $postEntry, $revisionId); // メインエントリを更新するか判定
        $isNewVersion = $this->isNewVersion($postEntry); // 新規バージョンとして保存するか判定 $isNewVersionだったもの
        // 承認機能が有効かつ承認前でない（= 承認フロー完了済みまたは承認フロー不要）かを判定する。
        $isApproved = enableApproval() && $preEntry['entry_approval'] !== EntryApprovalStatus::PreApproval->value;

        if ($isNewVersion) {
            Entry::setNewVersion(true);
        }

        $this->validate($postEntry, $entryId, $revisionId); // バリデート

        // extract() / saveAssets() は旧ファイルの物理削除を即時実行しない。
        // 実際の削除はバリデーション通過後、field/column 保存時に参照チェック
        // （field/field_rev, column/column_rev 両方）を経て実行される。
        // これにより「作業領域のみ更新」「新バージョン保存」でメイン側が参照する
        // ファイルを誤って削除することが自動的に防がれる。
        $field = $this->extract('field', new ACMS_Validator()); // カスタムフィールドを事前処理
        foreach ($this->fieldNames as $fieldName) {
            $customFieldCollection[$fieldName] = $this->extract($fieldName, new ACMS_Validator());
        }

        $range = $this->getRange($postEntry);

        if (!$this->Post->isValidAll()) {
            $this->validateFailed($field, $range, $postEntry);

            AcmsLogger::info('「' . ACMS_RAM::entryTitle($entryId) . '」エントリーの更新に失敗しました', [
                'isUpdateableForMainEntry' => $isUpdateableForMainEntry,
                'isNewVersion' => $isNewVersion,
                'isApproved' => $isApproved,
                'Entry' => $postEntry,
            ]);
            return false;
        }

        $primaryImageUnitId = $postEntry->get('primary_image') !== '' ? $postEntry->get('primary_image') : null;
        ['collection' => $collection, 'range' => $range] = $this->unitRepository->extractUnits($range, $primaryImageUnitId); // ユニットの事前処理
        $this->unitRepository->saveAssets($collection);
        Entry::setSummaryRange($range);
        $entryData = $this->createUpdateEntryData(
            $entryId,
            $preEntry,
            $postEntry,
            Entry::getSummaryRange()
        ); // エントリーの事前処理

        /**
         * エントリーの保存
         */
        if ($isUpdateableForMainEntry) {
            $collection = $this->saveUnit($collection, $entryId); // ユニット（unitテーブル）を更新
            if (get_called_class() !== 'ACMS_POST_Entry_Update_Detail') {
                $primaryImageUnit = $collection->getPrimaryImageUnitOrFallback();
                $primaryImageId = $primaryImageUnit !== null ? $primaryImageUnit->getId() : null;
                $entryData['entry_primary_image'] = $primaryImageId;
            }
            $this->updateEntry($entryId, $entryData); // エントリ（entryテーブル）を更新
            $this->saveTag($entryId, $postEntry->get('tag')); // タグ（tagテーブル）を更新
            Entry::saveRelatedEntries($entryId, $postEntry->getArray('related'), null, $postEntry->getArray('related_type'), $postEntry->getArray('loaded_realted_entries')); // 関連エントリ（relationship）を更新
            Entry::saveSubCategory($entryId, $cid, $postEntry->get('sub_category_id')); // サブカテゴリー（entry_sub_category）を更新
            $this->saveGeometry('eid', $entryId, $this->extract('geometry')); // 位置情ィストリー（geo）を更新
            Common::saveField('eid', $entryId, $field); // フィールド（field）を更新
            foreach ($customFieldCollection as $fieldName => $customField) {
                $this->saveCustomField($fieldName, $entryId, $customField);
            }
            Common::saveFulltext('eid', $entryId, Common::loadEntryFulltext($entryId)); // フルテキスト（fulltext）を更新

            // 承認前の場合はメインエントリーではなくリビジョン1（作業領域）を更新しているため、
            // ログメッセージを「作業領域を更新」として通常の更新と区別する。
            if (ACMS_RAM::entryApproval($entryId) === EntryApprovalStatus::PreApproval->value) {
                AcmsLogger::info('「' . $entryData['entry_title'] . '」エントリーの作業領域を更新しました', [
                    'eid' => $entryId,
                    'cid' => $cid,
                ]);
            } else {
                AcmsLogger::info('「' . $entryData['entry_title'] . '」エントリーを更新しました', [
                    'eid' => $entryId,
                    'cid' => $cid,
                ]);
            }
        }

        /**
         * バージョンの保存
         */
        $rvid = null;
        if (enableRevision() && get_called_class() !== 'ACMS_POST_Entry_Update_Detail') {
            $rvid = Entry::saveEntryRevision($entryId, $revisionId, $entryData, $postEntry->get('revision_type'), $postEntry->get('revision_memo'));
            $rvid = is_int($rvid) ? $rvid : null;
            if (is_int($rvid)) {
                $this->saveRevisionUnit($collection, $entryId, $rvid);
                Entry::saveFieldRevision($entryId, $field, $rvid);
                $this->saveRevisionTag($postEntry->get('tag'), $entryId, $rvid);
                Entry::saveRelatedEntries($entryId, $postEntry->getArray('related'), $rvid, $postEntry->getArray('related_type'), $postEntry->getArray('loaded_realted_entries'));
                Entry::saveSubCategory($entryId, $cid, $postEntry->get('sub_category_id'), BID, $rvid);
                $this->saveGeometry('eid', $entryId, $this->extract('geometry'), $rvid);

                // エントリのカレントリビジョンを変更
                if ($isUpdateableForMainEntry) {
                    $sql = SQL::newUpdate('entry');
                    $sql->addUpdate('entry_current_rev_id', $rvid);
                    $sql->addUpdate('entry_reserve_rev_id', 0);
                    $sql->addWhereOpr('entry_id', $entryId);
                    $sql->addWhereOpr('entry_blog_id', BID);
                    DB::query($sql->get(dsn()), 'exec');
                } else {
                    $revision = Entry::getRevision($entryId, $rvid);
                    if ($isNewVersion) {
                        AcmsLogger::info('エントリーの新規バージョンを作成しました「' . $revision['entry_title'] . '（' . $revision['entry_rev_memo'] . '）」', [
                            'eid' => $entryId,
                            'rvid' => $rvid,
                        ]);
                    } else {
                        AcmsLogger::info('エントリーのバージョンを上書き保存しました「' . $revision['entry_title'] . '（' . $revision['entry_rev_memo'] . '）」', [
                            'eid' => $entryId,
                            'rvid' => $rvid,
                        ]);
                    }
                }
            }
        }
        $this->lockService->unlock($entryId, $rvid); // ロック解除

        if ($isNewVersion || $isApproved) {
            $cid = ACMS_RAM::entryCategory($entryId);
        }

        $SQL = SQL::newSelect('entry');
        $SQL->addSelect('entry_status');
        $SQL->addWhereOpr('entry_id', $entryId);
        $SQL->addWhereOpr('entry_blog_id', BID);
        $status = DB::query($SQL->get(dsn()), 'one');

        //-------------------
        // キャッシュクリア予約
        Entry::updateCacheControl($entryData['entry_start_datetime'], $entryData['entry_end_datetime'], BID, $entryId);

        //----------------
        // キャッシュクリア
        ACMS_POST_Cache::clearEntryPageCache($entryId); // このエントリのみ削除

        //------
        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$entryId, $rvid]);
            $events = ['entry:updated'];
            if (
                !$isNewVersion && // 新規バージョンではない
                !$isApproved && // 更新前のステータスが承認ステータスではない
                $preEntry['entry_status'] !== 'open' && // 更新前のステータスが公開ステータスではない
                $status === 'open' && // 更新後のステータスが公開ステータスである
                strtotime($entryData['entry_start_datetime']) <= REQUEST_TIME && // 公開開始日時が現在より前である
                strtotime($entryData['entry_end_datetime']) >= REQUEST_TIME // 公開終了日時が現在より後である
            ) {
                $events[] = 'entry:opened';
            }
            Webhook::call(BID, 'entry', $events, [$entryId, $rvid]);
        }

        return [
            'eid' => $entryId,
            'cid' => $cid,
            'rvid' => $rvid,
            'status' => $status,
            'success' => 1,
        ];
    }

    /**
     * acms_entryテーブルを更新するか判定
     *
     * @param array<string, mixed> $preEntry
     * @param \Field_Validation $postEntry
     * @param int<1, max>|null $revisionId
     * @return boolean
     */
    protected function isUpdateableForMainEntry(array $preEntry, \Field_Validation $postEntry, ?int $revisionId = null): bool
    {
        if ($revisionId !== null && $revisionId !== 1) {
            return false;
        }
        if ($this->isNewVersion($postEntry)) {
            return false;
        }
        if (!Entry::requiresApproval(BID, CID)) {
            // 承認不要（承認機能無効 or 承認管理者）はそのままメインエントリーを更新
            return true;
        }
        // 承認前エントリーは作業領域（リビジョン1）とメインエントリーが同一内容として扱われるため、
        // メインエントリーを直接更新する（updateEntry を呼ぶ）。
        // 承認済み以降は新バージョン作成になるため、メインエントリーの直接更新は行わない。
        if ($preEntry['entry_approval'] === EntryApprovalStatus::PreApproval->value) {
            return true;
        }
        return false;
    }

    /**
     * 新規バージョンとして保存するか判定
     *
     * @param \Field $postEntry
     * @return boolean
     */
    protected function isNewVersion($postEntry)
    {
        if (enableRevision() && $postEntry->get('revision_type') === 'new') {
            return true;
        }
        return false;
    }

    /**
     * バリデーション
     *
     * @param \Field_Validation $postEntry
     * @param int<1, max> $entryId
     * @param int<1, max>|null $revisionId
     * @return void
     */
    protected function validate(\Field_Validation $postEntry, int $entryId, ?int $revisionId = null)
    {
        $categoryId = (int) $postEntry->get('category_id');
        if ($categoryId === 0) {
            $categoryId = null;
        }
        $postEntry->setMethod('status', 'required');
        $postEntry->setMethod('status', 'in', ['open', 'close', 'draft', 'trash']);
        $postEntry->setMethod('status', 'category', true);
        $postEntry->setMethod('title', 'required');
        $postEntry->setMethod('title', 'maxlength', '255');
        $code = $postEntry->get('code');
        if ($code !== '') {
            $postEntry->setMethod('code', 'reserved', !isReserved($code, false));
            if (config('check_duplicate_entry_code') === 'on') {
                $postEntry->setMethod('code', 'double', !Entry::validEntryCodeDouble($code, BID, $categoryId, $entryId));
            }
            $postEntry->setMethod('code', 'string', isValidCode($code));
        }
        $postEntry->setMethod('code', 'maxlength', '64');
        $postEntry->setMethod('link', 'maxlength', '255');
        $postEntry->setMethod('indexing', 'required');
        $postEntry->setMethod('indexing', 'in', ['on', 'off']);
        $postEntry->setMethod('entry', 'operable', $this->isOperable($entryId, $categoryId, $revisionId));
        $postEntry->setMethod('entry', 'lock', !$this->isLocked($entryId, $revisionId));
        $postEntry = Entry::validTag($postEntry);
        $postEntry = Entry::validSubCategory($postEntry);

        $postEntry->validate(new ACMS_Validator());
    }

    /**
     * バリデーション失敗時の処理
     *
     * @param \Field_Validation $field
     * @param int|null $range
     * @param \Field_Validation $postEntry
     * @return void
     */
    protected function validateFailed(\Field_Validation $field, ?int $range, \Field_Validation $postEntry): void
    {
        if ($field->isValid('recover_acms_Po9H2zdPW4fj', 'required')) {
            $this->addMessage('failure'); // エントリーの復元機能によるエラーの時はメッセージを出さない
        }
        $primaryImageUnitId = $postEntry->get('primary_image') !== '' ? $postEntry->get('primary_image') : null;
        ['collection' => $collection, 'range' => $range] = $this->unitRepository->extractUnits($range, $primaryImageUnitId);
        // バリデーション失敗時でもアップロード済みファイルは保存する（編集画面で一度設定したファイルを保持するため）。
        // このタイミングでは saveAllUnits が呼ばれないので、参照チェック経由の削除は走らず古いファイルはそのまま残る。
        $this->unitRepository->saveAssets($collection);
        Entry::setSummaryRange($range);
        Entry::setTempUnitData($collection);
    }

    /**
     * ユニットをメインデータに保存
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param int $eid
     * @return \Acms\Services\Unit\UnitCollection 保存したユニットのコレクション
     */
    protected function saveUnit(
        \Acms\Services\Unit\UnitCollection $collection,
        int $eid
    ): \Acms\Services\Unit\UnitCollection {
        $collection = $this->unitRepository->saveAllUnits(
            collection: $collection,
            eid: $eid,
            bid: BID,
            rvid: null,
        );
        return $collection;
    }

    /**
     * リビジョンのユニットを更新
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param int $eid
     * @param int $rvid
     * @return void
     */
    protected function saveRevisionUnit(
        \Acms\Services\Unit\UnitCollection $collection,
        int $eid,
        int $rvid
    ): void {
        $savedCollection = $this->unitRepository->saveRevisionUnits(
            collection: $collection,
            eid: $eid,
            bid: BID,
            rvid: $rvid,
        );
        $primaryImageUnit = $savedCollection->getPrimaryImageUnitOrFallback();
        $primaryImageUnitId = $primaryImageUnit !== null ? $primaryImageUnit->getId() : null;

        // primaryImageIdを更新
        $sql = SQL::newUpdate('entry_rev');
        $sql->addUpdate('entry_primary_image', $primaryImageUnitId);
        $sql->addWhereOpr('entry_id', $eid);
        $sql->addWhereOpr('entry_rev_id', $rvid);
        $sql->addWhereOpr('entry_blog_id', BID);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * 続きを読むの範囲を取得
     *
     * @param mixed $postEntry
     * @return int|null
     */
    protected function getRange($postEntry)
    {
        $range = strval($postEntry->get('summary_range'));
        $range = ('' === $range) ? null : (int) $range;

        return $range;
    }

    /**
     * エントリーコードを整形して取得
     *
     * @param \Field_Validation $postEntry
     * @param int $entryId
     * @return string
     */
    protected function getEntryCode(\Field_Validation $postEntry, int $entryId): string
    {
        $code = trim(strval($postEntry->get('code')), '/');
        if ($code !== '') {
            $code = Entry::formatEntryCode($code);
        }
        return $code;
    }

    /**
     * 保存するエントリーデータを整形して取得
     *
     * @param int $entryId
     * @param array<string, mixed> $preEntry
     * @param \Field_Validation $postEntry
     * @param int|null $range
     * @return array<string, mixed>
     */
    protected function createUpdateEntryData(
        int $entryId,
        array $preEntry,
        \Field_Validation $postEntry,
        ?int $range
    ): array {
        $title = $postEntry->get('title');
        $status = $postEntry->get('status');
        $code = $postEntry->get('code');
        $datetime = $postEntry->get('date') . ' ' . $postEntry->get('time');
        if ('open' === $status && 'draft' === ACMS_RAM::entryStatus($entryId) && config('update_datetime_as_entry_open') !== 'off') {
            $datetime = date('Y-m-d H:i:s', REQUEST_TIME);
        }
        $cid = (int)$postEntry->get('category_id');
        if ($cid === 0) {
            $cid = null;
        }
        $data = [
            'entry_category_id' => $cid,
            'entry_code' => $code,
            'entry_summary_range' => $range,
            'entry_status' => $status,
            'entry_title' => $title,
            'entry_link' => strval($postEntry->get('link')),
            'entry_datetime' => $datetime,
            'entry_start_datetime' => $this->getFixPublicDate($postEntry, $datetime),
            'entry_end_datetime' => $postEntry->get('end_date') . ' ' . $postEntry->get('end_time'),
            'entry_indexing' => $postEntry->get('indexing', 'on'),
            'entry_members_only' => $postEntry->get('members_only', 'on'),
            'entry_updated_datetime' => date('Y-m-d H:i:s', REQUEST_TIME),
        ];
        // entry_approval の引き継ぎ
        //
        // このデータは updateEntry（メインエントリー更新）と saveEntryRevision（リビジョン保存）の
        // 両方に使用されるため、どちらのパスでも意図通りの値になるよう設定する必要がある。
        //
        // 承認フロー中（pre_approval）はユーザーの保存操作でステータスを変更すべきでないため、
        // このデータに entry_approval をセットしない。
        // セットしないことで saveEntryRevision では DB から取得した現在の値が使われる。
        //
        // 承認管理者は承認フローをバイパスして即時公開できるため、常に none をセットする。
        //
        // ⚠ 注意: updateEntry が呼ばれるかは isUpdateableForMainEntry の判定に依存する。
        // 非管理者かつ pre_approval ステータスの場合は isUpdateableForMainEntry = false となり
        // updateEntry は呼ばれないが、saveEntryRevision では使用されるためこの分岐が適用される。
        if (
            $preEntry['entry_approval'] !== EntryApprovalStatus::PreApproval->value
            || sessionWithApprovalAdministrator(BID, $cid)
        ) {
            $data['entry_approval'] = EntryApprovalStatus::None->value;
        }
        return $data;
    }

    /**
     * エントリーをメインデータに保存
     *
     * @param int $entryId
     * @param array<string, mixed> $row
     * @return void
     */
    protected function updateEntry(int $entryId, array $row): void
    {
        $sql = SQL::newUpdate('entry');
        foreach ($row as $key => $val) {
            $sql->addUpdate($key, $val);
        }
        $sql->addWhereOpr('entry_id', $entryId);
        $sql->addWhereOpr('entry_blog_id', BID);
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newSelect('entry');
        $sql->addWhereOpr('entry_id', $entryId);
        $sql->addWhereOpr('entry_blog_id', BID);

        ACMS_RAM::entry($entryId, DB::query($sql->get(dsn()), 'row'));
    }

    /**
     * タグをメインデータに保存
     *
     * @param int $eid
     * @param string $tags
     * @return void
     */
    protected function saveTag($eid, $tags)
    {
        $sql = SQL::newDelete('tag');
        $sql->addWhereOpr('tag_entry_id', $eid);
        DB::query($sql->get(dsn()), 'exec');
        if ($tags !== '') {
            $tags = Common::getTagsFromString($tags);
            $sql = SQL::newBulkInsert('tag');
            foreach ($tags as $sort => $tag) {
                if (isReserved($tag)) {
                    continue;
                }
                $sql->addInsert([
                    'tag_name' => $tag,
                    'tag_sort' => $sort + 1,
                    'tag_entry_id' => $eid,
                    'tag_blog_id' => BID,
                ]);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * リビジョンのタグを保存
     *
     * @param string $tags
     * @param int $eid
     * @param int $rvid
     * @return void
     */
    protected function saveRevisionTag($tags, $eid, $rvid)
    {
        $sql = SQL::newDelete('tag_rev');
        $sql->addWhereOpr('tag_entry_id', $eid);
        $sql->addWhereOpr('tag_rev_id', $rvid);
        DB::query($sql->get(dsn()), 'exec');

        if ($tags !== '') {
            $tags = Common::getTagsFromString($tags);
            $sql = SQL::newBulkInsert('tag_rev');
            foreach ($tags as $sort => $tag) {
                $sql->addInsert([
                    'tag_name' => $tag,
                    'tag_sort' => $sort + 1,
                    'tag_entry_id' => $eid,
                    'tag_blog_id' => BID,
                    'tag_rev_id' => $rvid,
                ]);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * エントリーの事前処理
     *
     * @param \Field_Validation $postEntry
     * @param int $entryId
     * @return void
     */
    protected function preprocess(\Field_Validation $postEntry, int $entryId): void
    {
        $this->fix($postEntry);
        $postEntry->set('code', $this->getEntryCode($postEntry, $entryId));
    }

    /**
     * エントリーの操作権限があるかチェック
     *
     * @param int|null $entryId
     * @param int|null $categoryId
     * @param int|null $rvid
     * @return bool
     */
    protected function isOperable(?int $entryId = null, ?int $categoryId = null, ?int $rvid = null)
    {
        if ($entryId === null || $entryId <= 0) {
            return false;
        }
        return Entry::canUpdate($entryId, BID, $categoryId, $rvid);
    }

    /**
     * エントリーロックによって保存できないかチェック
     *
     * @param int $entryId
     * @param int|null $rvid
     * @return bool
     */
    protected function isLocked(int $entryId, ?int $rvid = null)
    {
        if (enableRevision() && Entry::isNewVersion()) {
            // 新規バージョンとして保存する場合は、ロックが関係ないので、OK
            return false;
        }
        if ($this->lockService->isAlertOnly()) {
            // アラートのみの設定なら、保存OK
            return false;
        }
        if ($this->lockService->getLockedUser($entryId, $rvid, SUID) === false) {
            // ロックがかかってない場合は、OK
            return false;
        }
        return true;
    }
}
