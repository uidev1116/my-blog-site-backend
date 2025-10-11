<?php

namespace Acms\Modules\Get\Helpers\Entry;

use SQL;
use SQL_Select;
use ACMS_Filter;

class TagRelationalHelper extends EntryQueryHelper
{
    /**
     * sqlの組み立て
     *
     * @return SQL_Select
     */
    public function buildQuery(): SQL_Select
    {
        $sql = SQL::newSelect('entry', 'entry');
        $sql->addSelect('*');
        $sql->addSelect('tag_name', 'tag_similar_grade', null, 'count');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $sql->addLeftJoin('tag', 'tag_entry_id', 'entry_id');

        $this->filterQuery($sql, []);

        $tagSql = SQL::newSelect('tag');
        $tagSql->addSelect('tag_name');
        $tagSql->addWhereOpr('tag_entry_id', $this->eid);

        $sql->addWhereIn('tag_name', $tagSql);
        $sql->addWhereOpr('entry_id', $this->eid, '!=');

        $this->categoryFilterQuery($sql);

        $this->setCountQuery($sql);
        $this->orderQuery($sql, []);
        $this->limitQuery($sql);
        $sql->addGroup('entry_id');

        return $sql;
    }

    /**
     * 絞り込みクエリ
     * @param SQL_Select $sql
     * @param array $relatedEntryIds
     * @param bool $subCategory
     * @return void
     */
    public function filterQuery(SQL_Select $sql, array $relatedEntryIds, $subCategory = false): void
    {
        ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis);
        ACMS_Filter::entrySession($sql);
        if ($this->start && $this->end) {
            ACMS_Filter::entrySpan($sql, $this->start, $this->end);
        }

        if ($this->config['displaySecretEntry'] ?? false) {
            ACMS_Filter::blogDisclosureSecretStatus($sql);
        } else {
            ACMS_Filter::blogStatus($sql);
        }
        if ($this->keyword) {
            ACMS_Filter::entryKeyword($sql, $this->keyword);
        }
        if ($this->Field && !$this->Field->isNull()) {
            ACMS_Filter::entryField($sql, $this->Field);
        }
        if ($this->config['displayIndexingOnly'] ?? false) {
            $sql->addWhereOpr('entry_indexing', 'on');
        }
        if ($this->config['displayMembersOnly'] ?? false) {
            $sql->addWhereOpr('entry_members_only', 'on');
        }
        if (!($this->config['displayNoImageEntry'] ?? true) && ($this->config['mainImageTarget'] ?? 'unit') !== 'field') {
            $sql->addWhereOpr('entry_primary_image', null, '<>');
        }
    }

    /**
     * Orderクエリ
     * @param SQL_Select $sql
     * @param array $relatedEntryIds
     * @return void
     */
    public function orderQuery(SQL_Select $sql, array $relatedEntryIds): void
    {
        ACMS_Filter::entryOrder($sql, $this->config['order'], $this->uid, $this->cid);
        if ($this->config['order'] === 'relationality') {
            $sql->setOrder('tag_similar_grade', 'DESC');
            $sql->addOrder('entry_datetime', 'DESC');
        }
    }
}
