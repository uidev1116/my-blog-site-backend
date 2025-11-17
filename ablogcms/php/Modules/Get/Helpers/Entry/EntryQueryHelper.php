<?php

namespace Acms\Modules\Get\Helpers\Entry;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Database;
use SQL;
use SQL_Select;
use ACMS_Filter;

class EntryQueryHelper extends BaseHelper
{
    /**
     * エントリー一件のsql組み立て
     * @param int $eid
     * @param null|int $rvid
     * @return SQL_Select
     */
    public function buildEntryQuery(int $eid, ?int $rvid): SQL_Select
    {
        if ($rvid) {
            $sql = SQL::newSelect('entry_rev');
            $sql->addWhereOpr('entry_rev_id', $rvid);
        } else {
            $sql = SQL::newSelect('entry');
        }
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $sql->addWhereOpr('entry_id', $eid);

        return $sql;
    }

    /**
     * エントリー数取得用のSQLを返す
     *
     * @return SQL_Select
     */
    public function getCountQuery(): SQL_Select
    {
        return $this->countQuery;
    }

    /**
     * エントリー一覧のsql組み立て
     * @param array $relatedEntryIds
     * @return SQL_Select
     */
    public function buildEntryIndexQuery(array $relatedEntryIds = []): SQL_Select
    {
        $geoLocation = config('geolocation_entry_function') === 'on';
        $subCategory = isset($this->config['displaySubcategoryEntries']) && $this->config['displaySubcategoryEntries'] && $this->cid;

        $sql = SQL::newSelect('entry', 'entry');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id', 'blog', 'entry');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id', 'category', 'entry');
        if ($geoLocation) {
            $sql->addLeftJoin('geo', 'geo_eid', 'entry_id', 'geo', 'entry');
        }
        $this->filterQuery($sql, $relatedEntryIds, $subCategory);
        $this->setSelect($sql, $geoLocation);
        $this->setCountQuery($sql); // limitする前のクエリから全件取得のクエリを準備しておく
        $this->orderQuery($sql, $relatedEntryIds);
        $this->limitQuery($sql);

        return $sql;
    }

    /**
     * SELECTする項目
     *
     * @param SQL_Select $sql
     * @param bool $geoLocation
     * @return void
     */
    public function setSelect(SQL_Select $sql, bool $geoLocation = false): void
    {
        $list = ['entry_id', 'entry_code', 'entry_status', 'entry_approval', 'entry_form_status', 'entry_sort', 'entry_user_sort', 'entry_category_sort', 'entry_title',
            'entry_link', 'entry_datetime', 'entry_start_datetime', 'entry_end_datetime', 'entry_posted_datetime', 'entry_updated_datetime', 'entry_summary_range', 'entry_indexing',
            'entry_members_only', 'entry_primary_image', 'entry_current_rev_id', 'entry_last_update_user_id', 'entry_category_id', 'entry_user_id', 'entry_form_id', 'entry_blog_id',
            'blog_id', 'blog_code', 'blog_status', 'blog_parent', 'blog_name', 'blog_domain', 'blog_indexing', 'blog_alias_status', 'blog_alias_sort', 'blog_alias_primary',
            'category_id', 'category_code', 'category_status', 'category_parent', 'category_sort', 'category_name', 'category_scope', 'category_indexing', 'category_blog_id',
        ];
        if ($geoLocation) {
            $list[] = 'geo_geometry';
            $list[] = 'geo_zoom';
        }
        foreach ($list as $name) {
            $sql->addSelect($name);
        }
        foreach ($this->sortFields as $name) {
            $sql->addSelect($name);
        }
        if ($geoLocation) {
            $sql->addSelect('geo_geometry', 'latitude', 'geo', 'ST_Y');
            $sql->addSelect('geo_geometry', 'longitude', 'geo', 'ST_X');
        }
    }

    /**
     * Orderクエリ
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function orderQuery(SQL_Select $sql, array $relatedEntryIds): void
    {
        if (($this->config['relatedEntryMode'] ?? false) && count($relatedEntryIds) > 0) {
            $sql->addGroup('entry_id');
            $sql->setFieldOrder('entry_id', $relatedEntryIds);
            return;
        }
        $sortFd = false;
        if ($this->config['noNarrowDownSort'] ?? false) {
            // カテゴリー、ユーザー絞り込み時でも、絞り込み時用のソートを利用しない
            $sortFd = ACMS_Filter::entryOrder($sql, $this->config['order'], null, null, false, $this->config['orderFieldName'] ?? '');
        } else {
            $sortFd = ACMS_Filter::entryOrder($sql, $this->config['order'], $this->uid, $this->cid, false, $this->config['orderFieldName'] ?? '');
        }
        if ($sortFd) {
            $sql->setGroup($sortFd);
        }
        $sql->addGroup('entry_id');
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
        $this->countQuery->setSelect(SQL::newFunction('entry_id', 'DISTINCT'), 'entry_amount', null, 'COUNT');
    }

    /**
     * limitクエリ
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function limitQuery(SQL_Select $sql): void
    {
        $from = ($this->page - 1) * (int) $this->config['limit'] + (int) $this->config['offset'];
        $limit = $this->config['limit'] + 1;
        $sql->setLimit($limit, $from);
    }

    /**
     * 絞り込みクエリ
     * @param SQL_Select $sql
     * @param array $relatedEntryIds
     * @param bool $subCategory
     * @return void
     */
    public function filterQuery(SQL_Select $sql, array $relatedEntryIds, bool $subCategory = false): void
    {
        $private = $this->config['hiddenPrivateEntry'] ?? false;
        if ($this->start && $this->end) {
            ACMS_Filter::entrySpan($sql, $this->start, $this->end);
        }
        ACMS_Filter::entrySession($sql, null, $private);

        if ($this->relationalFilterQuery($sql, $relatedEntryIds)) {
            return;
        }
        $multi = $this->categoryFilterQuery($sql, $subCategory);
        $multi = $this->userFilterQuery($sql) || $multi;
        $multi = $this->entryFilterQuery($sql) || $multi;
        $this->blogFilterQuery($sql, $multi);

        $this->tagFilterQuery($sql);
        $this->keywordFilterQuery($sql);
        $this->fieldFilterQuery($sql);
        $this->otherFilterQuery($sql);
    }

    /**
     * 関連エントリーの絞り込み
     * @param SQL_Select $sql
     * @param array $relatedEntryIds
     * @return bool
     */
    public function relationalFilterQuery(SQL_Select $sql, array $relatedEntryIds): bool
    {
        if ($this->config['relatedEntryMode'] ?? false) {
            $sql->addWhereIn('entry_id', $relatedEntryIds);
            return true;
        }
        return false;
    }

    /**
     * カテゴリーの絞り込み
     *
     * @param SQL_Select $sql
     * @param bool $subCategory
     * @return bool
     */
    public function categoryFilterQuery(SQL_Select $sql, $subCategory = false): bool
    {
        $multi = false;

        if ($this->cid) {
            $subCategorySql = SQL::newSelect('entry_sub_category', 'sub_category');
            $subCategorySql->setSelect(SQL::newField(fd: '1', quote: false));
            $subCategorySql->addWhereOpr('entry_sub_category_eid', SQL::newField('entry.entry_id'), '=', 'AND', 'sub_category');

            if ($this->categoryAxis === 'self' || $this->cids) {
                if (!$this->cids) {
                    if ($subCategory) {
                        $subCategorySql->addWhereOpr('entry_sub_category_id', $this->cid, '=', 'AND', 'sub_category');
                        $existsSql = SQL::newOprExists($subCategorySql);

                        $where = SQL::newWhere();
                        $where->addWhereOpr('entry_category_id', $this->cid, '=', 'OR', 'entry');
                        $where->addWhere($existsSql, 'OR');
                        $sql->addWhere($where);
                    } else {
                        $sql->addWhereOpr('category_id', $this->cid);
                    }
                } else {
                    $multi = true;
                    $where = SQL::newWhere();
                    $where->addWhereIn('entry_category_id', $this->cids, 'OR', 'entry');
                    if ($subCategory) {
                        $subCategorySql->addWhereIn('entry_sub_category_id', $this->cids, 'AND', 'sub_category');
                        $existsSql = SQL::newOprExists($subCategorySql);
                        $where->addWhere($existsSql, 'OR');
                    }
                    $sql->addWhere($where);
                }
                if ($this->config['displaySecretEntry'] ?? false) {
                    ACMS_Filter::categoryDisclosureSecretStatus($sql);
                } else {
                    ACMS_Filter::categoryStatus($sql);
                }
            } else {
                $categorySql = SQL::newSelect('category');
                $categorySql->setSelect('category_id');
                $isSelf = ACMS_Filter::categoryTree($categorySql, $this->cid, $this->categoryAxis);
                if ($this->config['displaySecretEntry'] ?? false) {
                    ACMS_Filter::categoryDisclosureSecretStatus($categorySql);
                } else {
                    ACMS_Filter::categoryStatus($categorySql);
                }
                $where = SQL::newWhere();
                if ($isSelf) {
                    $where->addWhereOpr('entry_category_id', $this->cid, '=', 'OR', 'entry');
                } else {
                    $where->addWhereIn('entry_category_id', Database::subQuery($categorySql), 'OR', 'entry');
                }
                if ($subCategory) {
                    if ($isSelf) {
                        $subCategorySql->addWhereOpr('entry_sub_category_id', $this->cid, '=', 'AND', 'sub_category');
                    } else {
                        $subCategorySql->addWhereIn('entry_sub_category_id', Database::subQuery($categorySql), 'AND', 'sub_category');
                    }
                    $existsSql = SQL::newOprExists($subCategorySql);
                    $where->addWhere($existsSql, 'OR');
                }
                $sql->addWhere($where);
            }
        } else {
            if ($this->config['displaySecretEntry'] ?? false) {
                ACMS_Filter::categoryDisclosureSecretStatus($sql);
            } else {
                ACMS_Filter::categoryStatus($sql);
            }
        }
        return $multi;
    }

    /**
     * ユーザーの絞り込み
     *
     * @param SQL_Select $sql
     * @return bool
     */
    public function userFilterQuery(SQL_Select $sql): bool
    {
        $multi = false;
        if ($this->uids) {
            $sql->addWhereIn('entry_user_id', $this->uids);
            $multi = true;
        } elseif ($this->uid) {
            $sql->addWhereOpr('entry_user_id', $this->uid);
        }
        return $multi;
    }

    /**
     * エントリーの絞り込み
     *
     * @param SQL_Select $sql
     * @return bool
     */
    public function entryFilterQuery(SQL_Select $sql): bool
    {
        $multi = false;
        if ($this->eids) {
            $sql->addWhereIn('entry_id', $this->eids);
            $multi = true;
        } elseif ($this->eid) {
            $sql->addWhereOpr('entry_id', $this->eid);
        }
        return $multi;
    }

    /**
     * ブログの絞り込み
     *
     * @param SQL_Select $sql
     * @param bool $multi
     * @return void
     */
    public function blogFilterQuery(SQL_Select $sql, bool $multi): void
    {
        if ($this->bid && !$this->bids && $this->blogAxis === 'self') {
            $sql->addWhereOpr('entry_blog_id', $this->bid);
            if ($this->config['displaySecretEntry'] ?? false) {
                ACMS_Filter::blogDisclosureSecretStatus($sql);
            } else {
                ACMS_Filter::blogStatus($sql);
            }
        } elseif ($this->bid) {
            $blogSubQuery = SQL::newSelect('blog');
            $blogSubQuery->setSelect('blog_id');
            if ($this->bids) {
                $blogSubQuery->addWhereIn('blog_id', $this->bids);
            } else {
                if ($multi) {
                    ACMS_Filter::blogTree($blogSubQuery, $this->bid, 'descendant-or-self');
                } else {
                    ACMS_Filter::blogTree($blogSubQuery, $this->bid, $this->blogAxis);
                }
            }
            if ($this->config['displaySecretEntry'] ?? false) {
                ACMS_Filter::blogDisclosureSecretStatus($blogSubQuery);
            } else {
                ACMS_Filter::blogStatus($blogSubQuery);
            }
            $q = $blogSubQuery->get(dsn());
            if ($blogIds = Database::query($q, 'list')) {
                $sql->addWhereIn('entry_blog_id', $blogIds);
            }
        }
    }

    /**
     * タグの絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function tagFilterQuery(SQL_Select $sql): void
    {
        if ($this->tags) {
            ACMS_Filter::entryTag($sql, $this->tags);
        }
    }

    /**
     * キーワードの絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function keywordFilterQuery(SQL_Select $sql): void
    {
        if ($this->keyword) {
            ACMS_Filter::entryKeyword($sql, $this->keyword);
        }
    }

    /**
     * フィールドの絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function fieldFilterQuery(SQL_Select $sql): void
    {
        if ($this->Field && !$this->Field->isNull()) {
            $this->sortFields = ACMS_Filter::entryField($sql, $this->Field);
        }
    }

    /**
     * その他の絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function otherFilterQuery(SQL_Select $sql): void
    {
        if ($this->config['displayIndexingOnly'] ?? false) {
            $sql->addWhereOpr('entry_indexing', 'on');
        }
        if ($this->config['displayMembersOnly'] ?? false) {
            $sql->addWhereOpr('entry_members_only', 'on');
        }
        if (!($this->config['displayNoImageEntry'] ?? true) && ($this->config['mainImageTarget'] ?? 'unit') !== 'field') {
            $sql->addWhereOpr('entry_primary_image', null, '<>');
        }
        if (EID && ($this->config['hiddenCurrentEntry'] ?? false)) { // @phpstan-ignore-line
            $sql->addWhereOpr('entry_id', EID, '<>');
        }
    }
}
