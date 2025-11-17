<?php

namespace Acms\Traits\Unit;

use Acms\Services\Facades\Database;
use Acms\Services\Unit\Contracts\Model;
use SQL;

trait UnitRepositoryTrait
{
    /**
     * 指定したエントリーのユニット数をカウント
     *
     * @param int<1, max> $eid
     * @return int
     */
    public function countUnitsTrait(int $eid): int
    {
        $sql = SQL::newSelect('column');
        $sql->addSelect('*', 'column_amount', null, 'COUNT');
        $sql->addWhereOpr('column_attr', 'acms-form', '<>');
        $sql->addWhereOpr('column_entry_id', $eid);

        return (int) Database::query($sql->get(dsn()), 'one');
    }

    /**
     * 指定したエントリーとソート番号のユニットを取得
     *
     * @param int<1, max> $eid
     * @param int $sort
     * @return array|null
     */
    public function getUnitBySortTrait(int $eid, int $sort): ?array
    {
        $sql = SQL::newSelect('column');
        $sql->addSelect('column_id');
        $sql->addWhereOpr('column_sort', $sort);
        $sql->addWhereOpr('column_entry_id', $eid);
        $sql->addWhereOpr('column_attr', 'acms-form', '<>');
        $sql->setOrder('column_id', 'DESC');

        if ($data = Database::query($sql->get(dsn()), 'row')) {
            return $data;
        }
        return null;
    }

    /**
     * ユニットをロード
     *
     * @param non-empty-string $unitId
     * @return null|array
     */
    public function loadUnitFromDBTrait(string $unitId): ?array
    {
        $sql = SQL::newSelect('column');
        $sql->addWhereOpr('column_id', $unitId);

        if ($data = Database::query($sql->get(dsn()), 'row')) {
            return $data;
        }
        return null;
    }

    /**
     * データベースからエントリーのユニットをロード
     *
     * @param int $eid
     * @param int|null $rvid
     * @param int|null $range
     * @return array
     */
    public function loadUnitsFromDBTrait(int $eid, ?int $rvid = null, ?int $range = null): array
    {
        if ($rvid) {
            $sql = SQL::newSelect('column_rev');
            $sql->addWhereOpr('column_rev_id', $rvid);
        } else {
            $sql = SQL::newSelect('column');
        }
        $sql->addWhereOpr('column_entry_id', $eid);
        $sql->addWhereOpr('column_attr', 'acms-form', '<>');
        $sql->setOrder('column_sort');
        if (!is_null($range)) {
            $sql->setLimit($range);
        }
        $q = $sql->get(dsn());

        return Database::query($q, 'all');
    }

    /**
     * 1ユニットの削除
     * 階層構造には対応していないので、親ユニットのIDを指定しても削除されません
     *
     * @param non-empty-string $unitId
     * @param int|null $rvid
     * @return void
     */
    public function removeUnitTrait(string $unitId, ?int $rvid = null): void
    {
        $isRevision = $rvid && $rvid > 0;
        $tableName = $isRevision ? 'column_rev' : 'column';
        $sql = SQL::newDelete($tableName);
        $sql->addWhereOpr('column_id', $unitId);
        if ($isRevision) {
            $sql->addWhereOpr('column_rev_id', $rvid);
        }
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * 全ユニットを削除
     *
     * @param int $eid
     * @param int|null $rvid
     * @return void
     */
    public function removeUnitsTrait(int $eid, ?int $rvid = null): void
    {
        $isRevision = $rvid && $rvid > 0;
        $tableName = $isRevision ? 'column_rev' : 'column';

        // 削除対象ユニットIDを取得
        $select = SQL::newSelect($tableName);
        $select->addSelect('column_id');
        $select->addWhereOpr('column_entry_id', $eid, '=', 'AND');
        $select->addWhereOpr('column_attr', 'acms-form', '<>', 'AND');
        $sub = SQL::newWhere();
        $sub->addWhereOpr('column_type', 'custom', '=', 'OR');
        $sub->addWhereOpr('column_type', 'custom\_%', 'LIKE', 'OR');
        $select->addWhere($sub, 'AND');
        if ($isRevision) {
            $select->addWhereOpr('column_rev_id', $rvid);
        }
        $removeUnitIds = Database::query($select->get(dsn()), 'list'); // column_id の配列

        // field / field_rev テーブルから対応するfield_unit_idを削除
        if ($removeUnitIds) {
            $fieldTableName = $isRevision ? 'field_rev' : 'field';
            $delete = SQL::newDelete($fieldTableName);
            $delete->addWhereIn('field_unit_id', $removeUnitIds);
            if ($isRevision) {
                $delete->addWhereOpr('field_rev_id', $rvid);
            }
            Database::query($delete->get(dsn()), 'exec');
        }

        // column / column_rev テーブルからユニットを削除
        $sql = SQL::newDelete($tableName);
        $sql->addWhereOpr('column_entry_id', $eid);
        $sql->addWhereOpr('column_attr', 'acms-form', '<>');
        if ($isRevision) {
            $sql->addWhereOpr('column_rev_id', $rvid);
        }
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * 指定したエントリーのユニットに存在するリビジョンIDを取得
     *
     * @param int $eid
     * @return int[]
     */
    public function getRevisionIds(int $eid): array
    {
        $sql = SQL::newSelect('column_rev');
        $sql->setSelect('column_rev_id', 'rvid', null, 'DISTINCT');
        $sql->addWhereOpr('column_entry_id', $eid);

        return Database::query($sql->get(dsn()), 'list');
    }

    /**
     * 指定した順番以降のユニット並び番号を更新する
     *
     * 新規カラム挿入に伴い、指定位置（$position['sort']）を含む「以降の」カラムを後方にずらす。
     *
     * - `>=` を使用することで、挿入位置に既に存在するカラムも含めて後方に移動する。
     * - これにより、挿入位置に確実に空きスペースを作成できる。
     *
     * @param array{sort: positive-int, parentId: non-empty-string|null} $position
     * @param int $eid
     * @param int|null $rvid
     * @param int $length
     * @return void
     */
    public function formatOrderWithInsertionTrait(
        array $position,
        int $eid,
        ?int $rvid = null,
        int $length = 1
    ): void {
        if ($rvid !== null && $rvid > 0) {
            $sql = SQL::newUpdate('column_rev');
            $sql->addWhereOpr('column_rev_id', $rvid);
        } else {
            $sql = SQL::newUpdate('column');
        }
        $sql->addUpdate('column_sort', SQL::newField('`column_sort` + ' . $length, null, false));
        $sql->addWhereOpr('column_sort', $position['sort'], '>=');
        $sql->addWhereOpr('column_parent_id', $position['parentId']);
        $sql->addWhereOpr('column_entry_id', $eid);

        $query = $sql->get(dsn());
        Database::query($query, 'exec');
    }

    /**
     * 指定した順番以降のユニット並び番号を更新する
     *
     * ユニット削除に伴い、指定位置（$position['sort']）を含む「以降の」カラムを前方にずらす。
     *
     * - `>` を使用することで、削除位置に既に存在するカラムも含めて前方に移動する。
     *
     * @param array{sort: positive-int, parentId: non-empty-string|null} $position
     * @param int $eid
     * @param int|null $rvid
     * @param int $length
     * @return void
     */
    public function formatOrderWithRemovalTrait(
        array $position,
        int $eid,
        ?int $rvid = null,
        int $length = 1
    ): void {
        if ($rvid !== null && $rvid > 0) {
            $sql = SQL::newUpdate('column_rev');
            $sql->addWhereOpr('column_rev_id', $rvid);
        } else {
            $sql = SQL::newUpdate('column');
        }
        $sql->addUpdate('column_sort', SQL::newField('`column_sort` - ' . $length, null, false));
        $sql->addWhereOpr('column_sort', $position['sort'], '>');
        $sql->addWhereOpr('column_parent_id', $position['parentId']);
        $sql->addWhereOpr('column_entry_id', $eid);

        $query = $sql->get(dsn());
        Database::query($query, 'exec');
    }


    /**
     * 保存時に1つのユニットから複数ユニットに増加できるユニットの処理
     * 多言語ユニットの場合はマルチアップロード非対応
     *
     * @param \Acms\Services\Unit\Contracts\Model $model
     * @return \Acms\Services\Unit\Contracts\Model[] 元のユニットを含んでいます
     */
    protected function handleMultipleUnitsTrait(Model $model): array
    {
        $items = [$model];
        $id = $model->getId();

        if ($model instanceof \Acms\Services\Unit\Models\Media) {
            // メディアユニットの場合
            $captions = $_POST["media_caption_{$id}"] ?? '';
            if (is_array($captions)) {
                // 多言語ユニットの場合はマルチアップロード非対応
                return $items;
            } else {
                // 通常メディアユニットの場合
                $mediaIds = $_POST["media_id_{$id}"] ?? [];
                $mediaSize = $_POST["media_size_{$id}"][0] ?? ''; // メディアサイズは配列で管理しているが、1つしか対応していない

                if (!is_array($mediaIds)) {
                    $mediaIds = [$mediaIds];
                }
                foreach ($mediaIds as $i => $mid) {
                    if ($i === 0) {
                        // 元のユニットは除外
                        continue;
                    }
                    $newModel = clone $model;
                    $newId = $newModel->getId();
                    $_POST["media_id_{$newId}"] = $mid;
                    $_POST["media_size_{$newId}"] = $mediaSize;
                    $items[] = $newModel;
                }
            }
        } elseif ($model instanceof \Acms\Services\Unit\Models\Image) {
            // 画像ユニットの場合
            $captions = $_POST["image_caption_{$id}"] ?? '';
            if (is_array($captions)) {
                // 多言語ユニットの場合はマルチアップロード非対応
                return $items;
            } else {
                // 通常画像ユニットの場合
                $imageFiles = $_POST["image_file_{$id}"] ?? [];
                $tmpFiles = $_FILES["image_file_{$id}"]['tmp_name'] ?? [];
                $exifAry = $_POST["image_exif_{$id}"] ?? [];
                $old = $_POST["image_old_{$id}"] ?? null;
                $oldSize = $_POST["old_image_size_{$id}"] ?? '';
                $imageSize = $_POST["image_size_{$id}"] ?? '';
                $imageEdit = $_POST["image_edit_{$id}"] ?? '';
                $imageCaption = $_POST["image_caption_{$id}"] ?? '';
                $imageLink = $_POST["image_link_{$id}"] ?? '';
                $imageAlt = $_POST["image_alt_{$id}"] ?? '';

                if (count($imageFiles) === 0) {
                    return $items;
                }
                if (!is_array($imageFiles)) {
                    $imageFiles = [$imageFiles];
                }
                if (!is_array($tmpFiles)) {
                    $tmpFiles = [$tmpFiles];
                }
                if (!is_array($exifAry)) {
                    $exifAry = [$exifAry];
                }
                foreach ($imageFiles as $i => $file) {
                    if ($i === 0) {
                        // 元のユニットは除外
                        continue;
                    }
                    $newModel = clone $model;
                    $newId = $newModel->getId();
                    $_POST["image_file_{$newId}"] = $file;
                    $_POST["image_exif_{$newId}"] = $exifAry[$i] ?? '';
                    $_FILES["image_file_{$newId}"]['tmp_name'] = [$tmpFiles[$i] ?? ''];
                    $_POST["image_old_{$newId}"] = $old;
                    $_POST["old_image_size_{$newId}"] = $oldSize;
                    $_POST["image_size_{$newId}"] = $imageSize;
                    $_POST["image_edit_{$newId}"] = $imageEdit;
                    $_POST["image_caption_{$newId}"] = $imageCaption;
                    $_POST["image_link_{$newId}"] = $imageLink;
                    $_POST["image_alt_{$newId}"] =  $imageAlt;
                    $items[] = $newModel;
                }
            }
        } elseif ($model instanceof \Acms\Services\Unit\Models\File) {
            // ファイルユニットの場合
            $captions = $_POST["file_caption_{$id}"] ?? null;
            if (is_array($captions)) {
                // 多言語ユニットの場合はマルチアップロード非対応
                return $items;
            }
            // 通常ファイルユニットの場合

            $tmpFiles = $_FILES["file_file_{$id}"]['tmp_name'] ?? [];
            $tmpFileNames = $_FILES["file_file_{$id}"]['name'] ?? [];
            $fileEdit = $_POST["file_edit_{$id}"] ?? '';
            $fileCaption = $_POST["file_caption_{$id}"] ?? '';
            $fileOldPath = $_POST["file_old_{$id}"] ?? null;

            if (count($tmpFiles) === 0) {
                return $items;
            }
            if (!is_array($tmpFiles)) {
                $tmpFiles = [$tmpFiles];
            }
            if (!is_array($tmpFileNames)) {
                $tmpFileNames = [$tmpFileNames];
            }

            foreach ($tmpFiles as $i => $file) {
                if ($i === 0) {
                    // 元のユニットは除外
                    continue;
                }
                $newModel = clone $model;
                $newId = $newModel->getId();
                $_FILES["file_file_{$newId}"]['tmp_name'] = $file;
                $_FILES["file_file_{$newId}"]['name'] = $tmpFileNames[$i] ?? '';
                $_POST["file_old_{$newId}"] = $fileOldPath;
                $_POST["file_edit_{$newId}"] = $fileEdit;
                $_POST["file_caption_{$newId}"] = $fileCaption;
                $items[] = $newModel;
            }
        }
        return $items;
    }
}
