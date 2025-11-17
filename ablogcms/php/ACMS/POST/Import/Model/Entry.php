<?php

class ACMS_POST_Import_Model_Entry extends ACMS_POST_Import_Model
{
    use \Acms\Traits\Unit\UnitModelTrait;

    protected $entry;
    protected $units;
    protected $fields;
    protected int $importBid = BID;
    protected $subCategories = [];
    protected $geoLat = 0;
    protected $geoLng = 0;
    protected $geoZoom = 11;
    protected $tags = [];
    protected $idLabel = 'entry_id';

    /**
     * set target category id
     *
     * @param int|null $cid
     */
    public function setTargetCid($cid)
    {
        $this->importCid = $cid;
    }

    /**
     * set target blog id
     *
     * @param int $bid
     */
    public function setTargetBid($bid)
    {
        $this->importBid = $bid;
    }

    function exist()
    {
        return ACMS_RAM::entryBlog($this->csvId) == $this->importBid && !!ACMS_RAM::entryCode($this->csvId) && ACMS_RAM::entryStatus($this->csvId) !== 'trash';
    }

    function nextId()
    {
        $DB = DB::singleton(dsn());
        $this->nextId = intval($DB->query(SQL::nextval('entry_id', dsn()), 'seq'));
    }

    function saveEntry()
    {
        $this->formatCheck();
        $this->updateKey();
        $this->build();

        if ($this->isUpdate) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    function formatCheck()
    {
        foreach ($this->data as $key => $value) {
            switch ($key) {
                case 'entry_id':
                case 'entry_summary_range':
                case 'entry_category_id':
                case 'entry_user_id':
                    if (!is_numeric($value)) {
                        throw new \RuntimeException('数値でない値が設定されています（' . $key . '）');
                    }
                    break;
                case 'entry_status':
                    if (!in_array($value, ['open', 'close', 'draft', 'trash'], true)) {
                        throw new \RuntimeException('不正な値が設定されています（' . $key . '）');
                    }
                    break;
                case 'entry_datetime':
                case 'entry_updated_datetime':
                case 'entry_start_datetime':
                case 'entry_end_datetime':
                case 'entry_posted_datetime':
                    if (!preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                        throw new \RuntimeException('日時のフォーマットが間違っています（' . $key . '）');
                    }
                    break;
                case 'entry_indexing':
                    if (!in_array($value, ['on', 'off'], true)) {
                        throw new \RuntimeException('on または off 以外の値が設定されています（' . $key . '）');
                    }
                    break;
                case 'entry_members_only':
                    if (!in_array($value, ['on', 'off'], true)) {
                        throw new \RuntimeException('on または off 以外の値が設定されています（' . $key . '）');
                    }
                    break;
            }
        }
    }

    function updateKey()
    {
        // プロ版以上限定
        if (!editionWithProfessional()) {
            return false;
        }
        $updateKey = null;

        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('field');
        $SQL->addSelect('field_eid');
        $SQL->addWhereOpr('field_blog_id', $this->importBid);

        foreach ($this->labels as $key) {
            if (strpos($key, 'unit@') === 0) {
                continue;
            }
            if (preg_match('/^\*/', $key)) {
                $updateKey = ltrim($key, '*');
                break;
            }
        }
        if (isset($this->data['*' . $updateKey])) {
            $SQL->addWhereOpr('field_key', $updateKey);
            $SQL->addWhereOpr('field_value', $this->data['*' . $updateKey]);
            $all = $DB->query($SQL->get(dsn()), 'all');

            if (count($all) === 1) {
                $eid = $all[0]['field_eid'];
                $this->csvId = $eid;
                $this->isUpdate = true;
            } elseif (count($all) > 1) {
                throw new RuntimeException('重複するキーがあったためこのエントリーのインポートを中止しました。');
            }
        }
    }

    function insert()
    {
        $this->_insertEntry();
        $this->_insertSubCategory($this->nextId);
        $this->_insertTag($this->nextId);
        $this->_insertGeo($this->nextId);
        $this->_insertUnit();
        $this->_insertField();
        Common::saveFulltext('eid', $this->nextId, Common::loadEntryFulltext($this->nextId));
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$this->nextId, 0]);
        }
    }

    function update()
    {
        $this->_updateEntry();
        $this->_insertSubCategory($this->csvId);
        $this->_insertTag($this->csvId);
        $this->_insertGeo($this->csvId);
        $this->_updateUnit();
        $this->_updateField();
        Common::saveFulltext('eid', $this->csvId, Common::loadEntryFulltext($this->csvId), ACMS_RAM::entryBlog($this->csvId));
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$this->csvId, 0]);
        }
    }

    function _insertEntry()
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newInsert('entry');
        foreach ($this->entry as $key => $val) {
            $SQL->addInsert($key, $val);
        }
        $DB->query($SQL->get(dsn()), 'exec');
    }

    function _updateEntry()
    {
        $DB = DB::singleton(dsn());
        $eid = $this->csvId;

        if (!ACMS_RAM::entryStatus($eid)) {
            throw new RuntimeException('Not Found Entry.');
        }

        unset(
            $this->entry['entry_id'],
            $this->entry['entry_sort'],
            $this->entry['entry_user_sort'],
            $this->entry['entry_category_sort'],
            $this->entry['entry_hash'],
            $this->entry['entry_blog_id']
        );

        if (!empty($this->entry)) {
            $SQL    = SQL::newUpdate('entry');
            foreach ($this->entry as $key => $val) {
                $SQL->addUpdate($key, $val);
            }
            $SQL->addWhereOpr('entry_id', $eid);
            $SQL->addWhereOpr('entry_blog_id', $this->importBid);
            $DB->query($SQL->get(dsn()), 'exec');
            ACMS_RAM::entry($eid, null);
        }
    }

    function _insertSubCategory($eid)
    {
        if (empty($this->subCategories)) {
            return;
        }
        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('entry_sub_category');
        $SQL->addWhereOpr('entry_sub_category_eid', $eid);
        $DB->query($SQL->get(dsn()), 'exec');

        $sql = SQL::newBulkInsert('entry_sub_category');
        foreach ($this->subCategories as $cid) {
            $sql->addInsert([
                'entry_sub_category_eid' => $eid,
                'entry_sub_category_id' => $cid,
                'entry_sub_category_blog_id' => $this->importBid,
            ]);
        }
        if ($sql->hasData()) {
            $DB->query($sql->get(dsn()), 'exec');
        }
    }

    function _insertTag($eid)
    {
        if (empty($this->tags)) {
            return;
        }
        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('tag');
        $SQL->addWhereOpr('tag_entry_id', $eid);
        $DB->query($SQL->get(dsn()), 'exec');

        $sql = SQL::newBulkInsert('tag');
        foreach ($this->tags as $sort => $tag) {
            $sql->addInsert([
                'tag_name' => $tag,
                'tag_sort' => $sort + 1,
                'tag_entry_id' => $eid,
                'tag_blog_id' => $this->importBid,
            ]);
        }
        if ($sql->hasData()) {
            $DB->query($sql->get(dsn()), 'exec');
        }
    }

    function _insertGeo($eid)
    {
        if (empty($this->geoLat) || empty($this->geoLng)) {
            return;
        }
        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('geo');
        $SQL->addWhereOpr('geo_eid', $eid);
        $DB->query($SQL->get(dsn()), 'exec');

        $SQL = SQL::newInsert('geo');
        $SQL->addInsert('geo_geometry', SQL::newGeometry($this->geoLat, $this->geoLng));
        $SQL->addInsert('geo_zoom', intval($this->geoZoom));
        $SQL->addInsert('geo_eid', $eid);
        $SQL->addInsert('geo_blog_id', $this->importBid);
        $DB->query($SQL->get(dsn()), 'exec');
    }

    function _insertUnit()
    {
        if (!empty($this->units)) {
            $sql = SQL::newBulkInsert('column');
            foreach ($this->units as $cval) {
                $cval['column_id'] = $this->generateNewIdTrait();
                $sql->addInsert($cval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    function _updateUnit()
    {
        $eid = $this->csvId;

        if (!empty($this->units)) {
            $SQL = SQL::newDelete('column');
            $SQL->addWhereOpr('column_entry_id', $eid);
            $SQL->addWhereOpr('column_type', 'text');
            DB::query($SQL->get(dsn()), 'exec');

            $sql = SQL::newBulkInsert('column');
            foreach ($this->units as $cval) {
                $cval['column_id'] = $this->generateNewIdTrait();
                $cval['column_entry_id'] = $eid;
                $sql->addInsert($cval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    function _insertField()
    {
        $eid = $this->nextId;

        if (!empty($this->fields)) {
            Common::deleteField('eid', $eid);

            $sql = SQL::newBulkInsert('field');
            foreach ($this->fields as $fval) {
                $sql->addInsert($fval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    function _updateField()
    {
        $eid = $this->csvId;

        if (!empty($this->fields)) {
            $fkey = [];
            $SQL = SQL::newDelete('field');
            $SQL->addWhereOpr('field_eid', $eid);
            foreach ($this->fields as $dval) {
                foreach ($dval as $key => $val) {
                    if ($key === 'field_key') {
                        $fkey[] = $val;
                    }
                }
            }
            $SQL->addWhereIn('field_key', $fkey);
            DB::query($SQL->get(dsn()), 'exec');
            Common::deleteFieldCache('eid', $eid);

            $sql = SQL::newBulkInsert('field');
            foreach ($this->fields as $fval) {
                $fval['field_eid'] = $eid;
                $fval['field_blog_id'] = ACMS_RAM::entryBlog($eid);
                $sql->addInsert($fval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    function getPostedDatetime()
    {
        $posted_datetime = date('Y-m-d H:i:s');
        $second = sprintf('%02d', rand(1, 59));
        $posted_datetime = preg_replace('@[0-9]{2}$@', $second, $posted_datetime);

        return $posted_datetime;
    }

    function build()
    {
        $this->entry = $this->entryBase();
        $unit = $this->unitBase();
        $field = $this->fieldBase();

        foreach ($this->data as $key => $value) {
            if ($key === 'entry_id' && $this->isUpdate) {
                $this->entry['entry_id'] = $this->csvId;
                $unit['column_entry_id'] = $this->csvId;
                $field['field_eid'] = $this->csvId;
            }
            if (array_key_exists($key, $this->entry)) {
                $this->buildEntry($key, $value);
            } elseif ($key === 'entry_sub_category') {
                $this->buildSubCategory($value);
            } elseif ($key === 'entry_tag') {
                $this->buildTag($value);
            } elseif (strpos($key, 'unit@') === 0) {
                $this->buildUnit($unit, $key, $value);
            } elseif (in_array($key, ['geo_lat', 'geo_lng', 'geo_zoom'], true)) {
                $this->buildGeo($key, $value);
            } else {
                $this->buildField($field, $key, $value);
            }
        }

        if (!$this->isUpdate) {
            // 新規作成の場合はソート番号を設定（ブログ・カテゴリー・ユーザーの決定後に設定する必要がある）
            if (!is_int($this->entry['entry_sort'])) {
                $this->entry['entry_sort'] = $this->nextEntrySort($this->entry['entry_blog_id']);
            }
            if (!is_int($this->entry['entry_user_sort'])) {
                $this->entry['entry_user_sort'] = $this->nextEntryUserSort($this->entry['entry_user_id'], $this->entry['entry_blog_id']);
            }
            if (!is_int($this->entry['entry_category_sort'])) {
                $this->entry['entry_category_sort'] = $this->nextEntryCategorySort($this->entry['entry_category_id'], $this->entry['entry_blog_id']);
            }
        }

        // アップデートの場合は余分なベース情報を削除
        if ($this->isUpdate) {
            foreach ($this->entry as $key => $value) {
                if (!isset($this->data[$key])) {
                    unset($this->entry[$key]);
                }
            }
        }
    }

    function buildEntry($key, $value)
    {
        switch ($key) {
            case 'entry_id':
            case 'entry_blog_id':
                break;
            case 'entry_datetime':
            case 'entry_updated_datetime':
            case 'entry_start_datetime':
            case 'entry_end_datetime':
                if (preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                    $this->entry[$key] = $value;
                }
                break;
            case 'entry_code':
                $value = preg_replace('@\.([^\.]+)$@', '', $value);
                $this->entry[$key] = $value . $this->getExtension();
                break;
            case 'entry_posted_datetime':
                if (preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                    $this->entry[$key] = $value;
                    $this->entry['entry_hash'] = md5(SYSTEM_GENERATED_DATETIME . $value);
                }
                break;
            case 'entry_category_id':
                $ccode = ACMS_RAM::categoryCode($value);
                if (empty($ccode)) {
                    $this->entry[$key] = null;
                } else {
                    $this->entry[$key] = $value;
                }
                break;
            default:
                $this->entry[$key] = $value;
        }
    }

    function buildGeo($key, $value)
    {
        switch ($key) {
            case 'geo_lat':
                $this->geoLat = $value;
                break;
            case 'geo_lng':
                $this->geoLng = $value;
                break;
            case 'geo_zoom':
                $this->geoZoom = $value;
                break;
        }
    }

    function buildSubCategory($value)
    {
        $subCategoryIds = explode(',', $value);
        if ($subCategoryIds === false) {
            return;
        }
        if (count($subCategoryIds) === 0) {
            return;
        }
        foreach ($subCategoryIds as $cid) {
            $cid = intval(trim($cid));
            if (empty($cid)) {
                continue;
            }
            $this->subCategories[] = $cid;
        }
    }

    function buildTag($value)
    {
        $tags = Common::getTagsFromString($value);
        if (empty($tags)) {
            return;
        }
        foreach ($tags as $sort => $tag) {
            $this->tags[] = $tag;
        }
    }

    function buildUnit($unit, $key, $value)
    {
        $type   = substr($key, strlen('unit@'));
        $sort   = 1;
        if (preg_match('@\[\d+\]$@', $type, $matchs)) {
            $sort   = intval(preg_replace('@\[|\]@', '', $matchs[0]));
            $type    = preg_replace('@\[\d+\]$@', '', $type);
        }
        if ($type === null) {
            return;
        }
        if ($type === 'block-editor') {
            $unit['column_type'] = 'block-editor';
            $unit['column_align'] = '';
            $unit['column_field_2'] = '';
        } else {
            $tokens = preg_split('@(#|\.)@', $type, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($tokens === false) {
                return;
            }
            $unit['column_field_2'] = array_shift($tokens);
            $id = '';
            $class  = '';
            while ($mark = array_shift($tokens)) {
                if (!$val = array_shift($tokens)) {
                    continue;
                }
                if ('#' == $mark) {
                    $id = $val;
                } else {
                    $class  = $val;
                }
            }

            $attr   = '';
            if (!empty($id)) {
                $attr .= ' id="' . $id . '"';
            }
            if (!empty($class)) {
                $attr .= ' class="' . $class . '"';
            }
            if (!empty($attr)) {
                $unit['column_attr'] = $attr;
            }
        }
        $unit['column_sort']      = $sort;
        $unit['column_field_1']   = $value;

        $this->units[] = $unit;
    }

    function buildField($field, $key, $value)
    {
        $sort = 1;
        if (preg_match('@\[\d+\]$@', $key, $matchs)) {
            $sort = intval(preg_replace('@\[|\]@', '', $matchs[0]));
            $key = preg_replace('@\[\d+\]$@', '', $key);
        }
        $fieldTypeValue = null;
        if (preg_match('/@(html|media|title)$/', $key, $matches)) {
            $fieldTypeValue = $matches[1];
        }
        $field['field_key'] = ltrim($key, '*');
        $field['field_type'] = $fieldTypeValue;
        $field['field_value'] = $value;
        $field['field_sort'] = $sort;

        $this->fields[] = $field;
    }

    function entryBase()
    {
        $posted_datetime = $this->getPostedDatetime();

        return [
            'entry_id'              => $this->nextId,
            'entry_code'            => config('entry_code_prefix') . $this->nextId . $this->getExtension(),
            'entry_status'          => 'open',
            'entry_sort'            => null,
            'entry_user_sort'       => null,
            'entry_category_sort'   => null,
            'entry_title'           => 'CSV_IMPORT-' . $this->nextId,
            'entry_link'            => '',
            'entry_datetime'        => $posted_datetime,
            'entry_start_datetime'  => '1000-01-01 00:00:00',
            'entry_end_datetime'    => '9999-12-31 23:59:59',
            'entry_posted_datetime' => $posted_datetime,
            'entry_updated_datetime' => $posted_datetime,
            'entry_hash'            => md5(SYSTEM_GENERATED_DATETIME . $posted_datetime),
            'entry_summary_range'   => null,
            'entry_indexing'        => 'on',
            'entry_members_only'    => 'off',
            'entry_primary_image'   => null,
            'entry_category_id'     => $this->importCid,
            'entry_user_id'         => SUID,
            'entry_blog_id'         => $this->importBid,
        ];
    }

    function unitBase()
    {
        return [
            'column_id'         => $this->generateNewIdTrait(),
            'column_sort'       => 0,
            'column_align'      => 'auto',
            'column_type'       => 'text',
            'column_attr'       => '',
            'column_size'       => '',
            'column_field_1'    => '',
            'column_field_2'    => 'p',
            'column_field_3'    => '',
            'column_field_4'    => '',
            'column_field_5'    => '',
            'column_entry_id'   => $this->nextId,
            'column_blog_id'    => $this->importBid,
        ];
    }

    function fieldBase()
    {
        return [
            'field_key'     => null,
            'field_value'   => null,
            'field_sort'    => 1,
            'field_search'  => 'on',
            'field_eid'     => $this->nextId,
            'field_blog_id' => $this->importBid,
        ];
    }

    protected function getExtension()
    {
        $extension = config('entry_code_extension');

        return empty($extension) ? '' : '.' . $extension;
    }
}
