<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Common;

class ACMS_POST_Entry_Insert extends ACMS_POST_Entry_Update
{
    /**
     * エントリーを作成
     */
    public function post()
    {
        $insertedResponse = $this->insert();
        $redirect = $this->Post->get('redirect');

        setCookieDelFlag();

        if (is_array($insertedResponse) && $redirect !== '' && Common::isSafeUrl($redirect)) {
            $this->responseRedirect($redirect);
        }

        if (is_array($insertedResponse)) {
            $Session = &Field::singleton('session');
            $Session->add('entry_action', 'update');
            $info = [
                'bid' => BID,
                'cid' => $insertedResponse['cid'],
                'eid' => $insertedResponse['eid'],
                'query' => [],
            ];
            if ($insertedResponse['trash'] == 'trash') {
                $info['query'] = ['trash' => 'show'];
            }
            if (ADMIN === 'entry_editor') {
                $info['admin'] = 'entry_editor';
                $info['query'] += ['success' => $insertedResponse['success']];
            }
            $this->responseRedirect(acmsLink($info));
        }

        return $this->responseGet();
    }

    /**
     * エントリー作成
     *
     * @return array|bool
     */
    protected function insert()
    {
        $postEntry = $this->extract('entry');
        $this->fix($postEntry);
        $customFieldCollection = [];
        $eid = DB::query(SQL::nextval('entry_id', dsn()), 'seq');
        $cid = is_numeric($postEntry->get('category_id')) ? intval($postEntry->get('category_id')) : null;
        // バリデート
        $code = $this->insertValidate($postEntry, $eid, $cid);

        // カスタムフィールドを事前処理
        $field = $this->extract('field', new ACMS_Validator());
        foreach ($this->fieldNames as $fieldName) {
            $customFieldCollection[$fieldName] = $this->extract($fieldName);
        }

        $range = $this->getRange($postEntry);

        if (!$this->Post->isValidAll()) {
            // バリデーション失敗
            $this->validateFailed($field, $range, $postEntry);

            AcmsLogger::info('エントリーの作成に失敗しました', [
                'Entry' => $postEntry,
            ]);
            return false;
        }

        // ユニットを事前処理
        $primaryImageUnitId = $postEntry->get('primary_image') !== '' ? $postEntry->get('primary_image') : null;
        ['collection' => $collection, 'range' => $range] = $this->unitRepository->extractUnits($range, $primaryImageUnitId);
        $this->unitRepository->saveAssets($collection);
        Entry::setSummaryRange($range);

        // エントリーの事前処理
        $entryData = $this->getInsertEntryData($postEntry);
        $entryData['entry_id'] = $eid;
        $entryData['entry_category_id'] = $cid;
        $entryData['entry_code'] = $code;
        $entryData['entry_summary_range'] = Entry::getSummaryRange();
        $entryData['entry_sort'] = $this->getEntrySort();
        $entryData['entry_user_sort'] = $this->getUserSort();
        $entryData['entry_category_sort'] = $this->getCategorySort($cid);

        /**
         * エントリーの保存
         */
        $collection = $this->saveUnit($collection, $eid);
        $primaryImageUnit = $collection->getPrimaryImageUnitOrFallback();
        $primaryImageId = $primaryImageUnit !== null ? $primaryImageUnit->getId() : null;
        $entryData['entry_primary_image'] = $primaryImageId;
        $this->insertEntry($eid, $entryData);
        $this->saveTag($eid, $postEntry->get('tag'));
        Common::saveField('eid', $eid, $field);
        foreach ($customFieldCollection as $fieldName => $customField) {
            $this->saveCustomField($fieldName, $eid, $customField);
        }
        $this->saveGeometry('eid', $eid, $this->extract('geometry'));
        Entry::saveRelatedEntries($eid, $postEntry->getArray('related'), null, $postEntry->getArray('related_type'), $postEntry->getArray('loaded_realted_entries'));
        Entry::saveSubCategory($eid, $cid, $postEntry->get('sub_category_id'));
        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));

        /**
         * 作業領域バージョンの保存
         */
        if (enableRevision()) {
            $rvid = 1;
            Entry::saveEntryRevision($eid, $rvid, $entryData, null, $postEntry->get('revision_memo'));
            $this->saveRevisionUnit($collection, $eid, $rvid);
            Entry::saveFieldRevision($eid, $field, $rvid);
            $this->saveRevisionTag($postEntry->get('tag'), $eid, $rvid);
            Entry::saveRelatedEntries($eid, $postEntry->getArray('related'), $rvid, $postEntry->getArray('related_type'), $postEntry->getArray('loaded_realted_entries'));
            Entry::saveSubCategory($eid, $cid, $postEntry->get('sub_category_id'), BID, $rvid);
        }

        if (enableApproval() && !sessionWithApprovalAdministrator()) {
            $SQL = SQL::newUpdate('entry');
            $SQL->addUpdate('entry_approval', 'pre_approval');
            $SQL->addWhereOpr('entry_id', $eid);
            $SQL->addWhereOpr('entry_blog_id', BID);
            DB::query($SQL->get(dsn()), 'exec');
            ACMS_RAM::entry($eid, null);

            AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」承認前エントリーを作成しました', [
                'bid' => BID,
                'eid' => $eid,
            ]);
        } else {
            AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーを作成しました', [
                'bid' => BID,
                'eid' => $eid,
            ]);
        }

        // キャッシュクリア予約
        Entry::updateCacheControl($entryData['entry_start_datetime'], $entryData['entry_end_datetime'], BID, $eid);

        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [intval($eid), null]);
            $events = ['entry:created'];
            if (
                1
                && !(enableApproval() && !sessionWithApprovalAdministrator())
                && $entryData['entry_status'] === 'open'
                && strtotime($entryData['entry_start_datetime']) <= REQUEST_TIME
                && strtotime($entryData['entry_end_datetime']) >= REQUEST_TIME
            ) {
                $events[] = 'entry:opened';
            }
            Webhook::call(BID, 'entry', $events, [intval($eid), null]);
        }

        return [
            'eid' => $eid,
            'cid' => $cid,
            'ecd' => $code,
            'trash' => $postEntry->get('status'),
            'success' => 1,
        ];
    }

    /**
     * エントリーソートを取得
     *
     * @return int
     */
    protected function getEntrySort()
    {
        return $this->entryRepository->nextSort(BID);
    }

    /**
     * ユーザー絞り込み時のソートを取得
     *
     * @return int
     */
    protected function getUserSort()
    {
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        if ($sessionUserId === null) {
            throw new \LogicException('You must be logged in to perform this action.');
        }
        return $this->entryRepository->nextUserSort($sessionUserId, BID);
    }

    /**
     * カテゴリー絞り込み時のソートを取得
     *
     * @return int
     */
    protected function getCategorySort($cid)
    {
        return $this->entryRepository->nextCategorySort($cid, BID);
    }

    /**
     * 保存するエントリーデータを整形して取得
     *
     * @param mixed $postEntry
     * @return array
     */
    protected function getInsertEntryData($postEntry)
    {
        $title = $postEntry->get('title');
        $datetime = $postEntry->get('date') . ' ' . $postEntry->get('time');
        $data = [
            'entry_posted_datetime' => date('Y-m-d H:i:s', REQUEST_TIME),
            'entry_updated_datetime' => date('Y-m-d H:i:s', REQUEST_TIME),
            'entry_user_id' => SUID,
            'entry_blog_id' => BID,
            'entry_status' => $postEntry->get('status'),
            'entry_title' => $title,
            'entry_link' => strval($postEntry->get('link')),
            'entry_datetime' => $datetime,
            'entry_start_datetime' => $this->getFixPublicDate($postEntry, $datetime),
            'entry_end_datetime' => $postEntry->get('end_date') . ' ' . $postEntry->get('end_time'),
            'entry_indexing' => $postEntry->get('indexing'),
            'entry_members_only' => $postEntry->get('members_only'),
            'entry_hash' => md5(SYSTEM_GENERATED_DATETIME . date('Y-m-d H:i:s', REQUEST_TIME)),
            'entry_current_rev_id' => enableApproval() ? 0 : 1,
            'entry_reserve_rev_id' => 0,
            'entry_last_update_user_id' => SUID,
        ];
        return $data;
    }

    /**
     * エントリーをメインデータに保存
     *
     * @param int $eid
     * @param array $entryData
     * @return void
     */
    protected function insertEntry($eid, $entryData)
    {
        $sql = SQL::newInsert('entry');
        foreach ($entryData as $key => $val) {
            $sql->addInsert($key, $val);
        }
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newSelect('entry');
        $sql->addWhereOpr('entry_id', $eid);
        $sql->addWhereOpr('entry_blog_id', BID);
        ACMS_RAM::entry($eid, DB::query($sql->get(dsn()), 'row'));
    }

    /**
     * バリデート
     *
     * @param mixed $postEntry
     * @return string
     */
    protected function insertValidate($postEntry, $eid, $cid)
    {
        $postEntry->setMethod('status', 'required');
        $postEntry->setMethod('status', 'in', ['open', 'close', 'draft', 'trash']);
        $postEntry->setMethod('status', 'category', true);
        $postEntry->setMethod('title', 'required');
        $code = strval($postEntry->get('code'));
        if ($code === '') {
            $code = $this->getEntryNewCode($postEntry, $eid);
        }
        if (!config('entry_code_extension')) {
            $postEntry->setMethod('code', 'reserved', !isReserved($code, false));
        }
        if (config('check_duplicate_entry_code') === 'on') {
            $postEntry->setMethod('code', 'double', !Entry::validEntryCodeDouble($code, BID, $cid));
        }
        $postEntry->setMethod('code', 'string', isValidCode($postEntry->get('code')));
        $postEntry->setMethod('indexing', 'required');
        $postEntry->setMethod('indexing', 'in', ['on', 'off']);
        $postEntry->setMethod('entry', 'operable', $this->isOperable());
        $postEntry = Entry::validTag($postEntry);
        $postEntry = Entry::validSubCategory($postEntry);
        $postEntry->validate(new ACMS_Validator());

        return $code;
    }

    /**
     * 操作権限があるかチェックs
     *
     * @return bool
     */
    protected function isOperable()
    {
        if (roleAvailableUser()) {
            if (IS_LICENSED && roleAuthorization('entry_edit', BID)) {
                return true;
            }
        } else {
            if (IS_LICENSED && sessionWithContribution(BID)) {
                return true;
            }
        }
        return false;
    }

    /**
     * エントリーコードを整形して取得
     *
     * @param mixed $postEntry
     * @return string
     */
    protected function getEntryNewCode($postEntry, $eid)
    {
        $title = $postEntry->get('title');
        $code = trim(strval($postEntry->get('code')), '/');
        if ($code === '') {
            $code = ('on' == config('entry_code_title')) ? stripWhitespace($title) : config('entry_code_prefix') . $eid;
        }
        if (!!config('entry_code_extension') && !strpos($code, '.')) {
            $code .= ('.' . config('entry_code_extension'));
        }

        return $code;
    }
}
