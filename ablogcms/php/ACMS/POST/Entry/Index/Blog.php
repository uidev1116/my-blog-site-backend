<?php

use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Application;

class ACMS_POST_Entry_Index_Blog extends ACMS_POST
{
    protected $globalCids = [];

    protected \Acms\Services\Entry\EntryRepository $entryRepository;

    /**
     * constructor
     */
    public function __construct()
    {
        $this->entryRepository = Application::make('entry.repository');
    }

    function post()
    {
        if (!($bid = intval($this->Post->get('bid')))) {
            $bid = 0;
        }
        $this->Post->setMethod('checks', 'required');
        if (empty($bid)) {
            $this->Post->setMethod('entry', 'operable', false);
        } else {
            $this->Post->setMethod('entry', 'operable', Entry::canBulkBlogChange($bid));
        }

        $this->Post->validate(new ACMS_Validator());

        if (!$this->Post->isValidAll()) {
            Logger::info('指定されたエントリーの一括ブログ移動に失敗しました');
            return $this->Post;
        }

        @set_time_limit(0);
        $targetEntryIds = [];
        foreach (array_reverse($this->Post->getArray('checks')) as $eid) {
            $id = preg_split('@:@', $eid, 2, PREG_SPLIT_NO_EMPTY);
            $eid = intval($id[1]);
            if (empty($eid)) {
                continue;
            }
            $this->moveEntry($eid, $bid, SUID);
            $targetEntryIds[] = $eid;
        }
        Logger::info('指定されたエントリーを「' . ACMS_RAM::blogName($bid) . '」ブログに一括移動させました', [
            'targetEntryIds' => implode(',', $targetEntryIds),
            'targetBlogId' => $bid,
        ]);

        return $this->Post;
    }

    /**
     * エントリーを指定されたブログに移動
     *
     * @param int $eid
     * @param int $bid
     * @param int $suid
     * @return void
     */
    protected function moveEntry(int $eid, int $bid, int $suid): void
    {
        $sort = $this->getEntrySort($bid);
        $usort = $this->getEntryUserSort($bid, $suid);
        $cid = ACMS_RAM::entryCategory($eid);
        $csort = $this->getEntryCategorySort($bid, $cid);
        $isGlobalCategory = $cid === null ? false : $this->isGlobalCategory($bid, $cid);

        // 通常エントリーを移動
        $this->moveEntryBase($eid, $bid, $cid, $sort, $usort, $csort, $suid, $isGlobalCategory, false);
        $this->moveEntryField($eid, $bid, false);
        $this->moveEntryUnit($eid, $bid, false);
        $this->moveEntryTag($eid, $bid, false);
        $this->moveEntryFulltext($eid, $bid);

        // リビジョンを移動
        $this->moveEntryBase($eid, $bid, $cid, $sort, $usort, $csort, $suid, $isGlobalCategory, true);
        $this->moveEntryField($eid, $bid, true);
        $this->moveEntryUnit($eid, $bid, true);
        $this->moveEntryTag($eid, $bid, true);
    }

    /**
     * エントリーの基本データを指定したブログに移動
     *
     * @param int $eid
     * @param int $bid
     * @param int|null $cid
     * @param int $sort
     * @param int $usort
     * @param int $csort
     * @param int $suid
     * @param bool $isGlobalCategory
     * @param bool $revision
     * @return void
     */
    protected function moveEntryBase(
        int $eid,
        int $bid,
        int|null $cid,
        int $sort,
        int $usort,
        int $csort,
        int $suid,
        bool $isGlobalCategory,
        bool $revision = false
    ): void {
        $tableName = $revision ? 'entry_rev' : 'entry';
        $sql = SQL::newUpdate($tableName);
        $sql->addUpdate('entry_blog_id', $bid);
        if (!$isGlobalCategory || is_null($cid)) {
            $sql->addUpdate('entry_category_id', null);
        } elseif ($revision) {
            $sql->addUpdate('entry_category_id', $cid);
        }
        $sql->addUpdate('entry_sort', $sort);
        $sql->addUpdate('entry_user_sort', $usort);
        $sql->addUpdate('entry_category_sort', $csort);

        $sql->addWhereOpr('entry_id', $eid);
        if (!sessionWithCompilation()) {
            $sql->addWhereOpr('entry_user_id', $suid);
        }
        DB::query($sql->get(dsn()), 'exec');
        ACMS_RAM::entry($eid, null);
    }

    /**
     * エントリーのフルテキストを指定したブログに移動
     *
     * @param int $eid
     * @param int $bid
     * @return void
     */
    protected function moveEntryFulltext(int $eid, int $bid): void
    {
        $sql = SQL::newUpdate('fulltext');
        $sql->addUpdate('fulltext_blog_id', $bid);
        $sql->addWhereOpr('fulltext_eid', $eid);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * エントリーのフィールドを指定したブログに移動
     *
     * @param int $eid
     * @param int $bid
     * @param bool $revision
     * @return void
     */
    protected function moveEntryField(int $eid, int $bid, bool $revision = false): void
    {
        $tableName = $revision ? 'field_rev' : 'field';
        $sql = SQL::newUpdate($tableName);
        $sql->addUpdate('field_blog_id', $bid);
        $sql->addWhereOpr('field_eid', $eid);
        DB::query($sql->get(dsn()), 'exec');
        Common::deleteFieldCache('eid', $eid);
    }

    /**
     * エントリーのユニットを指定したブログに移動
     *
     * @param int $eid
     * @param int $bid
     * @param bool $revision
     * @return void
     */
    protected function moveEntryUnit(int $eid, int $bid, bool $revision = false): void
    {
        $tableName = $revision ? 'column_rev' : 'column';
        $selectSql = SQL::newSelect($tableName);
        $selectSql->addWhereOpr('column_entry_id', $eid);
        $units = DB::query($selectSql->get(dsn()), 'all');

        $updateSql = SQL::newUpdate($tableName);
        $updateSql->addUpdate('column_blog_id', $bid);
        $updateSql->addWhereOpr('column_entry_id', $eid);
        DB::query($updateSql->get(dsn()), 'exec');

        $unitIds = array_column($units, 'column_id');
        $updateFieldSql = SQL::newUpdate($revision ? 'field_rev' : 'field');
        $updateFieldSql->addUpdate('field_blog_id', $bid);
        $updateFieldSql->addWhereIn('field_unit_id', $unitIds);
        DB::query($updateFieldSql->get(dsn()), 'exec');
    }

    /**
     * エントリーのタグを指定したブログに移動
     *
     * @param int $eid
     * @param int $bid
     * @param bool $revision
     * @return void
     */
    protected function moveEntryTag(int $eid, int $bid, bool $revision = false): void
    {
        $tableName = $revision ? 'tag_rev' : 'tag';
        $sql = SQL::newUpdate($tableName);
        $sql->addUpdate('tag_blog_id', $bid);
        $sql->addWhereOpr('tag_entry_id', $eid);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * 指定されたカテゴリーIDがグローバルカテゴリーか判定
     *
     * @param int $cid
     * @return bool
     */
    protected function isGlobalCategory(int $bid, int $cid): bool
    {
        static $globalCategoryIds = [];
        static $loaded = false;
        if ($loaded === false) {
            $globalCategoryIds = $this->getGlobalCategory($bid);
            $loaded = true;
        }
        return in_array($cid, $globalCategoryIds, true);
    }

    /**
     * 指定ブログのエントリーの最大ソート番号を取得
     *
     * @param int $bid
     * @return int
     */
    protected function getEntrySort(int $bid): int
    {
        return $this->entryRepository->nextSort($bid);
    }

    /**
     * 指定ブログ・指定ユーザーのエントリーの最大ソート番号（ユーザー）を取得
     *
     * @param int $bid
     * @param int $uid
     * @return int
     */
    protected function getEntryUserSort(int $bid, int $uid): int
    {
        return $this->entryRepository->nextUserSort($uid, $bid);
    }

    /**
     * 指定ブログ・指定カテゴリーのエントリーの最大ソート番号（カテゴリー）を取得
     *
     * @param int $bid
     * @param int|null $cid
     * @return int
     */
    protected function getEntryCategorySort(int $bid, ?int $cid): int
    {
        return $this->entryRepository->nextCategorySort($cid, $bid);
    }

    /**
     * 移動先ブログで参照可能なグローバルカテゴリーを取得する
     *
     * @param int $bid
     * @return array
     */
    protected function getGlobalCategory(int $bid): array
    {
        $globalCategoryIds = [];
        $SQL = SQL::newSelect('category');
        $SQL->addSelect('category_id', 'id');
        $SQL->addWhereOpr('category_scope', 'global');  // TreeGlobalとは別に、category_scope='global'の条件を与えておく
        $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        ACMS_Filter::categoryTreeGlobal($SQL, $bid, true);
        foreach (DB::query($SQL->get(dsn()), 'all') as $category) {
            $globalCategoryIds[] = intval($category['id']);
        }
        return $globalCategoryIds;
    }
}
