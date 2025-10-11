<?php

class ACMS_POST_Import extends ACMS_POST
{
    use \Acms\Traits\Unit\UnitModelTrait;

    public const SORT_ENTRY    = 1;
    public const SORT_USER     = 2;
    public const SORT_CATEGORY = 3;

    protected $unitType;
    protected $uploadFiledName;

    /**
     * @var \ACMS_Http_File
     */
    protected $httpFile;
    protected $fileObject;
    protected $locale;
    protected $entryCount = 0;
    protected $categoryList = [];
    protected $importType = '';

    /**
     * @var int|null
     */
    protected $importCid;

    public function init()
    {
    }

    public function import()
    {
    }

    public function post()
    {
        @set_time_limit(0);

        if (!sessionWithCompilation()) {
            return $this->Post;
        }

        $this->locale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'ja_JP.UTF-8');

        $path = null;
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

        $this->Post->set('importMessage', 0);
        $this->Post->set('success', 'on');
        $this->Post->set('blogName', ACMS_RAM::blogName(BID));
        $this->Post->set('entryCount', $this->entryCount);

        AcmsLogger::info('「' . $this->importType . '」インポートを実行しました', [
            'success' => $this->entryCount,
        ]);

        return $this->Post;
    }

    public function __destruct()
    {
        setlocale(LC_ALL, $this->locale);
    }

    function getNextSort($type)
    {
        $next = 0;
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('entry');

        switch ($type) {
            case self::SORT_ENTRY:
                $SQL->setSelect('entry_sort');
                $SQL->addWhereOpr('entry_blog_id', BID);
                $SQL->setOrder('entry_sort', 'DESC');
                break;
            case self::SORT_USER:
                $SQL->setSelect('entry_user_sort');
                $SQL->addWhereOpr('entry_user_id', SUID);
                $SQL->addWhereOpr('entry_blog_id', BID);
                $SQL->setOrder('entry_user_sort', 'DESC');
                break;
            case self::SORT_CATEGORY:
                $SQL->setSelect('entry_category_sort');
                $SQL->addWhereOpr('entry_category_id', $this->importCid);
                $SQL->addWhereOpr('entry_blog_id', BID);
                $SQL->setOrder('entry_category_sort', 'DESC');
                break;
            default:
                return 0;
        }

        $SQL->setLimit(1);
        $next = intval($DB->query($SQL->get(dsn()), 'one')) + 1;

        return $next;
    }

    public function insertEntry($entry)
    {
        $DB     = DB::singleton(dsn());
        $eid    = $DB->query(SQL::nextval('entry_id', dsn()), 'seq');
        $cid    = null;
        $ecode  = config('entry_code_prefix') . $eid . '.html';
        if (isset($this->importCid) && !empty($this->importCid) && $this->importCid != 0) {
            if ($this->importCid > 0) {
                $cid = $this->importCid;
            } else {
                $cid = null;
            }
        }
        if (isset($entry['ecode']) && !empty($entry['ecode'])) {
            $ecode  = $entry['ecode'] . '.html';
        }

        $status         = $entry['status'];
        $contents       = $entry['content'];
        $summaryRange   = (count($contents) > 1) ? 1 : null;

        // units
        $this->insertUnit($eid, $contents);

        // category
        if (isset($entry['category']) && !empty($entry['category'])) {
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
            'entry_sort'                => $this->getNextSort(self::SORT_ENTRY),
            'entry_user_sort'           => $this->getNextSort(self::SORT_USER),
            'entry_category_sort'       => $this->getNextSort(self::SORT_CATEGORY),
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

    public function insertUnit($eid, $contents = [])
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
                'column_entry_id' => intval($eid),
                'column_blog_id' => intval(BID),
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

    public function insertTag($eid, $entry)
    {
        $sql = SQL::newBulkInsert('tag');
        $sql->addColumn('tag_name');
        $sql->addColumn('tag_sort');
        $sql->addColumn('tag_entry_id');
        $sql->addColumn('tag_blog_id');

        $tags = array_unique($entry['tags']);
        foreach ($tags as $sort => $tag) {
            if (isReserved($tag)) {
                continue;
            }
            $tag = preg_replace('/[ 　]+/u', '_', $tag);
            $sql->addInsert([
                'tag_name' => $tag,
                'tag_sort' => intval($sort) + 1,
                'tag_entry_id' => intval($eid),
                'tag_blog_id' => intval(BID),
            ]);
        }
        if ($sql->hasData()) {
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    public function insertField($eid, $entry)
    {
        $DB = DB::singleton(dsn());
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
                'field_eid' => intval($eid),
                'field_blog_id' => BID,
            ]);
        }
        if ($sql->hasData()) {
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    public function insertCategory($name, $_code = null)
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

        $cid    = $DB->query(SQL::nextval('category_id', dsn()), 'seq');
        if ($_code) {
            $code = $_code;
        } else {
            $code = 'category-' . $cid;
        }
        $name   = $name;

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
