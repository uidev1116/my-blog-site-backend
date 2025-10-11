<?php

namespace Acms\Modules\Get\Helpers\Entry;

use SQL;
use SQL_Select;
use ACMS_Filter;

class UnitListHelper extends EntryQueryHelper
{
    /**
     * sqlの組み立て
     *
     * @return SQL_Select
     */
    public function buildUnitListQuery()
    {
        $sql = SQL::newSelect('column');
        $sql->addLeftJoin('entry', 'entry_id', 'column_entry_id');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');

        ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis);
        ACMS_Filter::blogStatus($sql);
        if ($this->cid) {
            ACMS_Filter::categoryTree($sql, $this->cid, $this->categoryAxis);
        }
        ACMS_Filter::categoryStatus($sql);

        $this->userFilterQuery($sql);
        $this->keywordFilterQuery($sql);
        $this->tagFilterQuery($sql);
        $this->fieldFilterQuery($sql);

        if ($this->eid) {
            $sql->addWhereOpr('column_entry_id', $this->eid);
        }
        ACMS_Filter::entrySession($sql);
        if ($this->start && $this->end) {
            ACMS_Filter::entrySpan($sql, $this->start, $this->end);
        }
        $sql->addWhereIn('column_type', array_merge(
            configArray('column_list_type'),
            configArray('column_list_extends_type')
        ));
        $this->setCountQuery($sql); // limitする前のクエリから全件取得のクエリを準備しておく
        $this->orderQuery($sql, []);
        $this->limitQuery($sql);

        return $sql;
    }

    /**
     * エントリー数取得sqlの準備
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function setCountQuery(SQL_Select $sql): void
    {
        $this->countQuery = new SQL_Select(clone $sql);
        $this->countQuery->addSelect(SQL::newFunction('column_id', 'DISTINCT'), 'unit_amount', null, 'COUNT');
    }

    /**
     * Orderクエリ
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function orderQuery(SQL_Select $sql, array $relatedEntryIds): void
    {
        $order = $this->config['order'][0];
        if ('random' === $order) {
            $sql->setOrder('RAND()');
        } else {
            if ('datetime-asc' === $order) {
                $sql->addOrder('entry_datetime', 'ASC');
            } else {
                $sql->addOrder('entry_datetime', 'DESC');
            }
        }
    }
}
