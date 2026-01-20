<?php

/**
 * エントリーCSVインポート用モデルクラス
 *
 * エントリーのCSVインポート処理を実装するクラス
 */
class ACMS_POST_Import_Model_Entry extends ACMS_POST_Import_Model
{
    use \Acms\Traits\Unit\UnitModelTrait;

    /** @var array<string, mixed> エントリーデータ */
    protected array $entry = [];

    /** @var array<int, array<string, mixed>> ユニットデータ配列 */
    protected array $units = [];

    /** @var array<int, array<string, mixed>> フィールドデータ配列 */
    protected array $fields = [];

    /** @var int インポート先ブログID */
    protected int $importBid = BID;

    /** @var array<int> サブカテゴリーID配列 */
    protected array $subCategories = [];

    /** @var float 緯度 */
    protected float $geoLat = 0.0;

    /** @var float 経度 */
    protected float $geoLng = 0.0;

    /** @var int 地図のズームレベル */
    protected int $geoZoom = 11;

    /** @var array<int, string> タグ配列 */
    protected array $tags = [];

    /** @var string IDラベル名 */
    protected string $idLabel = 'entry_id';

    /**
     * インポート先カテゴリーIDを設定
     *
     * @param int|null $cid カテゴリーID
     * @return void
     */
    public function setTargetCid(?int $cid): void
    {
        $this->importCid = $cid;
    }

    /**
     * インポート先ブログIDを設定
     *
     * @param int $bid ブログID
     * @return void
     */
    public function setTargetBid(int $bid): void
    {
        $this->importBid = $bid;
    }

    /**
     * エントリーの存在チェック
     *
     * CSVから取得したIDが存在し、更新可能な状態かを確認する
     *
     * @return bool 存在し更新可能な場合true
     */
    protected function exist(): bool
    {
        if (is_null($this->csvId)) {
            return false;
        }
        $entryBlogId = ACMS_RAM::entryBlog($this->csvId);
        $entryCode = ACMS_RAM::entryCode($this->csvId);
        $entryStatus = ACMS_RAM::entryStatus($this->csvId);
        if ($entryBlogId !== BID) {
            // 実行ブログに存在しないエントリーは更新できない
            return false;
        }
        if ($entryCode === null) {
            // コードが null = 存在しないエントリー
            return false;
        }
        if ($entryStatus === null || $entryStatus === '' || $entryStatus === 'trash') {
            // ステータスが存在しない、または削除されたエントリーは更新できない
            return false;
        }
        return true;
    }

    /**
     * 次発行されるエントリーIDを設定
     *
     * @return void
     */
    protected function nextId(): void
    {
        $DB = DB::singleton(dsn());
        $this->nextId = intval($DB->query(SQL::nextval('entry_id', dsn()), 'seq'));
    }

    /**
     * 保存処理
     *
     * フォーマットチェック、更新キー処理、データ組み立てを行い、更新または挿入を実行する
     *
     * @return void
     * @throws RuntimeException フォーマットエラーまたは重複キーエラー時
     */
    public function save(): void
    {
        $this->formatCheck();
        $this->updateKey();
        $this->build();

        if ($this->isUpdate) {
            $this->update();
        } else {
            $this->insert();
        }
    }

    /**
     * データフォーマットの検証
     *
     * 各フィールドの値が適切なフォーマットかを検証する
     *
     * @return void
     * @throws RuntimeException フォーマットが不正な場合
     */
    protected function formatCheck(): void
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

    /**
     * 更新キーの処理
     *
     * プロ版限定機能。フィールドキーを使用してエントリーを特定し、更新対象として設定する
     *
     * @return bool 処理が実行された場合true、プロ版でない場合はfalse
     * @throws RuntimeException 重複するキーが見つかった場合
     */
    protected function updateKey(): bool
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
        return false;
    }

    /**
     * エントリーデータの挿入
     *
     * エントリー本体、サブカテゴリー、タグ、位置情報、ユニット、フィールドを挿入する
     *
     * @return void
     */
    protected function insert(): void
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

    /**
     * エントリーデータの更新
     *
     * エントリー本体、サブカテゴリー、タグ、位置情報、ユニット、フィールドを更新する
     *
     * @return void
     * @throws RuntimeException エントリーが見つからない場合
     */
    protected function update(): void
    {
        if ($this->csvId === null) {
            throw new RuntimeException('更新対象のエントリーIDが設定されていません。');
        }
        $eid = $this->csvId;
        $this->_updateEntry($eid);
        $this->_insertSubCategory($eid);
        $this->_insertTag($eid);
        $this->_insertGeo($eid);
        $this->_updateUnit();
        $this->_updateField();
        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid), ACMS_RAM::entryBlog($eid));
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, 0]);
        }
    }

    /**
     * エントリー本体を挿入
     *
     * @return void
     */
    private function _insertEntry(): void
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newInsert('entry');
        foreach ($this->entry as $key => $val) {
            $SQL->addInsert($key, $val);
        }
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * エントリー本体を更新
     *
     * @param int $eid エントリーID
     * @return void
     * @throws RuntimeException エントリーが見つからない場合
     */
    private function _updateEntry(int $eid): void
    {
        $DB = DB::singleton(dsn());

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

    /**
     * サブカテゴリーを挿入
     *
     * @param int $eid エントリーID
     * @return void
     */
    private function _insertSubCategory(int $eid): void
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

    /**
     * タグを挿入
     *
     * @param int $eid エントリーID
     * @return void
     */
    private function _insertTag(int $eid): void
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

    /**
     * 位置情報を挿入
     *
     * @param int $eid エントリーID
     * @return void
     */
    private function _insertGeo(int $eid): void
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

    /**
     * ユニットを挿入
     *
     * @return void
     */
    private function _insertUnit(): void
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

    /**
     * ユニットを更新
     *
     * @return void
     */
    private function _updateUnit(): void
    {
        if ($this->csvId === null) {
            return;
        }
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

    /**
     * フィールドを挿入
     *
     * @return void
     */
    private function _insertField(): void
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

    /**
     * フィールドを更新
     *
     * @return void
     */
    private function _updateField(): void
    {
        if ($this->csvId === null) {
            return;
        }
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

    /**
     * 投稿日時を取得
     *
     * 現在日時にランダムな秒数を設定した日時を返す
     *
     * @return string 日時文字列（Y-m-d H:i:s形式）
     */
    private function getPostedDatetime(): string
    {
        $posted_datetime = date('Y-m-d H:i:s');
        $second = sprintf('%02d', rand(1, 59));
        $result = preg_replace('@[0-9]{2}$@', $second, $posted_datetime);
        if ($result === null) {
            return $posted_datetime;
        }

        return $result;
    }

    /**
     * エントリーデータの組み立て
     *
     * CSVデータからエントリー、ユニット、フィールドのデータを組み立てる
     *
     * @return void
     */
    protected function build(): void
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

    /**
     * エントリーフィールドの組み立て
     *
     * @param string $key フィールドキー
     * @param string $value フィールド値
     * @return void
     */
    private function buildEntry(string $key, string $value): void
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
                $cid = intval($value);
                $ccode = ACMS_RAM::categoryCode($cid);
                if (empty($ccode)) {
                    $this->entry[$key] = null;
                } else {
                    $this->entry[$key] = $cid;
                }
                break;
            default:
                $this->entry[$key] = $value;
        }
    }

    /**
     * 位置情報の組み立て
     *
     * @param string $key 位置情報キー（geo_lat, geo_lng, geo_zoom）
     * @param string $value 位置情報値
     * @return void
     */
    private function buildGeo(string $key, string $value): void
    {
        switch ($key) {
            case 'geo_lat':
                $this->geoLat = (float)$value;
                break;
            case 'geo_lng':
                $this->geoLng = (float)$value;
                break;
            case 'geo_zoom':
                $this->geoZoom = (int)$value;
                break;
        }
    }

    /**
     * サブカテゴリーの組み立て
     *
     * @param string $value カンマ区切りのカテゴリーID文字列
     * @return void
     */
    private function buildSubCategory(string $value): void
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

    /**
     * タグの組み立て
     *
     * @param string $value タグ文字列
     * @return void
     */
    private function buildTag(string $value): void
    {
        $tags = Common::getTagsFromString($value);
        if (empty($tags)) {
            return;
        }
        foreach ($tags as $sort => $tag) {
            $this->tags[] = $tag;
        }
    }

    /**
     * ユニットの組み立て
     *
     * @param array<string, mixed> $unit ユニットベースデータ
     * @param string $key ユニットキー（unit@で始まる）
     * @param string $value ユニット値
     * @return void
     */
    private function buildUnit(array $unit, string $key, string $value): void
    {
        $type   = substr($key, strlen('unit@'));
        $sort   = 1;
        if (preg_match('@\[\d+\]$@', $type, $matchs)) {
            $sort   = intval(preg_replace('@\[|\]@', '', $matchs[0]));
            $type    = preg_replace('@\[\d+\]$@', '', $type);
        }
        if ($type === null || $type === '') {
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

    /**
     * フィールドの組み立て
     *
     * @param array<string, mixed> $field フィールドベースデータ
     * @param string $key フィールドキー
     * @param string $value フィールド値
     * @return void
     */
    private function buildField(array $field, string $key, string $value): void
    {
        $sort = 1;
        if (preg_match('@\[\d+\]$@', $key, $matchs)) {
            $sort = intval(preg_replace('@\[|\]@', '', $matchs[0]));
            $key = preg_replace('@\[\d+\]$@', '', $key);
        }
        if ($key === null || $key === '') {
            return;
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

    /**
     * エントリーベースデータを取得
     *
     * @return array<string, mixed> エントリーベースデータ
     */
    private function entryBase(): array
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

    /**
     * ユニットベースデータを取得
     *
     * @return array<string, mixed> ユニットベースデータ
     */
    private function unitBase(): array
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

    /**
     * フィールドベースデータを取得
     *
     * @return array<string, mixed> フィールドベースデータ
     */
    private function fieldBase(): array
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

    /**
     * エントリーコードの拡張子を取得
     *
     * @return string 拡張子（先頭にドットを含む）
     */
    protected function getExtension(): string
    {
        $extension = config('entry_code_extension');

        return empty($extension) ? '' : '.' . $extension;
    }
}
