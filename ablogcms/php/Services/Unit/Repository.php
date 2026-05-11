<?php

namespace Acms\Services\Unit;

use Acms\Services\Unit\Constants\UnitAlign;
use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Constants\UnitStatus;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Media;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use LogicException;
use ACMS_RAM;
use SQL;

/**
 * @phpstan-type ValidatedRequest array{
 *  id: non-empty-string,
 *  type: string,
 *  parentId: non-empty-string|null,
 *  align: string,
 *  anker: string,
 *  group: string,
 *  status: string,
 * }[]
 */
class Repository
{
    use \Acms\Traits\Unit\UnitRepositoryTrait;
    use \Acms\Traits\Unit\UnitMultiLangTrait;

    /**
     * ユニットレジストリ
     * @var \Acms\Services\Unit\Registry
     */
    protected $unitRegistory;

    /**
     * コンストラクタ
     *
     * @param \Acms\Services\Unit\Registry $resistry
     */
    public function __construct(Registry $resistry)
    {
        $this->unitRegistory = $resistry;
    }

    /**
     * ユニットモデルを作成
     *
     * @param string $type
     * @return null|Model
     */
    public function makeModel(string $type): ?Model
    {
        $baseType = detectUnitTypeSpecifier($type); // 特定指定子を除外した、一般名のユニット種別
        if (!$this->unitRegistory->exists($baseType)) {
            return null;
        }
        /** @var Model $model */
        $model = $this->unitRegistory->make($baseType); // ユニットモデルを生成
        $model->setType($type);

        return $model;
    }

    /**
     * ユニットモデルをDBデータから生成
     *
     * @param array $unit
     * @return Model|null
     */
    public function loadModel(array $unit): ?Model
    {
        if ($model = $this->makeModel($unit['column_type'])) {
            $model->load($unit); // モデルにデータをロード
            return $model;
        }
        return null;
    }

    /**
     * ユニットモデル（配列）をDBデータから生成
     *
     * @param array $units
     * @return UnitCollection
     */
    public function loadModels(array $units): UnitCollection
    {
        // モデルを生成
        $models = array_map(function ($unit) {
            return $this->loadModel($unit);
        }, $units);

        // モデルがnullの場合は除外
        $models = array_filter($models, function ($model) {
            return $model !== null;
        });

        $models = array_values($models); // 添字をリセット

        return new UnitCollection($models);
    }

    /**
     * 追加時のユニットを作成
     *
     * @param string $type
     * @param string $addType
     * @param int $configIndex
     * @return Model|null
     */
    public function create(string $type, string $addType, int $configIndex = 0): ?Model
    {
        if ($model = $this->makeModel($type)) {
            $model->create($addType, $configIndex); // 追加時のユニットを新規生成
            return $model;
        }
        return null;
    }

    /**
     * 初期表示のユニットを作成
     *
     * @param string $type
     * @param int $configIndex
     * @return Model|null
     */
    public function createDefault(string $type, int $configIndex = 0): ?Model
    {
        if ($model = $this->makeModel($type)) {
            $model->setId($model->generateNewIdTrait());
            $model->createDefault($configIndex); // 初期表示のユニットを新規生成
            return $model;
        }
        return null;
    }

    /**
     * 指定したユニットIDのモデルをロード
     *
     * @param non-empty-string $utid
     * @return Model|null
     */
    public function loadUnit(string $utid): ?Model
    {
        $unit = $this->loadUnitFromDBTrait($utid);
        if (is_null($unit)) {
            return null;
        }
        if ($model = $this->makeModel($unit['column_type'])) {
            $model->load($unit); // モデルにデータをロード
            return $model;
        }
        return null;
    }

    /**
     * 指定したエントリーからユニットをロード
     *
     * @param int $eid
     * @param ?int $rvid
     * @param ?int $range
     * @param array{
     *   setPrimaryImage?: bool,
     * } $options
     * @return UnitCollection
     */
    public function loadUnits(
        int $eid,
        ?int $rvid = null,
        ?int $range = null,
        array $options = []
    ): UnitCollection {
        $units = $this->loadUnitsFromDBTrait($eid, $rvid, $range);
        $collection = $this->loadModels($units);
        if (isset($options['setPrimaryImage']) && $options['setPrimaryImage']) {
            $sql = SQL::newSelect($rvid !== null ? 'entry_rev' : 'entry');
            $sql->addSelect('entry_primary_image');
            $sql->addWhereOpr('entry_id', $eid);
            if ($rvid !== null) {
                $sql->addWhereOpr('entry_rev_id', $rvid);
            }
            /** @var string|null|false $primaryImageUnitId */
            $primaryImageUnitId = Database::query($sql->get(dsn()), 'one');
            if ($primaryImageUnitId !== false && $primaryImageUnitId !== null && $primaryImageUnitId !== '') {
                $collection->setPrimaryImageUnit($primaryImageUnitId);
            }
        }
        return $collection;
    }

    /**
     * 初期表示ユニットをロード
     *
     * @return UnitCollection
     */
    public function loadDefaultUnit(): UnitCollection
    {
        $defaultUnitTypes = configArray('column_def_insert_type');
        if (count($defaultUnitTypes) === 0) {
            return new UnitCollection([]);
        }
        $units = [];
        foreach ($defaultUnitTypes as $i => $type) {
            if ($model = $this->createDefault($type, $i)) {
                $units[] = $model;
            }
        }
        return new UnitCollection($units);
    }

    /**
     * ユニットデータをEagerLoad
     *
     * @param int[] $entryIds
     * @return array<int<1, max>, \Acms\Services\Unit\UnitCollection>
     */
    public function eagerLoadUnits(array $entryIds): array
    {
        if (count($entryIds) === 0) {
            return [];
        }
        $sql = SQL::newSelect('column');
        $sql->addWhereIn('column_entry_id', array_unique($entryIds));
        $sql->addWhereOpr('column_attr', 'acms-form', '<>');
        $sql->addOrder('column_sort', 'ASC');
        $q = $sql->get(dsn());
        $unitsData = Database::query($q, 'all');

        /** @var array<int<1, max>, Model[]> $data */
        $data = $this->formatEagerLoadUnits($unitsData, function ($carry, $unit) {
            $eid = $unit->getEntryId();
            if (!isset($carry[$eid])) {
                $carry[$eid] = [];
            }
            $carry[$eid][] = $unit;
            return $carry;
        }, []);

        $eagerLoadingData = [];
        foreach ($data as $eid => $units) {
            $eagerLoadingData[$eid] = new UnitCollection($units);
        }

        return $eagerLoadingData;
    }

    /**
     * カスタムユニットのカスタムフィールドをEagerLoad
     *
     * @param UnitCollection $collection
     * @return void
     */
    public function eagerLoadCustomUnitFields(UnitCollection $collection): void
    {
        [$customUnitIds, $customUnitRevIds] = $collection->reduce(function (array $carry, Model $unit) {
            $id = $unit->getId();
            $rvid = $unit->getRevId();
            if ($unit instanceof \Acms\Services\Unit\Contracts\EagerLoadingCustom) {
                $carry[0][] = $id;
                $carry[1][] = $rvid;
            }
            return $carry;
        }, [[], []]);

        $customUnitRevIds = array_unique($customUnitRevIds);
        if (count($customUnitRevIds) > 1) {
            throw new \RuntimeException('ロードしたユニット内に異なるリビジョンのユニットが含まれます。');
        }
        $rvid = $customUnitRevIds[0] ?? null;

        if ($customUnitIds) {
            $customUnitFields = eagerLoadField($customUnitIds, 'unit_id', $rvid);

            $collection->walk(function (Model $unit) use ($customUnitFields) {
                if ($unit instanceof \Acms\Services\Unit\Contracts\EagerLoadingCustom) {
                    $unit->setEagerLoadedCustomUnitFields($customUnitFields);
                }
            });
        }
    }

    /**
     * メイン画像ユニットをEagerLoad
     *
     * @param array $entries
     * @return array{
     *  unit: array<non-empty-string, Model>,
     *  media: array<int, array>
     * }
     */
    public function eagerLoadPrimaryImageUnits(array $entries): array
    {
        $eagerLoadingData = [
            'unit' => [],
            'media' => [],
        ];
        $mainImageUnitIds = array_reduce($entries, function ($carry, $entry) {
            if ($primaryImageUnitId = isset($entry['entry_primary_image']) ? (string) $entry['entry_primary_image'] : null) {
                $carry[] = $primaryImageUnitId;
            }
            return $carry;
        }, []);
        if (count($mainImageUnitIds) === 0) {
            return $eagerLoadingData;
        }
        $sql = SQL::newSelect('column');
        $sql->addWhereIn('column_id', array_unique($mainImageUnitIds));
        $sql->addWhereOpr('column_attr', 'acms-form', '<>');
        $q = $sql->get(dsn());
        $unitsData = Database::query($q, 'all');

        $eagerLoadingData['unit'] = $this->formatEagerLoadUnits($unitsData, function ($carry, $unit) {
            $utid = $unit->getId();
            $carry[$utid] = $unit;
            return $carry;
        }, []);
        $collection = new UnitCollection(array_values($eagerLoadingData['unit']));
        $eagerLoadingData['media'] = Media::mediaEagerLoadFromUnit($collection);

        return $eagerLoadingData;
    }

    /**
     * ユニットをEagerLoad
     *
     * @param array $unitsData
     * @return array {unit: Model[], media: []}
     */
    private function formatEagerLoadUnits(array $unitsData, callable $reduce, $initData)
    {
        foreach ($unitsData as $data) {
            if ($model = $this->makeModel($data['column_type'])) {
                $model->load($data); // モデルにデータをロード
                $initData = $reduce($initData, $model);
            }
        }
        return $initData;
    }

    /**
     * リクエスト内のユニットデータをバリデーション
     *
     * @param array $request
     * @return ValidatedRequest
     */
    private function validateRequest(array $request): array
    {
        $ids = $request['unit_id'] ?? null;
        if (is_null($ids)) {
            // ユニットデータが0件の場合
            return [];
        }

        if (!is_array($ids)) {
            throw new \RuntimeException('unit_id must be an array');
        }

        if (
            !array_all($ids, function ($value) {
                return is_string($value) && $value !== '';
            })
        ) {
            throw new \RuntimeException('unit id must be an array of non-empty strings');
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw new \RuntimeException('unit id must be unique');
        }

        $ids = array_map('strval', $ids);

        $types = $request['unit_type'] ?? null;
        if (is_null($types)) {
            throw new \RuntimeException('unit type is required');
        }

        if (!is_array($types)) {
            throw new \RuntimeException('unit type must be an array');
        }

        if (
            !array_all($types, function ($value) {
                return is_string($value);
            })
        ) {
            throw new \RuntimeException('unit type must be an array of strings');
        }

        $types = array_map('strval', $types);

        $parentIds = $request['unit_parent_id'] ?? null;
        if (is_null($parentIds)) {
            throw new \RuntimeException('unit parent id is required');
        }

        if (!is_array($parentIds)) {
            throw new \RuntimeException('unit parent id must be an array');
        }

        if (
            !array_all($parentIds, function ($value) {
                return is_string($value);
            })
        ) {
            throw new \RuntimeException('unit parent id must be an array of strings');
        }

        $parentIds = array_map(function ($value) {
            return $value === '' ? null : $value;
        }, $parentIds);

        $aligns = $request['unit_align'] ?? null;
        if (is_null($aligns)) {
            throw new \RuntimeException('unit align is required');
        }

        if (!is_array($aligns)) {
            throw new \RuntimeException('unit align must be an array');
        }

        if (
            !array_all($aligns, function ($value) {
                return is_string($value);
            })
        ) {
            throw new \RuntimeException('unit align must be an array of strings');
        }

        $aligns = array_map('strval', $aligns);

        $groups = $request['unit_group'] ?? null;
        if (is_null($groups)) {
            throw new \RuntimeException('unit group is required');
        }

        if (!is_array($groups)) {
            throw new \RuntimeException('unit group must be an array');
        }

        if (
            !array_all($groups, function ($value) {
                return is_string($value);
            })
        ) {
            throw new \RuntimeException('unit group must be an array of strings');
        }

        $groups = array_map('strval', $groups);

        $ankers = $request['unit_anker'] ?? null;
        if (is_null($ankers)) {
            $ankers = array_fill(0, count($ids), '');
        } else {
            if (!is_array($ankers)) {
                throw new \RuntimeException('unit anker must be an array');
            }
            if (
                !array_all($ankers, function ($value) {
                    return is_string($value);
                })
            ) {
                throw new \RuntimeException('unit anker must be an array of strings');
            }
            $ankers = array_map('strval', $ankers);
        }

        if (count($ankers) !== count($ids)) {
            throw new \RuntimeException('unit anker must have the same length as unit id');
        }

        // ステータスのバリデーション
        $statuses = $request['unit_status'] ?? null;
        if (is_null($statuses)) {
            // ステータスが指定されていない場合はデフォルト値を設定
            $statuses = array_fill(0, count($ids), UnitStatus::OPEN);
        }

        if (!is_array($statuses)) {
            throw new \RuntimeException('unit status must be an array');
        }

        if (
            !array_all($statuses, function ($value) {
                return is_string($value);
            })
        ) {
            throw new \RuntimeException('unit status must be an array of strings');
        }

        $statuses = array_map('strval', $statuses);

        $counts = [
            count($ids),
            count($types),
            count($parentIds),
            count($aligns),
            count($groups),
            count($statuses),
            count($ankers),
        ];

        if (count(array_unique($counts)) !== 1) {
            throw new \RuntimeException('All unit arrays must have the same length');
        }

        return array_map(function (
            string $id,
            string $type,
            ?string $parentId,
            string $align,
            string $group,
            string $status,
            string $anker
        ) {
            /** @var non-empty-string $id */
            /** @var non-empty-string|null $parentId */
            return [
                'id' => $id,
                'type' => $type,
                'parentId' => $parentId,
                'align' => $align,
                'group' => $group,
                'status' => $status,
                'anker' => $anker,
            ];
        }, $ids, $types, $parentIds, $aligns, $groups, $statuses, $ankers);
    }

    /**
     * POSTデータからユニットを抽出
     *
     * @param int|null $range
     * @param non-empty-string|null $primaryImageUnitId
     * @return array{collection: \Acms\Services\Unit\UnitCollection, range: int|null}
     */
    public function extractUnits(?int $range = null, ?string $primaryImageUnitId = null): array
    {
        $models = [];
        $newRange = $range;
        $request = $this->validateRequest($_POST);
        foreach ($request as $data) {
            $model = $this->makeModel($data['type']);
            if (is_null($model)) {
                continue;
            }
            $model->setId($data['id']);
            $model->setParentId($data['parentId']);
            if ($model instanceof AlignableUnitInterface && $data['align'] !== '') {
                $model->setAlign(UnitAlign::from($data['align']));
            }
            if ($model instanceof AnkerUnitInterface && $data['anker'] !== '') {
                $model->setAnker($data['anker']);
            }
            if (config('unit_group') === 'on') {
                $model->setGroup($data['group']);
            }
            $model->setStatus(UnitStatus::from($data['status']));

            // Handle multiple units
            $unitModels = $this->handleMultipleUnitsTrait($model);
            if ($model->getParentId() === null && $newRange !== null) {
                // ルート階層かつ、rangeが指定されている場合は、rangeを更新
                $newRange += count($unitModels) - 1; // 元のユニットとして1つ引く
            }
            foreach ($unitModels as $unitModel) {
                $unitModel->extract($_POST);
                $models[] = $unitModel;
            }
        }

        $collection = new UnitCollection($models);
        $collection->resort();

        if ($primaryImageUnitId !== null) {
            $collection->setPrimaryImageUnit($primaryImageUnitId);
        }

        return [
            'collection' => $collection,
            'range' => $newRange,
        ];
    }

    /**
     * ユニットのアセットを保存
     *
     * @param UnitCollection $collection
     * @return void
     */
    public function saveAssets(UnitCollection $collection): void
    {
        $assetProviderUnits = $collection->filter(function ($unit) {
            return $unit instanceof \Acms\Services\Unit\Contracts\AssetProvider;
        });
        $assetProviderUnits->walk(function ($unit) {
            assert($unit instanceof \Acms\Services\Unit\Contracts\AssetProvider);
            $unit->saveFiles($_POST);
        });
    }

    /**
     * ユニットを保存
     *
     * エントリーの全ユニットを更新します。既存のユニットは一旦削除され、
     * 新しいユニットで置き換えられます。
     *
     * 保存前後でファイルパスが消える（行削除・差し替え）AssetProvider ユニットの
     * 物理ファイルは、column / column_rev 両テーブルの参照チェックを経て
     * どこからも参照されていない場合にのみ物理削除します。
     * これにより「作業領域のみ更新」「新バージョン保存」でメイン側が参照するファイルを
     * 誤って削除することを自動的に防げます。
     *
     * @param UnitCollection $collection 保存するユニットのコレクション
     * @param int $eid エントリーID
     * @param int $bid ブログID
     * @param ?int $rvid リビジョンID（リビジョンモードの場合のみ使用）
     * @return UnitCollection 保存したユニットのコレクション
     * @throws \LogicException 親ユニットのIDが不正な場合
     */
    public function saveAllUnits(
        UnitCollection $collection,
        int $eid,
        int $bid,
        ?int $rvid = null
    ): UnitCollection {
        $newCollection = $collection->filter(function ($unit) {
            return $unit->canSave();
        });

        // DELETE前: 行削除・差し替えで新コレクションから消えたファイルパスを収集
        $orphanedPaths = $this->collectOrphanedFilePaths($newCollection, $eid, $rvid);

        // 既存のユニットを削除
        $this->removeUnitsTrait($eid, $rvid);

        $result = $this->saveUnitsInternal($newCollection, $eid, $bid, $rvid);

        // INSERT後: 参照チェックして未参照ファイルのみ物理削除
        if ($orphanedPaths !== []) {
            $this->removeUnreferencedUnitFiles($orphanedPaths);
        }

        return $result;
    }

    /**
     * 新コレクションから消える既存 AssetProvider ユニットのファイルパスを収集する（DELETE前に呼ぶ）。
     *
     * 行削除だけでなく、同じユニットIDでファイルが差し替えられたケースや、
     * 削除チェックにより空文字化されたケースも「古いパスが新コレクションに存在しない」として拾う。
     *
     * @param UnitCollection $newCollection 保存対象の新しいユニットコレクション
     * @param int $eid エントリーID
     * @param ?int $rvid リビジョンID
     * @return array<string, Model&Contracts\AssetProvider> 古いパス => そのパスを持っていたユニット
     */
    private function collectOrphanedFilePaths(UnitCollection $newCollection, int $eid, ?int $rvid): array
    {
        // 新コレクション側で使われるファイルパスを収集
        $newPaths = [];
        foreach ($newCollection->flat() as $unit) {
            if (!$unit instanceof Contracts\AssetProvider) {
                continue;
            }
            foreach ($unit->getFilePaths() as $path) {
                if ($path !== '') {
                    $newPaths[$path] = true;
                }
            }
        }

        // 既存ユニットのパスから新側で使われていないものを抽出
        $orphaned = [];
        $existing = $this->loadUnits($eid, $rvid);
        foreach ($existing->flat() as $oldUnit) {
            if (!$oldUnit instanceof Contracts\AssetProvider) {
                continue;
            }
            foreach ($oldUnit->getFilePaths() as $oldPath) {
                if ($oldPath === '' || isset($newPaths[$oldPath])) {
                    continue;
                }
                $orphaned[$oldPath] = $oldUnit;
            }
        }
        return $orphaned;
    }

    /**
     * 参照チェック済みの孤児ファイルを物理削除する（INSERT後に呼ぶ）。
     *
     * @param array<string, Model&Contracts\AssetProvider> $orphanedPaths collectOrphanedFilePaths の戻り値
     */
    private function removeUnreferencedUnitFiles(array $orphanedPaths): void
    {
        foreach ($orphanedPaths as $relPath => $unit) {
            if ($this->isReferencedByUnit($relPath)) {
                continue;
            }
            if ($unit instanceof \Acms\Services\Unit\Models\Image) {
                $normal = ARCHIVES_DIR . $relPath;
                $large = otherSizeImagePath($normal, 'large');
                $tiny = otherSizeImagePath($normal, 'tiny');
                $square = otherSizeImagePath($normal, 'square');
                deleteFile($normal, true);
                deleteFile($large, true);
                deleteFile($tiny, true);
                deleteFile($square, true);
                deleteFile("{$normal}.webp", true);
                deleteFile("{$large}.webp", true);
                deleteFile("{$tiny}.webp", true);
                deleteFile("{$square}.webp", true);
                if (HOOK_ENABLE) {
                    $Hook = \ACMS_Hook::singleton();
                    $Hook->call('mediaDelete', $normal);
                    $Hook->call('mediaDelete', $large);
                    $Hook->call('mediaDelete', $tiny);
                    $Hook->call('mediaDelete', $square);
                }
            } else {
                $target = ARCHIVES_DIR . $relPath;
                deleteFile($target, true);
                deleteFile("{$target}.webp", true);
                if (HOOK_ENABLE) {
                    $Hook = \ACMS_Hook::singleton();
                    $Hook->call('mediaDelete', $target);
                }
            }
        }
    }

    /**
     * 指定パスが column / column_rev テーブルのいずれかで使われているか判定する。
     *
     * LIKE 検索である理由:
     * 多言語ユニットでは複数のファイルパスが `:acms_unit_delimiter:` 区切りで
     * 1 カラム（column_field_2）に詰め込まれる仕様のため、等価比較ではヒットしない。
     * `UnitMultiLangTrait::implodeUnitDataTrait()` / `explodeUnitDataTrait()` を参照。
     *
     * 副作用として以下のリスクがある:
     * - インデックスが効かずフルテーブルスキャンになる（参照チェックは保存の度に走るので
     *   テーブル肥大時のパフォーマンスに影響しうる）。
     * - 他のパスの部分文字列として誤マッチする可能性（例: "a.jpg" が "banana.jpg" に
     *   ヒット）。現行は `uniqueString()` ベースの 64 文字ハッシュパスのため実用上の
     *   衝突はほぼないが、`rawfilename` 設定で任意ファイル名を許容する運用では発生しうる。
     *   誤マッチは「削除されるべきファイルが残る」側に倒れるので安全寄り。
     *
     * 将来の改善方針:
     * - 多言語ユニットのサポートを終了できれば等価比較に統一可能。
     * - 暫定対応するなら LIKE で粗く絞り込んだ上で `explodeUnitDataTrait()` による
     *   PHP 側の厳密判定にフォールバックする方式が候補（案3）。
     *
     * @param string $path 検査するファイルパス（相対パス）
     * @return bool いずれかのレコードが参照していれば true
     */
    private function isReferencedByUnit(string $path): bool
    {
        $sql = SQL::newSelect('column');
        $sql->setSelect('column_field_2');
        $sql->addWhereOpr('column_field_2', '%' . $path . '%', 'LIKE');
        $sql->setLimit(1);
        if (Database::query($sql->get(dsn()), 'one')) {
            return true;
        }

        $sql = SQL::newSelect('column_rev');
        $sql->setSelect('column_field_2');
        $sql->addWhereOpr('column_field_2', '%' . $path . '%', 'LIKE');
        $sql->setLimit(1);
        if (Database::query($sql->get(dsn()), 'one')) {
            return true;
        }

        return false;
    }

    /**
     * ユニットを挿入
     *
     * 既存のユニットを保持したまま、新しいユニットを挿入します。
     *
     * @param UnitCollection $collection 追加するユニットのコレクション
     * @param array{sort: positive-int, parentId: non-empty-string|null} $position 挿入位置
     * @param int $eid エントリーID
     * @param int $bid ブログID
     * @return UnitCollection 挿入したユニットのコレクション
     * @throws \LogicException 親ユニットのIDが不正な場合
     */
    public function insertUnits(UnitCollection $collection, array $position, int $eid, int $bid): UnitCollection
    {
        // 保存可能なユニットを抽出
        $newCollection = $collection->filter(function ($unit) {
            return $unit->canSave();
        });

        // 挿入位置以降のソート番号を更新
        $this->formatOrderWithInsertionTrait($position, $eid, null, count($newCollection));

        // ユニットのソートを更新
        $newCollection->resort($position['sort']);

        return $this->saveUnitsInternal($newCollection, $eid, $bid, null);
    }

    /**
     * ユニットを更新
     *
     * @param UnitCollection $collection 更新するユニットのコレクション
     * @param int $eid エントリーID
     * @param int $bid ブログID
     * @return UnitCollection 更新したユニットのコレクション
     */
    public function updateUnits(UnitCollection $collection, int $eid, int $bid): UnitCollection
    {
        // 保存可能なユニットを抽出
        $collection = $collection->filter(function ($unit) {
            return $unit->canSave();
        });
        return $this->saveUnitsInternal($collection, $eid, $bid, null);
    }

    /**
     * ユニットの保存処理の内部実装
     *
     * @param UnitCollection $collection 保存するユニットのコレクション
     * @param int $eid エントリーID
     * @param int $bid ブログID
     * @param ?int $rvid リビジョンID
     * @return UnitCollection 保存したユニットのコレクション
     * @throws \LogicException 親ユニットのIDが不正な場合
     * @internal
     */
    private function saveUnitsInternal(
        UnitCollection $collection,
        int $eid,
        int $bid,
        ?int $rvid
    ): UnitCollection {
        if (
            $collection->any(function ($unit) {
                return !$unit->canSave();
            })
        ) {
            throw new \LogicException('All units must be saved. Please check the unit canSave() method.');
        }
        if (!enableRevision()) {
            // リビジョン機能が有効でない場合はリビジョンIDをnullに設定
            $rvid = null;
        }

        foreach ($collection->flat() as $unit) {
            $unitId = $unit->getId();
            if (is_null($unitId)) {
                throw new \LogicException('Unit ID must not be null. This is an unexpected state as the unit data already exists in the database.');
            }
            $this->removeUnitTrait($unitId, $rvid); // 既存ユニットデータを削除
            $unit->save($eid, $bid, $rvid);
        }
        return $collection;
    }

    /**
     * リビジョンユニットを保存
     *
     * @param UnitCollection $collection
     * @param int $eid
     * @param int $bid
     * @param int|null $rvid
     * @return UnitCollection 保存したユニットのコレクション
     */
    public function saveRevisionUnits(
        UnitCollection $collection,
        int $eid,
        int $bid,
        ?int $rvid = null
    ): UnitCollection {
        if (!enableRevision()) {
            throw new \LogicException('Cannot save revision units because revision feature is disabled. To enable revisions, please enable the revision feature in system settings.');
        }
        return $this->saveAllUnits($collection, $eid, $bid, $rvid);
    }

    /**
     * 指定したエントリーのユニットを複製
     *
     * @param int $eid
     * @param int $newEid
     * @param int|null $rvid
     * @param int|null $newRvid
     * @return UnitCollection
     */
    public function duplicateUnits(int $eid, int $newEid, ?int $rvid = null, ?int $newRvid = null): UnitCollection
    {
        $collection = $this->loadUnits(
            eid: $eid,
            rvid: $rvid,
            options: ['setPrimaryImage' => true],
        );
        $newCollection = clone $collection;

        $newCollection->walk(function (Model $unit) use ($newEid, $newRvid) {
            if (is_null($unit->getId())) {
                throw new \RuntimeException('Unit ID must not be null. This is an unexpected state as the unit data already exists in the database.');
            }
            $unit->setEntryId($newEid);
            if ($newRvid !== null && $newRvid > 0) {
                $unit->setRevId($newRvid);
            }
            $unit->handleDuplicate();
            $unit->insertDataTrait($unit, $newRvid !== null && $newRvid > 0);
        });
        return $newCollection;
    }

    /**
     * リビジョンユニットを複製して別リビジョンに複製
     * @param int $eid
     * @param int $sourceRvid
     * @param int $targetRvid
     * @return void
     */
    public function duplicateRevisionUnits(int $eid, int $sourceRvid, int $targetRvid): void
    {
        $collection = $this->loadUnits($eid, $sourceRvid);

        foreach ($collection->flat() as $unit) {
            $unit->setRevId($targetRvid);
            $unit->handleDuplicate();
            $unit->insertDataTrait($unit, true);
        }
    }

    /**
     * 指定したユニットを同エントリー内に複製
     * 階層構造には対応しておらず、親ユニットのIDを指定すると例外が発生します
     * @param non-empty-string $unitId
     * @param int $eid
     * @param int|null $rvid
     * @return \Acms\Services\Unit\Contracts\Model
     */
    public function duplicateUnit(string $unitId, int $eid, ?int $rvid = null): Model
    {
        $unit = $this->loadUnit($unitId);
        if (!$unit instanceof Model) {
            throw new \RuntimeException("The unit with ID={$unitId} was not found.");
        }
        if ($unit instanceof \Acms\Services\Unit\Contracts\ParentUnit) {
            throw new \RuntimeException('Parent unit cannot be duplicated.');
        }
        $position = [
            'sort' => $unit->getSort() + 1, // 1つ後ろに挿入するため+1
            'parentId' => $unit->getParentId(),
        ];
        $this->formatOrderWithInsertionTrait($position, $eid, $rvid);

        $newUnit = clone $unit;
        $isRevision = $rvid && $rvid > 0;
        $newUnit->setSort($position['sort']);
        $newUnit->handleDuplicate();
        $newUnit->insertDataTrait($newUnit, $isRevision);

        return $newUnit;
    }

    /**
     * 1ユニット削除
     * 階層構造には対応しておらず、親ユニットのIDを指定すると例外が発生します
     *
     * @param non-empty-string $unitId
     * @param int|null $rvid
     * @param bool $withAssets
     * @return \Acms\Services\Unit\Contracts\Model
     */
    public function removeUnit(string $unitId, ?int $rvid = null, bool $withAssets = true): Model
    {
        $unit = $this->loadUnit($unitId);
        if (!$unit instanceof Model) {
            throw new \RuntimeException("The unit with ID={$unitId} was not found.");
        }
        if ($unit instanceof \Acms\Services\Unit\Contracts\ParentUnit) {
            throw new \RuntimeException('Parent unit cannot be removed.');
        }
        if ($withAssets) {
            $unit->handleRemove();
        }

        $position = [
            'sort' => $unit->getSort(),
            'parentId' => $unit->getParentId(),
        ];
        $entryId = $unit->getEntryId();
        if ($entryId === null) {
            throw new \RuntimeException('Entry ID must not be null. Please check the unit data.');
        }
        $this->formatOrderWithRemovalTrait($position, $entryId, $rvid);
        $this->removeUnitTrait($unitId, $rvid);

        return $unit;
    }

    /**
     * 全ユニットを削除
     *
     * @param int $eid
     * @param int|null $rvid
     * @param bool $withAssets
     * @return UnitCollection
     */
    public function removeUnits(int $eid, ?int $rvid = null, bool $withAssets = true): UnitCollection
    {
        $collection = $this->loadUnits($eid, $rvid);
        if ($withAssets) {
            foreach ($collection->flat() as $unit) {
                $unit->handleRemove();
            }
        }
        $this->removeUnitsTrait($eid, $rvid);

        return $collection;
    }

    /**
     * ユニットを DB から削除し、参照チェック経由で孤児ファイルのみ物理削除する。
     *
     * 承認完了時の changeRevision のように、メイン column を消して column_rev を
     * メインに昇格する用途を想定。削除前にメインのユニットが参照していたファイルパスを
     * 収集し、DB から削除した後、column / column_rev のいずれからも参照されなくなった
     * ファイルだけを物理削除する。
     *
     * column_rev に同じパスがあればファイルは残る（昇格後もパスを使い続けるため）。
     *
     * @param int $eid エントリーID
     * @param int|null $rvid リビジョンID（null でメイン column が対象）
     * @return UnitCollection 削除したユニットのコレクション
     */
    public function removeUnitsWithReferenceCheck(int $eid, ?int $rvid = null): UnitCollection
    {
        $collection = $this->loadUnits($eid, $rvid);

        // 削除前に、各ユニットが参照するファイルパスを収集する
        $orphanCandidates = [];
        foreach ($collection->flat() as $unit) {
            if (!$unit instanceof Contracts\AssetProvider) {
                continue;
            }
            foreach ($unit->getFilePaths() as $path) {
                if ($path === '') {
                    continue;
                }
                $orphanCandidates[$path] = $unit;
            }
        }

        // DB レコードを削除（column / column_rev いずれか、および field / field_rev の対応行）
        $this->removeUnitsTrait($eid, $rvid);

        // いずれのレコードからも参照されなくなったファイルのみ物理削除する
        if ($orphanCandidates !== []) {
            $this->removeUnreferencedUnitFiles($orphanCandidates);
        }

        return $collection;
    }

    /**
     * ユニットの検索テキストを取得
     *
     * @param int $eid
     * @return string
     */
    public function getUnitSearchText(int $eid): string
    {
        $collection = $this->loadUnits($eid);
        $this->eagerLoadCustomUnitFields($collection);

        $searchText = array_reduce($collection->flat(), function ($carry, $unit) {
            if (!$unit->isHidden()) {
                if ($unitSummaryText = $unit->getSearchText()) {
                    $carry .= "{$unitSummaryText} ";
                }
            }
            return $carry;
        }, '');
        return $this->removeMultiLangUnitDelimiterTrait($searchText);
    }
}
