<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Entry;

class ACMS_POST_Import extends ACMS_POST
{
    use \Acms\Traits\Unit\UnitModelTrait;

    /**
     * ユニットタイプ
     * @var string
     */
    protected $unitType;

    /**
     * アップロードファイル名
     * @var string
     */
    protected $uploadFiledName;

    /**
     * @var \ACMS_Http_File
     */
    protected $httpFile;
    /**
     * @var string|false
     */
    protected $locale;
    /**
     * @var int
     */
    protected $entryCount = 0;
    /**
     * @var array<string, int>
     */
    protected $categoryList = [];
    /**
     * @var string
     */
    protected $importType = '';

    /**
     * @var int|null
     */
    protected $importCid;

    /**
     * インポートでスキップしたエントリーのエラー一覧
     *
     * @var array<int, array{index: int, message: string}>
     */
    protected $importErrors = [];

    /**
     * インポートを初期化
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * インポートを実行
     *
     * @return void
     */
    public function import()
    {
    }

    /**
     * インポートでスキップしたエントリーのエラーを記録する
     *
     * @param int $entryIndex エントリー番号（何件目か）
     * @param string $message エラーメッセージ
     * @return void
     */
    public function recordImportError($entryIndex, $message)
    {
        $this->importErrors[] = ['index' => $entryIndex, 'message' => $message];
    }

    /**
     * @inheritDoc
     */
    public function post()
    {
        @set_time_limit(0);

        if (!sessionWithCompilation()) {
            return $this->Post;
        }

        $this->locale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'ja_JP.UTF-8');

        $this->init();
        $this->unitType = $this->Post->get('unit_type');

        try {
            $this->httpFile = ACMS_Http::file($this->uploadFiledName);
            $this->import();
        } catch (Exception $e) {
            $this->Post->set('importMessage', $e->getMessage());
            $this->Post->set('success', 'off');

            AcmsLogger::notice('「' . $this->importType . '」インポートでエラーが発生しました', Common::exceptionArray($e));

            return $this->Post;
        }

        $errorCount = count($this->importErrors);
        $this->Post->set('entryCount', $this->entryCount);
        $this->Post->set('importErrorCount', $errorCount);
        $importErrorsJson = json_encode($this->importErrors);
        $this->Post->set('importErrors', $importErrorsJson !== false ? $importErrorsJson : '[]');

        if ($this->entryCount > 0) {
            $this->Post->set('importMessage', 0);
            $this->Post->set('success', 'on');
            $this->Post->set('blogName', ACMS_RAM::blogName(BID));
        } elseif ($errorCount > 0) {
            $this->Post->set('importMessage', 'すべてのエントリーでエラーが発生しました。');
            $this->Post->set('success', 'off');
        } else {
            $this->Post->set('importMessage', 'インポートできるエントリーがありませんでした。');
            $this->Post->set('success', 'off');
        }

        AcmsLogger::info('「' . $this->importType . '」インポートを実行しました', [
            'success' => $this->entryCount,
            'skipped' => $errorCount,
        ]);

        return $this->Post;
    }

    public function __destruct()
    {
        if ($this->locale !== false) {
            setlocale(LC_ALL, $this->locale);
        }
    }

    /**
     * 次のエントリー表示順を取得
     *
     * @param int $blogId
     *
     * @return int
     **/
    protected function nextEntrySort($blogId)
    {
        $entryRepository = Application::make('entry.repository');
        assert($entryRepository instanceof \Acms\Services\Entry\EntryRepository);
        return $entryRepository->nextSort($blogId);
    }

    /**
     * 次のエントリーのユーザー絞り込み時の表示順を取得
     *
     * @param int $userId
     * @param int $blogId
     * @return int
     **/
    protected function nextEntryUserSort($userId, $blogId)
    {
        $entryRepository = Application::make('entry.repository');
        assert($entryRepository instanceof \Acms\Services\Entry\EntryRepository);
        return $entryRepository->nextUserSort($userId, $blogId);
    }

    /**
     * 次のエントリーのカテゴリー絞り込み時の表示順を取得
     *
     * @param int|null $categoryId
     * @param int $blogId
     *
     * @return int
     **/
    protected function nextEntryCategorySort($categoryId, $blogId)
    {
        $entryRepository = Application::make('entry.repository');
        assert($entryRepository instanceof \Acms\Services\Entry\EntryRepository);
        return $entryRepository->nextCategorySort($categoryId, $blogId);
    }

    /**
     * エントリーを挿入
     *
     * @param array<string, mixed> $entry
     * @return void
     */
    public function insertEntry(array $entry)
    {
        $DB     = DB::singleton(dsn());
        $eid    = $DB->query(SQL::nextval('entry_id', dsn()), 'seq');
        $cid    = null;
        $ecode  = Entry::generateEntryCode($eid);
        if (isset($this->importCid) && !empty($this->importCid) && $this->importCid != 0) {
            if ($this->importCid > 0) {
                $cid = $this->importCid;
            } else {
                $cid = null;
            }
        }
        if (isset($entry['ecode']) && $entry['ecode'] !== '') {
            $ecode = Entry::formatEntryCode($entry['ecode']);
        }

        $status         = $entry['status'];
        $contents       = $entry['content'];
        $summaryRange   = (count($contents) > 1) ? 1 : null;

        // units
        $this->insertUnit($eid, $contents);

        // category
        if (isset($entry['category']) && $entry['category'] !== '') {
            $cid = $this->insertCategory($entry['category']);
        }

        $posted_datetime = date('Y-m-d H:i:s');
        $second = sprintf('%02d', rand(1, 59));
        $posted_datetime = preg_replace('@[0-9]{2}$@', $second, $posted_datetime);

        $SQL    = SQL::newInsert('entry');
        $row    = [
            'entry_id'                  => $eid,
            'entry_posted_datetime'     => $posted_datetime,
            'entry_updated_datetime'    => $entry['date'],
            'entry_summary_range'       => $summaryRange,
            'entry_category_id'         => $cid,
            'entry_user_id'             => SUID,
            'entry_blog_id'             => BID,
            'entry_code'                => $ecode,
            'entry_sort'                => $this->nextEntrySort(BID),
            'entry_user_sort'           => $this->nextEntryUserSort(SUID, BID), // @phpstan-ignore-line
            'entry_category_sort'       => $this->nextEntryCategorySort($cid, BID),
            'entry_status'              => $status,
            'entry_title'               => $entry['title'],
            'entry_link'                => '',
            'entry_datetime'            => $entry['date'],
            'entry_hash'                => md5(SYSTEM_GENERATED_DATETIME . $posted_datetime),
        ];

        foreach ($row as $key => $val) {
            $SQL->addInsert($key, $val);
        }
        $DB->query($SQL->get(dsn()), 'exec');

        // tag
        if (isset($entry['tags']) && !empty($entry['tags'])) {
            $this->insertTag($eid, $entry);
        }

        // field
        if (isset($entry['fields']) && !empty($entry['fields'])) {
            $this->insertField($eid, $entry);
        }

        // fulltext
        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
        $this->entryCount++;

        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, 0]);
        }
    }

    /**
     * ユニットを挿入
     *
     * @param int $eid
     * @param string[] $contents
     * @return void
     */
    protected function insertUnit($eid, array $contents = [])
    {
        $sql = SQL::newBulkInsert('column');
        $sql->addColumn('column_id');
        $sql->addColumn('column_sort');
        $sql->addColumn('column_type');
        $sql->addColumn('column_entry_id');
        $sql->addColumn('column_blog_id');
        $sql->addColumn('column_field_1');
        if ($this->unitType === 'text') {
            $sql->addColumn('column_field_2');
        }
        for ($i = 0; $i < count($contents); $i++) {
            $data = [
                'column_id' => $this->generateNewIdTrait(),
                'column_sort' => $i + 1,
                'column_type' => $this->unitType,
                'column_entry_id' => $eid,
                'column_blog_id' => BID,
                'column_field_1' => $contents[$i],
            ];
            if ($this->unitType === 'text') {
                $data['column_field_2'] = 'p';
            }
            $sql->addInsert($data);
        }
        if ($sql->hasData()) {
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    /**
     * タグを挿入
     *
     * @param int $eid
     * @param array<string, mixed> $entry
     * @return void
     */
    protected function insertTag($eid, array $entry)
    {
        $tags = $entry['tags'];
        $tags = array_map('trim', $tags);
        $tags = array_map(function ($tag) {
            $normalizedTag = preg_replace('/[ 　]+/u', '_', $tag);
            if ($normalizedTag !== null) {
                return $normalizedTag;
            }
            return $tag;
        }, $tags);
        $tags = array_filter($tags, function ($tag) {
            return !isReserved($tag);
        });
        $tags = array_filter($tags, function ($tag) {
            return !preg_match(REGEX_INVALID_TAG_NAME, $tag);
        });
        $tags = array_filter($tags, function ($tag) {
            return $tag !== '';
        });
        $tags = array_values($tags); // 添字を0から始まる整数に変換
        $tags = array_unique($tags);

        $sql = SQL::newBulkInsert('tag');
        $sql->addColumn('tag_name');
        $sql->addColumn('tag_sort');
        $sql->addColumn('tag_entry_id');
        $sql->addColumn('tag_blog_id');
        foreach ($tags as $sort => $tag) {
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

    /**
     * フィールドを挿入
     *
     * @param int $eid
     * @param array<string, mixed> $entry
     * @return void
     */
    protected function insertField($eid, array $entry)
    {
        Common::deleteField('eid', $eid);

        $sql = SQL::newBulkInsert('field');
        $sql->addColumn('field_key');
        $sql->addColumn('field_value');
        $sql->addColumn('field_type');
        $sql->addColumn('field_sort');
        $sql->addColumn('field_search');
        $sql->addColumn('field_eid');
        $sql->addColumn('field_blog_id');

        foreach ($entry['fields'] as $i => $val) {
            $fieldTypeValue = null;
            if (preg_match('/@(html|media|title)$/', $val['key'], $match)) {
                $fieldTypeValue = $match[1];
            }
            $sql->addInsert([
                'field_key' => $val['key'],
                'field_value' => $val['value'],
                'field_type' => $fieldTypeValue,
                'field_sort' => $i + 1,
                'field_search' => 'on',
                'field_eid' => $eid,
                'field_blog_id' => BID,
            ]);
        }
        if ($sql->hasData()) {
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    /**
     * カテゴリーを挿入
     *
     * @param string $name
     * @return int
     */
    protected function insertCategory($name)
    {
        if (isset($this->categoryList[$name])) {
            return $this->categoryList[$name];
        }

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('category');
        $SQL->addWhereOpr('category_blog_id', BID);
        $SQL->setOrder('category_right', true);
        $SQL->setLimit(1);
        if ($row = $DB->query($SQL->get(dsn()), 'row')) {
            $sort   = $row['category_sort'] + 1;
            $left   = $row['category_right'] + 1;
            $right  = $row['category_right'] + 2;
        } else {
            $sort   = 1;
            $left   = 1;
            $right  = 2;
        }

        $cid = (int) $DB->query(SQL::nextval('category_id', dsn()), 'seq');
        $code = 'category-' . $cid;

        $SQL    = SQL::newInsert('category');
        $SQL->addInsert('category_id', $cid);
        $SQL->addInsert('category_parent', 0);
        $SQL->addInsert('category_sort', $sort);
        $SQL->addInsert('category_left', $left);
        $SQL->addInsert('category_right', $right);
        $SQL->addInsert('category_blog_id', BID);
        $SQL->addInsert('category_status', 'open');
        $SQL->addInsert('category_name', $name);
        $SQL->addInsert('category_scope', 'local');
        $SQL->addInsert('category_indexing', 'on');
        $SQL->addInsert('category_code', $code);
        $DB->query($SQL->get(dsn()), 'exec');

        $this->categoryList[$name] = $cid;

        return $cid;
    }
}
