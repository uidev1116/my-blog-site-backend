<?php

declare(strict_types=1);

namespace Acms\Services\Unit;

use Acms\Services\Unit\Contracts\ParentUnit;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Services\Unit\Contracts\AttrableUnitInterface;
use Acms\Services\Unit\Contracts\ImageUnit;
use Acms\Services\Unit\Contracts\Model;

/**
 * ユニットのコレクション（tree 構造と flat 構造の両方の違いを吸収するために利用）
 */
class UnitCollection implements \Countable
{
    /**
     * @var Model[]
     */
    private $units;

    /**
     * @param Model[]|UnitTree $units
     */
    public function __construct($units)
    {
        if ($units instanceof UnitTree) {
            $this->units = $this->toFlat($units);
        } else {
            $this->units = $units;
        }
    }

    /**
     * @return UnitTree
     */
    public function tree(): UnitTree
    {
        return $this->toTree($this->units);
    }

    /**
     * @return Model[]
     */
    public function flat(): array
    {
        return $this->units;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function flatArray(): array
    {
        $array = [];
        foreach ($this->flat() as $unit) {
            $data = [
                'id' => $unit->getId(),
                'type' => $unit->getType(),
                'name' => $unit->getName(),
                'status' => $unit->getStatus()->value,
                'parent_id' => $unit->getParentId(),
                'sort' => $unit->getSort(),
            ];

            if (config('unit_group') === 'on') {
                $data['group'] = $unit->getGroup();
            }

            if ($unit instanceof AttrableUnitInterface) {
                $data['attr'] = $unit->getAttr();
            }

            if ($unit instanceof AlignableUnitInterface) {
                $data['align'] = $unit->getAlign()->value;
            }

            if ($unit instanceof AnkerUnitInterface) {
                $data['anker'] = $unit->getAnker();
            }

            $array[] = $data;
        }
        return $array;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function treeArray(): array
    {
        return $this->treeToArray($this->tree()->getRoots());
    }

    /**
     * @param Model[] $units
     * @return UnitTree
     */
    private function toTree(array $units): UnitTree
    {
        $nodes = [];
        foreach ($units as $unit) {
            $nodes[$unit->getId()] = new UnitTreeNode($unit);
        }

        $roots = [];
        foreach ($nodes as $node) {
            if ($node->unit->getParentId() === null) {
                $roots[] = $node;
            } else {
                $parent = $nodes[$node->unit->getParentId()] ?? null;
                if ($parent && $parent->unit instanceof ParentUnit) {
                    $parent->children[] = $node;
                }
            }
        }
        return new UnitTree($roots);
    }

    /**
     * @param UnitTree $tree
     * @return Model[]
     */
    private function toFlat(UnitTree $tree): array
    {
        $flat = [];
        $this->flattenTree($tree->getRoots(), $flat);
        return $flat;
    }

    /**
     * @param UnitTreeNode[] $nodes
     * @param Model[] $flat
     * @return void
     * @param-out Model[] $flat
     */
    private function flattenTree(array $nodes, array &$flat): void
    {
        foreach ($nodes as $node) {
            $flat[] = $node->unit;
            $this->flattenTree($node->children, $flat);
        }
    }

    /**
     * ユニットの並び順を再整列
     * @param int<1, max> $initial 並び順の開始番号
     * @return void
     */
    public function resort(int $initial = 1): void
    {
        $tree = $this->tree();

        $this->resortRecursive($tree->getRoots(), $initial);
    }

    /**
     * @param UnitTreeNode[] $nodes
     * @param int<1, max> $initial 並び順の開始番号
     * @return void
     */
    private function resortRecursive(array $nodes, int $initial = 1): void
    {
        $sort = $initial;
        foreach ($nodes as $node) {
            $node->unit->setSort($sort);
            $this->resortRecursive($node->children);
            $sort++;
        }
    }

    /**
     * ユニットを複製し、新しいUnitCollectionを返す
     * @return UnitCollection
     */
    public function clone(): UnitCollection
    {
        $newUnits = [];

        // 先に再帰複製
        foreach ($this->tree()->getRoots() as $node) {
            $this->cloneRecursive($node, null, $newUnits);
        }

        return new UnitCollection($newUnits);
    }

    /**
     * @param UnitTreeNode $node
     * @param non-empty-string|null $newParentId
     * @param Model[] $newUnits
     * @return void
     */
    private function cloneRecursive(
        UnitTreeNode $node,
        ?string $newParentId,
        array &$newUnits
    ): void {
        $oldUnit = $node->unit;
        $newUnit = clone $oldUnit;
        $newUnit->setParentId($newParentId);
        $newId = $newUnit->getId();
        assert($newId !== null);

        $newUnits[] = $newUnit;

        foreach ($node->children as $childNode) {
            $this->cloneRecursive($childNode, $newId, $newUnits);
        }
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->units);
    }

    /**
     * @param callable(Model): mixed $callback
     * @param mixed $arg
     * @return void
     */
    public function walk(callable $callback, $arg = null): void
    {
        array_walk($this->units, $callback, $arg);
    }

    /**
     * @template T
     * @param callable(T, Model): mixed $callback
     * @param T $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->units, $callback, $initial);
    }

    /**
     * @param callable(Model): mixed $callback
     * @return UnitCollection
     */
    public function map(callable $callback): UnitCollection
    {
        $units = array_map($callback, $this->units);
        return new UnitCollection($units);
    }

    /**
     * @param callable(Model): bool $callback
     * @return UnitCollection
     */
    public function filter(callable $callback): UnitCollection
    {
        $units = array_filter($this->units, $callback);
        $units = array_values($units); // 添字をリセット
        return new UnitCollection($units);
    }

    /**
     * 親子関係の整合性を保証したUnitCollectionを返す
     * 親が存在しない子ユニットを削除する
     * @return UnitCollection
     */
    public function normalize(): UnitCollection
    {
        return new UnitCollection($this->tree());
    }

    /**
     * @param callable(Model): bool $callback
     * @return bool
     */
    public function any(callable $callback): bool
    {
        return array_any($this->units, $callback);
    }

    /**
     * @param callable(Model): bool $callback
     * @return bool
     */
    public function all(callable $callback): bool
    {
        return array_all($this->units, $callback);
    }

    /**
     * @param callable(Model): bool $callback
     * @return Model|null
     */
    public function find(callable $callback): ?Model
    {
        return array_find($this->units, $callback);
    }

    /**
     * @param callable(Model): bool $callback
     * @return int|string|null
     */
    public function findKey(callable $callback)
    {
        return array_find_key($this->units, $callback);
    }

    /**
     * @param int $offset
     * @param int|null $length
     * @param bool $preserveKeys
     * @return UnitCollection
     */
    public function slice(int $offset, ?int $length = null, bool $preserveKeys = false): UnitCollection
    {
        return new UnitCollection(array_slice($this->units, $offset, $length, $preserveKeys));
    }

    /**
     * ツリー構造を辿ってコールバック処理を実行する
     * @param callable(UnitTreeNode, int): void $callback
     * @return void
     */
    public function walkTree(callable $callback): void
    {
        $tree = $this->tree();
        foreach ($tree->getRoots() as $i => $node) {
            $this->walkTreeRecursive($node, $callback, $i);
        }
    }

    /**
     * @param UnitTreeNode $node
     * @param callable(UnitTreeNode, int): void $callback
     * @return void
     */
    private function walkTreeRecursive(UnitTreeNode $node, callable $callback, int $i): void
    {
        $callback($node, $i);

        foreach ($node->children as $j => $childNode) {
            $this->walkTreeRecursive($childNode, $callback, $j);
        }
    }

    /**
     * @param UnitTreeNode[] $nodes
     * @return array<int, array<string, mixed>>
     */
    private function treeToArray(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $unit = $node->unit;
            $data = [
                'id' => $unit->getId(),
                'type' => $unit->getType(),
                'name' => $unit->getName(),
                'status' => $unit->getStatus()->value,
                'sort' => $unit->getSort(),
                // 'parentId' => $unit->getParentId(),
                'attributes' => $unit->getAttributes(),
                'children' => $this->treeToArray($node->children),
            ];

            if (config('unit_group') === 'on') {
                $data['group'] = $unit->getGroup();
            }

            if ($unit instanceof AlignableUnitInterface) {
                $data['align'] = $unit->getAlign()->value;
            }

            if ($unit instanceof AnkerUnitInterface) {
                $data['anker'] = $unit->getAnker();
            }

            if ($unit instanceof AttrableUnitInterface) {
                $data['attr'] = $unit->getAttr();
            }

            $result[] = $data;
        }
        return $result;
    }


    /**
     * ツリー上の position にユニット（単体 or 配列）を挿入する
     * - rootId が null の場合…ルート直下に index 挿入
     * - rootId が指定された場合…該当ノードの children に index 挿入
     *
     * @param Model|Model[] $newUnits
     * @param array{rootId?: non-empty-string|null, index?: int<0, max>|null} $position
     * @return void
     */
    public function insertAt(Model|array $newUnits, array $position = []): void
    {
        $rootId = $position['rootId'] ?? null;
        $index = $position['index'] ?? null;

        // 正規化
        $newNodes = $newUnits instanceof Model ? [new UnitTreeNode($newUnits)] : array_map(fn(Model $unit) => new UnitTreeNode($unit), $newUnits);

        // もともとのツリーを取得
        $tree = $this->tree();
        $roots = $tree->getRoots();

        if ($rootId === null) {
            // ルート直下への挿入
            $roots = $this->insertArray($roots, $newNodes, $index);
        } else {
            // 指定 rootId の子へ挿入
            $roots = $this->recursiveInsertNodes($roots, $rootId, $newNodes, $index);
        }

        // ツリー→フラットへ反映し、並び順を調整
        $this->units = $this->toFlat(new UnitTree($roots));
        $this->resort();
    }

    /**
     * ツリーから指定 ID（またはID配列）を削除する
     * @param non-empty-string|string[] $id
     * @return void
     */
    public function remove(string|array $id): void
    {
        $tree  = $this->tree();
        $roots = $tree->getRoots();

        $roots = $this->removeUnitRecursiveNodes($roots, $id);

        // ツリー→フラットへ反映し、並び順を調整
        $this->units = $this->toFlat(new UnitTree($roots));
        $this->resort();
    }

    /**
     * ユニットのメイン画像ユニットを取得
     * @return (\Acms\Services\Unit\Contracts\Model&\Acms\Services\Unit\Contracts\ImageUnit)|null
     */
    public function getPrimaryImageUnit()
    {
        $primaryImageUnit = $this->find(function ($unit) {
            return $unit instanceof ImageUnit && $unit->isPrimaryImage();
        });
        return $primaryImageUnit instanceof ImageUnit ? $primaryImageUnit : null;
    }

    /**
     * メイン画像ユニットを取得し、無効な場合は最初の画像系ユニットを返す
     *
     * @return (\Acms\Services\Unit\Contracts\Model&\Acms\Services\Unit\Contracts\ImageUnit)|null
     */
    public function getPrimaryImageUnitOrFallback(): ?ImageUnit
    {
        $primaryImageUnit = $this->getPrimaryImageUnit();
        if ($primaryImageUnit !== null) {
            // メイン画像ユニットが存在する場合はそれを返す
            return $primaryImageUnit;
        }
        // メイン画像ユニットが存在しない場合は最初の画像系ユニットを返す
        $primaryImageUnit = $this->find(function ($unit) {
            return $unit instanceof ImageUnit && $unit->canBePrimaryImage();
        });
        if ($primaryImageUnit instanceof ImageUnit) {
            // 画像系ユニットが存在する場合は最初の画像系ユニットを返す
            return $primaryImageUnit;
        }

        // 画像系ユニットが存在しない場合はnullを返す
        return null;
    }

    /**
     * メイン画像ユニットを設定する
     *
     * @param non-empty-string $primaryImageUnitId メイン画像ユニットのID
     * @return void
     */
    public function setPrimaryImageUnit(string $primaryImageUnitId): void
    {
        $this->walk(function ($unit) use ($primaryImageUnitId) {
            if (
                $unit->getId() === $primaryImageUnitId &&
                $unit instanceof ImageUnit &&
                $unit->canBePrimaryImage()
            ) {
                $unit->setIsPrimaryImage(true);
            }
        });
    }

    /**
     * ツリー/配列の指定位置に items を挿入（不変）
     * @template T
     * @param array<int,T> $arr     既存配列
     * @param array<int,T> $items   挿入する配列
     * @param int<0, max>|null $index   挿入位置（null は末尾）
     * @return array<int,T>
     */
    private function insertArray(array $arr, array $items, ?int $index = null): array
    {
        $len = count($arr);
        $idx = $index ?? $len;

        if ($idx > $len) {
            throw new \RangeException('index must be less than or equal to length');
        }

        return array_merge(
            array_slice($arr, 0, $idx),
            $items,
            array_slice($arr, $idx)
        );
    }

    /**
     * ツリーを再帰的に辿って、指定 rootId の children に newNodes を挿入
     * @param UnitTreeNode[] $nodes
     * @param string|null $rootId  null の場合はマッチしない（ルート直下への挿入は呼び出し側で実施）
     * @param UnitTreeNode[] $newNodes
     * @param int<0, max>|null $index
     * @return UnitTreeNode[]
     */
    private function recursiveInsertNodes(array $nodes, ?string $rootId, array $newNodes, ?int $index): array
    {
        $result = [];
        foreach ($nodes as $node) {
            if ($rootId !== null && $node->unit->getId() === $rootId) {
                $node->children = $this->insertArray($node->children, $newNodes, $index);
                $result[] = $node;
                continue;
            }

            if (count($node->children) > 0) {
                $node->children = $this->recursiveInsertNodes($node->children, $rootId, $newNodes, $index);
            }
            $result[] = $node;
        }
        return $result;
    }

    /**
     * ツリーを再帰的に辿って、該当 ID（または配列に含まれるID）のノードを除去
     * @param UnitTreeNode[] $nodes
     * @param non-empty-string|string[] $id
     * @return UnitTreeNode[]
     */
    private function removeUnitRecursiveNodes(array $nodes, string|array $id): array
    {
        $ids = is_array($id) ? $id : [$id];

        $filtered = array_values(array_filter($nodes, function (UnitTreeNode $n) use ($ids): bool {
            $nid = $n->unit->getId();
            return !in_array($nid, $ids, true);
        }));

        $result = [];
        foreach ($filtered as $node) {
            if (count($node->children) > 0) {
                $node->children = $this->removeUnitRecursiveNodes($node->children, $ids);
            }
            $result[] = $node;
        }
        return $result;
    }

    public function __clone()
    {
        $this->units = $this->clone()->flat();
    }
}
