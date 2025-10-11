<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Database;
use ACMS_Filter;
use SQL;

class FieldValueHelper extends BaseHelper
{
    /**
     * フィールド値の一覧を取得する
     *
     * @param integer $limit
     * @param 'ASC' | 'DESC' $order
     * @return array
     */
    public function getFieldValueData(int $limit, string $order): array
    {
        $sql = SQL::newSelect('field');
        $sql->addSelect('field_value');
        $sql->addLeftJoin('blog', 'blog_id', 'field_blog_id');

        ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis);
        ACMS_Filter::blogStatus($sql);
        if ($this->Field) {
            ACMS_Filter::fieldList($sql, $this->Field);
        }

        $sql->setLimit($limit);
        $sql->addWhereOpr('field_value', '', '<>');
        $sql->addWhereOpr('field_value', null, '<>');
        $sql->setGroup('field_value');
        $direction = strtoupper($order);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }
        $sql->setOrder('field_value', $direction);
        $q = $sql->get(dsn());
        $all = Database::query($q, 'all');

        return array_column($all, 'field_value');
    }
}
