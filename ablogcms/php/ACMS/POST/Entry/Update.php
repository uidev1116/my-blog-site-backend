<?php

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
        $updatedResponse = $this->update();
        $redirect = $this->Post->get('redirect');

        setCookieDelFlag();

        if (is_array($updatedResponse) && $redirect !== '' && Common::isSafeUrl($redirect)) {
            $this->responseRedirect($redirect);
        }

        if (is_array($updatedResponse)) {
            $Session = &Field::singleton('session');
            $Session->add('entry_action', 'update');
            $info = [
                'bid' => BID,
                'cid' => $updatedResponse['cid'],
                'eid' => EID,
            ];
            if ($updatedResponse['trash'] == 'trash') {
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
                    'eid' => EID,
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
     * @param mixed $exceptField
     * @return array|bool
     */
    public function update($exceptField = false)
    {
        ACMS_RAM::entry(EID, null);

        $postEntry = $this->extract('entry');
        $this->fix($postEntry);
        $customFieldCollection = [];
        $cid = (int)$postEntry->get('category_id');
        if ($cid === 0) {
            $cid = null;
        }

        $preEntry = ACMS_RAM::entry(EID);
        $isUpdateableForMainEntry = $this->isUpdateableForMainEntry($preEntry, $postEntry); // メインエントリを更新するか判定
        $isNewVersion = $this->isNewVersion($postEntry); // 新規バージョンとして保存するか判定 $isNewVersionだったもの
        $isApproved = enableApproval() && $preEntry['entry_approval'] !== 'pre_approval';

        if (enableRevision() && $postEntry->get('revision_type') === 'new') {
            Entry::setNewVersion(true);
        }

        $this->validate($postEntry); // バリデート

        $field = $this->extract('field', new ACMS_Validator()); // カスタムフィールドを事前処理
        foreach ($this->fieldNames as $fieldName) {
            $customFieldCollection[$fieldName] = $this->extract($fieldName, new ACMS_Validator());
        }

        $range = $this->getRange($postEntry);

        if (!$this->Post->isValidAll()) {
            // バリデーション失敗
            $this->validateFailed($field, $range, $postEntry);

            AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '」エントリーの更新に失敗しました', [
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
        $entryData = $this->getUpdateEntryData($preEntry, $postEntry, Entry::getSummaryRange()); // エントリーの事前処理

        /**
         * エントリーの保存
         */
        if ($isUpdateableForMainEntry) {
            $collection = $this->saveUnit($collection, EID); // ユニット（unitテーブル）を更新
            if (get_called_class() !== 'ACMS_POST_Entry_Update_Detail') {
                $primaryImageUnit = $collection->getPrimaryImageUnitOrFallback();
                $primaryImageId = $primaryImageUnit !== null ? $primaryImageUnit->getId() : null;
                $entryData['entry_primary_image'] = $primaryImageId;
            }
            $this->updateEntry($entryData); // エントリ（entryテーブル）を更新
            $this->saveTag(EID, $postEntry->get('tag')); // タグ（tagテーブル）を更新
            Entry::saveRelatedEntries(EID, $postEntry->getArray('related'), null, $postEntry->getArray('related_type'), $postEntry->getArray('loaded_realted_entries')); // 関連エントリ（relationship）を更新
            Entry::saveSubCategory(EID, $cid, $postEntry->get('sub_category_id')); // サブカテゴリー（entry_sub_category）を更新
            $this->saveGeometry('eid', EID, $this->extract('geometry')); // 位置情報（geo）を更新
            if (!$exceptField) {
                Common::saveField('eid', EID, $field); // フィールド（field）を更新
                foreach ($customFieldCollection as $fieldName => $customField) {
                    $this->saveCustomField($fieldName, EID, $customField);
                }
            }
            Common::saveFulltext('eid', EID, Common::loadEntryFulltext(EID)); // フルテキスト（fulltext）を更新

            if (ACMS_RAM::entryApproval(EID) === 'pre_approval') {
                AcmsLogger::info('「' . $entryData['entry_title'] . '」エントリーの作業領域を更新しました', [
                    'eid' => EID,
                    'cid' => $cid,
                ]);
            } else {
                AcmsLogger::info('「' . $entryData['entry_title'] . '」エントリーを更新しました', [
                    'eid' => EID,
                    'cid' => $cid,
                ]);
            }
        }

        /**
         * バージョンの保存
         */
        $rvid = null;
        if (enableRevision() && get_called_class() !== 'ACMS_POST_Entry_Update_Detail') {
            $rvid = Entry::saveEntryRevision(EID, RVID, $entryData, $postEntry->get('revision_type'), $postEntry->get('revision_memo'));
            $rvid = is_int($rvid) ? $rvid : null;
            if (is_int($rvid)) {
                $this->saveRevisionUnit($collection, EID, $rvid); // @phpstan-ignore-line
                Entry::saveFieldRevision(EID, $field, $rvid);
                $this->saveRevisionTag($postEntry->get('tag'), EID, $rvid);
                Entry::saveRelatedEntries(EID, $postEntry->getArray('related'), $rvid, $postEntry->getArray('related_type'), $postEntry->getArray('loaded_realted_entries'));
                Entry::saveSubCategory(EID, $cid, $postEntry->get('sub_category_id'), BID, $rvid);
                $this->saveGeometry('eid', EID, $this->extract('geometry'), $rvid);

                // エントリのカレントリビジョンを変更
                if ($isUpdateableForMainEntry) {
                    $sql = SQL::newUpdate('entry');
                    $sql->addUpdate('entry_current_rev_id', $rvid);
                    $sql->addUpdate('entry_reserve_rev_id', 0);
                    $sql->addWhereOpr('entry_id', EID);
                    $sql->addWhereOpr('entry_blog_id', BID);
                    DB::query($sql->get(dsn()), 'exec');
                } else {
                    $revision = Entry::getRevision(EID, $rvid);
                    if ($isNewVersion) {
                        AcmsLogger::info('エントリーの新規バージョンを作成しました「' . $revision['entry_title'] . '（' . $revision['entry_rev_memo'] . '）」', [
                            'eid' => EID,
                            'rvid' => $rvid,
                        ]);
                    } else {
                        AcmsLogger::info('エントリーのバージョンを上書き保存しました「' . $revision['entry_title'] . '（' . $revision['entry_rev_memo'] . '）」', [
                            'eid' => EID,
                            'rvid' => $rvid,
                        ]);
                    }
                }
            }
        }
        $this->lockService->unlock(EID, $rvid); // ロック解除

        if ($isNewVersion || $isApproved) {
            $cid = ACMS_RAM::entryCategory(EID);
        }

        $SQL = SQL::newSelect('entry');
        $SQL->addSelect('entry_status');
        $SQL->addWhereOpr('entry_id', EID);
        $SQL->addWhereOpr('entry_blog_id', BID);
        $status = DB::query($SQL->get(dsn()), 'one');

        //-------------------
        // キャッシュクリア予約
        Entry::updateCacheControl($entryData['entry_start_datetime'], $entryData['entry_end_datetime'], BID, EID);

        //----------------
        // キャッシュクリア
        ACMS_POST_Cache::clearEntryPageCache(EID); // このエントリのみ削除

        //------
        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [EID, $rvid]);
            $events = ['entry:updated'];
            if (
                1
                && !$isNewVersion
                && !$isApproved
                && $preEntry['entry_status'] !== 'open'
                && $status === 'open'
                && strtotime($entryData['entry_start_datetime']) <= REQUEST_TIME
                && strtotime($entryData['entry_end_datetime']) >= REQUEST_TIME
            ) {
                $events[] = 'entry:opened';
            }
            Webhook::call(BID, 'entry', $events, [EID, $rvid]);
        }

        return [
            'eid' => EID,
            'cid' => $cid,
            'ecd' => $this->getEntryCode($postEntry),
            'rvid' => $rvid,
            'trash' => $status,
            'updateApproval' => $isApproved,
            'isNewVersion' => $isNewVersion,
            'success' => 1,
        ];
    }

    /**
     * acms_entryテーブルを更新するか判定
     *
     * @param \Field $postEntry
     * @return boolean
     */
    protected function isUpdateableForMainEntry($preEntry, $postEntry)
    {
        if (RVID && RVID !== 1) {
            return false;
        }
        if ($this->isNewVersion($postEntry)) {
            return false;
        }
        if (sessionWithApprovalAdministrator()) {
            return true;
        }
        if (enableApproval()) {
            if ($preEntry['entry_approval'] === 'pre_approval') {
                return true;
            }
            return false;
        }
        return true;
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
     * @return void
     */
    protected function validate($postEntry)
    {
        if (!($cid = $postEntry->get('category_id'))) {
            $cid = null;
        }
        $postEntry->setMethod('status', 'required');
        $postEntry->setMethod('status', 'in', ['open', 'close', 'draft', 'trash']);
        $postEntry->setMethod('status', 'category', true);
        $postEntry->setMethod('title', 'required');
        if (!!($code = strval($postEntry->get('code')))) {
            if (!config('entry_code_extension')) {
                $postEntry->setMethod('code', 'reserved', !isReserved($code, false));
            }
            if (config('check_duplicate_entry_code') === 'on') {
                $postEntry->setMethod('code', 'double', !Entry::validEntryCodeDouble($code, BID, $cid, EID));
            }
        }
        $postEntry->setMethod('code', 'string', isValidCode($postEntry->get('code')));
        $postEntry->setMethod('indexing', 'required');
        $postEntry->setMethod('indexing', 'in', ['on', 'off']);
        $postEntry->setMethod('entry', 'operable', $this->isOperable());
        $postEntry->setMethod('entry', 'lock', !$this->isLocked());
        $postEntry = Entry::validTag($postEntry);
        $postEntry = Entry::validSubCategory($postEntry);

        $postEntry->validate(new ACMS_Validator());
    }

    /**
     * バリデーション失敗時の処理
     *
     * @param \Field_Validation $field
     * @param int $range
     * @param \Field $postEntry
     * @return void
     */
    protected function validateFailed($field, $range, $postEntry)
    {
        if ($field->isValid('recover_acms_Po9H2zdPW4fj', 'required')) {
            $this->addMessage('failure'); // エントリーの復元機能によるエラーの時はメッセージを出さない
        }
        $primaryImageUnitId = $postEntry->get('primary_image') !== '' ? $postEntry->get('primary_image') : null;
        ['collection' => $collection, 'range' => $range] = $this->unitRepository->extractUnits($range, $primaryImageUnitId);
        // バリデーション失敗時でもファイルを保存する（ファイルの削除は行わない）
        // 編集画面で一度設定したファイルを保持するため
        $this->unitRepository->saveAssets($collection, false);
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
        $collection = $this->unitRepository->saveAllUnits($collection, $eid, BID);
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
        $savedCollection = $this->unitRepository->saveRevisionUnits($collection, $eid, BID, $rvid);
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
     * @param mixed $postEntry
     * @return string
     */
    protected function getEntryCode($postEntry)
    {
        $code = trim(strval($postEntry->get('code')), '/');
        if ($code !== '' && !!config('entry_code_extension') && !strpos($code, '.')) {
            $code .= ('.' . config('entry_code_extension'));
        }
        return $code;
    }

    /**
     * 保存するエントリーデータを整形して取得
     *
     * @param mixed $preEntry
     * @param mixed $postEntry
     * @param mixed $range
     * @return array
     */
    protected function getUpdateEntryData($preEntry, $postEntry, $range)
    {
        $title = $postEntry->get('title');
        $status = $postEntry->get('status');
        $code = $this->getEntryCode($postEntry);
        $datetime = $postEntry->get('date') . ' ' . $postEntry->get('time');
        if ('open' === $status && 'draft' === ACMS_RAM::entryStatus(EID) && config('update_datetime_as_entry_open') !== 'off') {
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
        if ($preEntry['entry_approval'] !== 'pre_approval' || sessionWithApprovalAdministrator(BID, CID)) {
            $data['entry_approval'] = 'none';
        }
        return $data;
    }

    /**
     * エントリーをメインデータに保存
     *
     * @param array $row
     * @return void
     */
    protected function updateEntry($row)
    {
        $sql = SQL::newUpdate('entry');
        foreach ($row as $key => $val) {
            $sql->addUpdate($key, $val);
        }
        $sql->addWhereOpr('entry_id', EID);
        $sql->addWhereOpr('entry_blog_id', BID);
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newSelect('entry');
        $sql->addWhereOpr('entry_id', EID);
        $sql->addWhereOpr('entry_blog_id', BID);

        ACMS_RAM::entry(EID, DB::query($sql->get(dsn()), 'row'));
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
     * エントリーの操作権限があるかチェック
     *
     * @return bool
     */
    protected function isOperable()
    {
        if (!EID) {
            return false;
        }
        return Entry::canUpdate(EID, BID, CID, RVID);
    }

    /**
     * エントリーロックによって保存できないかチェック
     *
     * @return bool
     */
    protected function isLocked()
    {
        if (enableRevision() && Entry::isNewVersion()) {
            // 新規バージョンとして保存する場合は、ロックが関係ないので、OK
            return false;
        }
        if ($this->lockService->isAlertOnly()) {
            // アラートのみの設定なら、保存OK
            return false;
        }
        if ($this->lockService->getLockedUser(EID, RVID, SUID) === false) {
            // ロックがかかってない場合は、OK
            return false;
        }
        return true;
    }
}
