<?php

namespace Acms\Traits\Unit;

use Acms\Services\Facades\Database;
use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Services\Unit\Contracts\AttrableUnitInterface;
use Acms\Services\Unit\Contracts\SizeableUnitInterface;
use SQL;

trait UnitModelTrait
{
    /**
     * 新しいユニットIDを発行
     *
     * @return non-empty-string
     */
    public function generateNewIdTrait(): string
    {
        $id = uuidv4();
        return $id;
    }

    /**
     * ユニットをデータベースに保存
     *
     * @param \Acms\Services\Unit\Contracts\Model $model
     * @param bool $isRevision
     * @return void
     */
    public function insertDataTrait(Model $model, bool $isRevision): void
    {
        $tableName = $isRevision ? 'column_rev' : 'column';

        $sql = SQL::newInsert($tableName);
        $sql->addInsert('column_id', $model->getId());
        $sql->addInsert('column_sort', $model->getSort());
        $sql->addInsert('column_entry_id', $model->getEntryId());
        $sql->addInsert('column_blog_id', $model->getBlogId());
        if ($isRevision) {
            $sql->addInsert('column_rev_id', $model->getRevId());
        }
        $sql->addInsert('column_type', $model->getType());
        if ($model instanceof AlignableUnitInterface) {
            $sql->addInsert('column_align', $model->getAlign()->value);
        }
        $sql->addInsert('column_status', $model->getStatus()->value);
        if ($model instanceof AttrableUnitInterface) {
            $sql->addInsert('column_attr', $model->getAttr());
        }
        if ($model instanceof AnkerUnitInterface) {
            $sql->addInsert('column_anker', $model->getAnker());
        }
        if (config('unit_group') === 'on') {
            $sql->addInsert('column_group', $model->getGroup());
        }
        if ($model instanceof SizeableUnitInterface) {
            $sql->addInsert('column_size', $model->getSize());
        }
        $sql->addInsert('column_parent_id', $model->getParentId());
        $sql->addInsert('column_field_1', $model->getField1());
        $sql->addInsert('column_field_2', $model->getField2());
        $sql->addInsert('column_field_3', $model->getField3());
        $sql->addInsert('column_field_4', $model->getField4());
        $sql->addInsert('column_field_5', $model->getField5());
        $sql->addInsert('column_field_6', $model->getField6());
        $sql->addInsert('column_field_7', $model->getField7());
        $sql->addInsert('column_field_8', $model->getField8());
        $model->extendInsertQuery($sql, $isRevision);

        $query = $sql->get(dsn());
        Database::query($query, 'exec');
    }
}
