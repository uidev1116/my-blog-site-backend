<?php

namespace Acms\Services\Entry;

use SQL;
use DB;
use ACMS_Filter;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\BlockEditor;
use Acms\Services\Facades\Application;
use Symfony\Component\Yaml\Yaml;

class Import
{
    /**
     * @var array
     */
    protected $yaml;

    /**
     * @var int
     */
    protected $bid;

    /**
     * @var int
     */
    protected $uid;

    /**
     * @var string
     */
    protected $distPath;

    /**
     * @var array
     */
    protected $ids;

    /**
     * @var array
     */
    protected $errors;

    /**
     * @var array
     */
    protected $mediaFieldFix = [];

    /**
     * @var int
     */
    protected $entrySort = 0;

    /**
     * @var string
     */
    protected $entryStatus = '';

    /**
     * @var int
     */
    protected $userSort = 0;

    /**
     * @var \Acms\Services\Entry\EntryRepository $entryRepository
     */
    protected $entryRepository;

    /**
     * Import constructor
     */
    public function __construct()
    {
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        if ($sessionUserId === null) {
            throw new \LogicException('You must be logged in to perform this action.');
        }
        $this->uid = $sessionUserId;
        $this->entryRepository = Application::make('entry.repository');
    }

    /**
     * import blog data
     *
     * @param int $bid
     * @param string $yaml
     * @param string $distPath
     * @param string $status
     *
     * @return array
     */
    public function run($bid, $yaml, $distPath, $status = '')
    {
        $this->bid = $bid;
        $this->yaml = Yaml::parse($yaml);
        $this->distPath = $distPath;
        $this->ids = [];
        $this->errors = [];
        $this->entryStatus = $status;

        $this->entrySort = $this->entryRepository->nextSort($this->bid);

        $this->userSort = $this->entryRepository->nextUserSort($this->uid, $this->bid);

        $this->registerNewIDs();

        $tables = [
            'entry',
            'column',
            'field',
            'tag',
            'entry_sub_category',
            'media',
            'media_tag',
            'geo',
        ];
        foreach ($tables as $table) {
            $this->insertData($table);
        }
        return $this->errors;
    }

    /**
     * @param string $table
     *
     * @return void
     */
    private function insertData($table)
    {
        if (!$this->existsYaml($table)) {
            return;
        }
        foreach ($this->yaml[$table] as $record) {
            $sql = SQL::newInsert($table);
            foreach ($record as $field => $value) {
                $value = $this->fix($table, $field, $value);
                $method = $table . 'Fix';
                if (is_callable([$this, $method])) {
                    /** @var callable $callback */
                    $callback = [$this, $method];
                    $value = call_user_func_array($callback, [$field, $value, $record]);
                }
                if ($value !== false) {
                    $sql->addInsert($field, $value);
                }
            }
            // geo テーブルの場合、geo_lat と geo_lng から geo_geometry を再構築
            if ($table === 'geo') {
                $lat = $record['geo_lat'] ?? null;
                $lng = $record['geo_lng'] ?? null;
                if ($lat !== null && $lng !== null) {
                    $sql->addInsert('geo_geometry', SQL::newGeometry($lat, $lng));
                } else {
                    continue; // 位置情報がない場合はスキップ
                }
            }
            try {
                DB::query($sql->get(dsn()), 'exec');

                if ($table === 'entry') {
                    if ($eid = $this->getNewID('entry', $record['entry_id'])) {
                        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
                    }
                }
            } catch (\Exception $e) {
                $this->errors[] = $e->getMessage();
            }
        }
        foreach ($this->mediaFieldFix as $data) {
            $sql = SQL::newUpdate('field');
            $sql->addUpdate('field_value', $data['value']);
            $sql->addWhereOpr('field_key', $data['name']);
            $sql->addWhereOpr('field_eid', $data['eid']);
            $sql->addWhereOpr('field_sort', $data['sort']);
            $sql->addWhereOpr('field_blog_id', $this->bid);
            DB::query($sql->get(dsn()), 'exec');
            Common::deleteFieldCache('eid', $data['eid']);
        }
    }

    /**
     * @param string $table
     * @param string $field
     * @param string|null $value
     *
     * @return int|null
     */
    private function fix($table, $field, $value)
    {
        $key = substr($field, strlen($table . '_'));
        if (!is_null($value) && $key === 'id' && $table !== 'entry_sub_category') {
            $value = $this->getNewID($table, $value);
        } elseif (!is_null($value) && $key === 'category_id') {
            $value = $this->getNewID('category', $value);
        } elseif (!is_null($value) && $key === 'user_id') {
            $value = $this->uid;
        } elseif (!is_null($value) && $key === 'entry_id') {
            $value = $this->getNewID('entry', $value);
        } elseif (!is_null($value) && $key === 'module_id') {
            $value = $this->getNewID('module', $value);
        } elseif (!is_null($value) && $key === 'media_id') {
            $value = $this->getNewID('media', $value);
        } elseif (!is_null($value) && $key === 'unit_id') {
            $value = $this->getNewID('column', $value);
        } elseif (!is_null($value) && $key === 'blog_id') {
            $value = $this->bid;
        }

        return $value;
    }

    /**
     * @param string $field
     * @param string|null $value
     * @param array $record
     *
     * @return mixed
     */
    private function entryFix($field, $value, $record)
    {
        if ($field === 'entry_current_rev_id') {
            $value = 0;
        } elseif ($field === 'entry_last_update_user_id') {
            $value = $this->uid;
        } elseif ($field === 'entry_primary_image' && !empty($value)) {
            $value = $this->getNewID('column', $value) ?: 0;
        } elseif ($field === 'entry_form_id') {
            $value = 0;
        } elseif ($field === 'entry_delete_uid') {
            $value = null;
        } elseif ($field === 'entry_sort') {
            $value = $this->entrySort;
            $this->entrySort++;
        } elseif ($field === 'entry_user_sort') {
            $value = $this->userSort;
            $this->userSort;
        } elseif ($field === 'entry_status') {
            if ($this->entryStatus) {
                $value = $this->entryStatus;
            }
        } elseif ($field === 'entry_category_sort') {
            $value = $this->entryRepository->nextCategorySort($record['entry_category_id'], $this->bid);
        } elseif ($field === 'entry_code' && !empty($value)) {
            $sql = SQL::newSelect('entry');
            $sql->setSelect('entry_id');
            $sql->addWhereOpr('entry_code', $value);
            $sql->addWhereOpr('entry_blog_id', $this->bid);
            if (DB::query($sql->get(dsn()), 'one')) {
                $explodeCode = explode('.', $value);
                $length = count($explodeCode);
                if ($length > 1) {
                    $extension = $explodeCode[$length - 1];
                    unset($explodeCode[$length - 1]);
                    $code = implode('.', $explodeCode);
                    $value = $code . '-' . $this->getNewID('entry', $record['entry_id']) . '.' . $extension;
                }
            }
        }
        return $value;
    }

    /**
     * @param string $field
     * @param string|null $value
     * @param array $record
     *
     * @return mixed
     */
    private function columnFix($field, $value, $record)
    {
        $type = detectUnitTypeSpecifier($record['column_type']);

        if ($type === 'media' && $field === 'column_field_1' && $value) {
            $value = $this->getNewID('media', $value) ?: 0;
        } elseif ($type === 'block-editor' && $field === 'column_field_1' && $value) {
            $value = $this->fixBlockEditorMedia($value);
        } elseif ($type === 'module' && $field === 'column_field_1' && !empty($value)) {
            $value = $this->getNewID('module', $value) ?: 0;
        } elseif ($type === 'image' && $field === 'column_field_2' && !empty($value)) {
            $value = $this->distPath . $value;
        } elseif ($type === 'file' && $field === 'column_field_2' && !empty($value)) {
            $value = $this->distPath . $value;
        } elseif ($field === 'column_parent_id' && $value) {
            $value = $this->getNewID('column', $value);
        }
        return $value;
    }

    /**
     * @param string $field
     * @param string|null $value
     * @param array $record
     *
     * @return mixed
     */
    private function entry_sub_categoryFix($field, $value, $record)
    {
        if (!is_null($value) && $field === 'entry_sub_category_eid') {
            $value = $this->getNewID('entry', $value);
        } elseif (!is_null($value) && $field === 'entry_sub_category_id') {
            $value = $this->getNewID('category', $value);
        }
        return $value;
    }

    /**
     * @param string $field
     * @param string|null $value
     * @param array $record
     *
     * @return mixed
     */
    private function fieldFix($field, $value, $record)
    {
        if (!is_null($value) && $field === 'field_eid') {
            $value = $this->getNewID('entry', $value);
        } elseif ($field === 'field_value' && $value) {
            if (preg_match('/@media$/', $record['field_key'])) {
                if ($value = $this->getNewID('media', $value)) {
                    $this->mediaFieldFix[] = [
                        'name' => substr($record['field_key'], 0, -6),
                        'value' => $value,
                        'eid' => $this->getNewID('entry', $record['field_eid']),
                        'sort' => $record['field_sort'],
                    ];
                }
            } elseif ($record['field_type'] === 'block-editor') {
                $value = $this->fixBlockEditorMedia($value);
            } elseif (
                preg_match('/@path$/', $record['field_key']) ||
                preg_match('/@tinyPath$/', $record['field_key']) ||
                preg_match('/@largePath$/', $record['field_key']) ||
                preg_match('/@squarePath$/', $record['field_key'])
            ) {
                $value = $this->distPath . $value;
            }
        }
        return $value;
    }

    private function fixBlockEditorMedia(string $value): string
    {
        return BlockEditor::fixMediaId($value, $this->ids['media'] ?? []);
    }

    /**
     * This method is called dynamically via call_user_func_array().
     *
     * @param string $field
     * @param string|null $value
     * @param array $record
     *
     * @return mixed
     * @phpstan-ignore-next-line
     */
    private function geoFix($field, $value, $record)
    {
        if (!is_null($value) && $field === 'geo_eid') {
            $value = $this->getNewID('entry', $value);
        } elseif ($field === 'geo_lat' || $field === 'geo_lng') {
            // geo_lat と geo_lng はエクスポート時に ST_X/ST_Y で抽出した値
            // geo_geometry は insertData で再構築するため、ここではスキップ
            $value = false;
        }
        return $value;
    }

    /**
     * @param string $field
     * @param string|null $value
     * @param array $record
     *
     * @return mixed
     */
    private function mediaFix($field, $value, $record)
    {
        if ($field === 'media_path') {
            $value =  $this->distPath . $value;
        } elseif ($field === 'media_thumbnail') {
            $value =  $this->distPath . $value;
        } elseif ($field === 'media_original') {
            $value = $this->distPath . $value;
        }
        return $value;
    }

    /**
     * @param string $table
     * @param int|string $id
     *
     * @return int|bool|string|void
     */
    private function getNewID($table, $id)
    {
        if (is_numeric($id) || $table === 'column') {
            if (!isset($this->ids[$table][$id])) {
                if ($table === 'category') {
                    return null; // カテゴリーは存在しない場合はnullを返す
                }
                return $id;
            }
            return $this->ids[$table][$id];
        }
        if (strpos($id, ':acms_unit_delimiter:') !== false) {
            $temp = explode(':acms_unit_delimiter:', $id);
            $responseIds = [];
            foreach ($temp as $tempId) {
                if (!isset($this->ids[$table][$tempId])) {
                    $responseIds[] = $tempId;
                    continue;
                }
                $responseIds[] = $this->ids[$table][$tempId];
            }
            return implode(':acms_unit_delimiter:', $responseIds);
        }
    }

    /**
     * @return void
     */
    private function registerNewIDs()
    {
        $tables = [
            'entry',
            'column',
            'media',
        ];
        foreach ($tables as $table) {
            $this->registerNewID($table);
        }

        $this->registerCategoryNewId();
        $this->registerModuleNewId();
    }

    /**
     * カテゴリーの新しいIDをマッピング
     *
     * インポートするカテゴリーデータのIDを、既存のカテゴリーIDにマッピングする。
     * 同じカテゴリーコードが異なる親カテゴリーに存在する可能性があるため、
     * カテゴリーコードと親カテゴリーIDの組み合わせで既存カテゴリーを検索する。
     *
     * 処理の流れ:
     * 1. YAMLデータからカテゴリーコードを収集
     * 2. 既存のカテゴリーをDBから検索（現在のブログ、グローバルスコープ、親ブログが対象）
     * 3. 既存カテゴリーを「カテゴリーコード:親ID」の形式でテーブル化
     * 4. YAMLのカテゴリーを親ID順にソート（親が先に処理されるようにする）
     * 5. 各カテゴリーについて、親IDをマッピング後のIDに変換し、既存カテゴリーとマッチング
     *
     * @return void
     */
    private function registerCategoryNewId()
    {
        if (!$this->existsYaml('category')) {
            return;
        }

        // ステップ1: YAMLデータからカテゴリーコードを収集
        $codeArray = [];
        foreach ($this->yaml['category'] as $record) {
            $codeArray[] = $record['category_code'];
        }

        // ステップ2: 既存のカテゴリーをDBから検索
        // 検索対象: 現在のブログ、祖先ブログのグローバルカテゴリー
        $sql = SQL::newSelect('category');
        $sql->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        ACMS_Filter::blogTree($sql, $this->bid, 'ancestor-or-self');
        $where = SQL::newWhere();
        $where->addWhereOpr('category_blog_id', $this->bid, '=', 'OR');
        $where->addWhereOpr('category_scope', 'global', '=', 'OR');
        $sql->addWhere($where);
        $sql->addWhereIn('category_code', $codeArray);
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');

        // ステップ3: 既存カテゴリーを「カテゴリーコード:親ID」の形式でテーブル化
        // 例: "news:0" => 100, "news:50" => 200（同じコードでも親が違えば別エントリー）
        $categoryTable = [];
        while ($row = DB::next($statement)) {
            $code = $row['category_code'];
            $parent = intval($row['category_parent']);
            // コードと親IDの組み合わせをキーにすることで、親が異なる同一コードを区別
            $key = $code . ':' . $parent;
            $categoryTable[$key] = $row['category_id'];
        }

        // ステップ4: YAMLのカテゴリーを親ID順にソート
        // 親が先に処理されることで、子カテゴリーの処理時に親のマッピングが完了している
        $categories = $this->yaml['category'];
        usort($categories, function ($a, $b) {
            $parentA = intval($a['category_parent']);
            $parentB = intval($b['category_parent']);
            return $parentA <=> $parentB;
        });

        // ステップ5: 各カテゴリーのIDマッピング
        foreach ($categories as $record) {
            $id = $record['category_id'];
            $code = $record['category_code'];
            $oldParentId = intval($record['category_parent']);

            // 親IDを新しいIDにマッピング
            // 例: YAMLの親ID=10が既存のID=100にマッピング済みの場合、$newParentId=100
            $newParentId = 0;
            if ($oldParentId > 0 && isset($this->ids['category'][$oldParentId])) {
                // 親が既にマッピング済み → マッピング後のIDを使用
                $newParentId = $this->ids['category'][$oldParentId];
            } elseif ($oldParentId > 0) {
                // 親カテゴリーがYAMLデータに含まれていない、または循環参照などの異常な状態。
                // データ不整合を避けるため、ルートカテゴリー（parent=0）として扱う。
                // 通常、親IDでソートしているためこの分岐には到達しない。
                $newParentId = 0;
            }

            // カテゴリーコードとマッピング後の親IDでキーを作成し、既存カテゴリーを検索
            // 例: "news:100" → 既存のカテゴリーID=200
            $key = $code . ':' . $newParentId;

            if (isset($categoryTable[$key])) {
                // 既存カテゴリーが見つかった → そのIDを使用（新規作成しない）
                $this->ids['category'][$id] = $categoryTable[$key];
            } else {
                // 既存カテゴリーが見つからない → 後続処理で新規作成される
                $this->ids['category'][$id] = null;
            }
        }
    }

    private function registerModuleNewId()
    {
        if (!$this->existsYaml('module')) {
            return;
        }
        $identifierArray = [];
        foreach ($this->yaml['module'] as $record) {
            $identifierArray[] = $record['module_identifier'];
        }
        $sql = SQL::newSelect('module');
        $sql->addLeftJoin('blog', 'blog_id', 'module_blog_id');
        ACMS_Filter::blogTree($sql, $this->bid, 'ancestor-or-self');
        $where = SQL::newWhere();
        $where->addWhereOpr('module_blog_id', $this->bid, '=', 'OR');
        $where->addWhereOpr('module_scope', 'global', '=', 'OR');
        $sql->addWhere($where);
        $sql->addWhereIn('module_identifier', $identifierArray);
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');

        $moduleTable = [];
        while ($row = DB::next($statement)) {
            $identifier = $row['module_identifier'];
            $moduleTable[$identifier] = $row['module_id'];
        }
        foreach ($this->yaml['module'] as $record) {
            $id = $record['module_id'];
            $identifier = $record['module_identifier'];

            if (isset($moduleTable[$identifier])) {
                $this->ids['module'][$id] = $moduleTable[$identifier];
            } else {
                $this->ids['module'][$id] = null;
            }
        }
    }

    /**
     * @param string $table
     *
     * @return void
     */
    private function registerNewID($table)
    {
        if (!$this->existsYaml($table)) {
            return;
        }
        foreach ($this->yaml[$table] as $record) {
            if (!isset($record[$table . '_id'])) {
                continue;
            }
            $id = $record[$table . '_id'];
            if (isset($this->ids[$table][$id])) {
                continue;
            }
            if ($table === 'column') {
                $this->ids[$table][$id] = uuidv4();
            } else {
                $this->ids[$table][$id] = DB::query(SQL::nextval($table . '_id', dsn()), 'seq');
            }
        }
    }

    /**
     * check yaml data
     *
     * @param $table
     *
     * @return bool
     */
    private function existsYaml($table)
    {
        if (!isset($this->yaml[$table])) {
            return false;
        }
        $data = $this->yaml[$table];
        if (!is_array($data)) {
            return false;
        }
        return true;
    }
}
