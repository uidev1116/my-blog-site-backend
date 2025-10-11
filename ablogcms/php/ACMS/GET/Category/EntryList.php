<?php

use Acms\Services\Logger\Deprecated;

class ACMS_GET_Category_EntryList extends ACMS_GET
{
    public $_scope  = [
        'cid'   => 'global',
    ];

    public $_config = [];

    protected $entries = [];

    protected $eagerLoadingData = [];

    /**
     * @inheritdoc
     */
    protected function initVars()
    {
        return [
            'categoryOrder'                 => config('category_entry_list_category_order'),
            'categoryEntryListLevel'        => config('category_entry_list_level'),
            'categoryIndexing'              => config('category_entry_list_category_indexing'),
            'entryAmountZero'               => config('category_entry_list_entry_amount_zero'),
            'subCategory'                   => config('category_entry_list_sub_category'),
            'entryActiveCategory'           => config('category_entry_list_entry_active_category'),
            'order'                         => config('category_entry_list_entry_order'),
            'limit'                         => config('category_entry_list_entry_limit'),
            'indexing'                      => config('category_entry_list_entry_indexing'),
            'categoryLoopClass'             => config('category_entry_list_category_loop_class'),
            'entryLoopClass'                => config('category_entry_list_entry_loop_class'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function get()
    {
        if (get_called_class() === __CLASS__) {
            Deprecated::once('Category_EntryList モジュール', [
                'since' => '3.2.0',
                'alternative' => ' Category_EntrySummary モジュール',
            ]);
        }

        $this->_config = $this->initVars();

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);
        $DB     = DB::singleton(dsn());

        $aryStack   = [intval($this->cid)];
        $aryCount   = [];
        $aryHidden  = [];
        $eagerLoadCategoryFields = $this->eagerLoadCategoryFields();

        $sqlStock = [];
        while (array_key_exists(0, $aryStack)) {
            $pid = $aryStack[0];

            if (isset($sqlStock[$pid])) {
                $categoryStmt = $sqlStock[$pid];
            } else {
                $SQL = SQL::newSelect('category');
                $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
                $SQL->addWhereOpr('category_parent', $pid);
                ACMS_Filter::blogTree($SQL, $this->bid, 'ancestor-or-self');
                ACMS_Filter::categoryStatus($SQL);
                ACMS_Filter::categoryOrder($SQL, $this->_config['categoryOrder']);

                $Where  = SQL::newWhere();
                $Where->addWhereOpr('category_blog_id', $this->bid, '=', 'OR');
                $Where->addWhereOpr('category_scope', 'global', '=', 'OR');
                $SQL->addWhere($Where);
                $categoryStmt = $DB->query($SQL->get(dsn()), 'exec');
                $sqlStock[$pid] = $categoryStmt;
            }
            $level  = 0;
            foreach ($aryStack as $cid) {
                if (empty($aryHidden[$cid])) {
                    $level++;
                }
            }
            $cid    = null;

            if (intval($this->_config['categoryEntryListLevel']) >= $level) {
                while (!!($cRow = $DB->next($categoryStmt))) {
                    $cid = intval($cRow['category_id']);
                    $this->entries = [];
                    $entryStmt = null;

                    //--------------------
                    // entry build query
                    if (!('on' == $this->_config['categoryIndexing'] and 'on' <> $cRow['category_indexing'])) {
                        if ($eQ = $this->buildQuery($cid, $Tpl)) {
                            $entryStmt = $DB->query($eQ, 'exec');
                            if ($entryStmt && !!($eRow = $DB->next($entryStmt))) {
                            }
                        }
                    }

                    if (
                        1
                        and !('on' == $this->_config['categoryIndexing'] and 'on' <> $cRow['category_indexing'])
                        and !('on' <> $this->_config['entryAmountZero'] and empty($eRow))
                    ) {
                        //-------
                        // entry
                        if (isset($this->_config['notfound']) && $this->_config['notfound'] === 'on' && empty($eRow)) {
                            $Tpl->add('notFound');
                        }
                        if (isset($this->_config['entryActiveCategory']) && 'on' == $this->_config['entryActiveCategory'] && ($cid != CID || intval(CID) == 0)) {
                        } else {
                            $i = 0;
                            if (!empty($eRow) && $entryStmt) {
                                do {
                                    $i++;
                                    $this->entries[$i] = $eRow;
                                } while (!!($eRow = $DB->next($entryStmt)));
                            }
                            foreach ($this->entries as $entry) {
                                ACMS_RAM::entry($entry['entry_id'], $entry);
                            }
                            $this->preBuildUnit();
                            foreach ($this->entries as $j => $entry) {
                                $this->buildUnit($entry, $Tpl, $cid, $level, $j);
                            }
                        }

                        //----------
                        // category
                        $vars = [];
                        $vars += [
                            'categoryUrl'   => acmsLink([
                                'bid'   => $this->bid,
                                'cid'   => $cid,
                            ]),
                            'categoryName'  => $cRow['category_name'],
                            'categoryLevel' => $level,
                            'categoryCode'  => $cRow['category_code'],
                            'categoryId'    => $cid,
                            'categoryPid'   => $pid,
                            'category:loop.class'   => $this->_config['categoryLoopClass'],
                        ];

                        if (!isset($this->_config['categoryFieldOn']) or $this->_config['categoryFieldOn'] === 'on') {
                            if (isset($eagerLoadCategoryFields[$cid])) {
                                $vars += $this->buildField($eagerLoadCategoryFields[$cid], $Tpl);
                            }
                        }

                        if (empty($aryCount[$pid])) {
                            if (empty($aryHidden[$pid])) {
                                $Tpl->add('categoryUl#front');
                            }
                            $aryCount[$pid] = 0;
                        }
                        $aryCount[$pid]++;

                        $Tpl->add('category:loop', $vars);
                        $Tpl->add('categoryEntryList:loop', ['debug' => 'bug']);
                    } else {
                        $aryHidden[$cid]    = true;
                    }

                    if (intval($this->_config['categoryEntryListLevel']) >= $level) {
                        array_unshift($aryStack, $cid);
                    }
                    break;
                }
            }

            if (is_null($cid)) {
                array_shift($aryStack);
                if (empty($aryHidden[$pid])) {
                    if (!empty($aryCount[$pid])) {
                        $Tpl->add('categoryUl#rear');
                        $Tpl->add('categoryEntryList:loop');
                    }
                    if (!empty($aryStack)) {
                        $Tpl->add('categoryLi#rear');
                        $Tpl->add('categoryEntryList:loop');
                    }
                }
            }
        }

        $rootVars = $this->getRootVars();
        $Tpl->add(null, $rootVars);
        return $Tpl->get();
    }

    /**
     * @param SQL_Select $SQL
     * @return void
     */
    protected function filterQuery($SQL)
    {
        ACMS_Filter::entrySpan($SQL, $this->start, $this->end);
        ACMS_Filter::entrySession($SQL);
        if ('on' == $this->_config['indexing']) {
            $SQL->addWhereOpr('entry_indexing', 'on');
        }
        $SQL->addWhereOpr('entry_blog_id', $this->bid);

        if (!empty($this->tags)) {
            ACMS_Filter::entryTag($SQL, $this->tags);
        }
        if (!empty($this->keyword)) {
            ACMS_Filter::entryKeyword($SQL, $this->keyword);
        }
        if (!empty($this->Field)) {
            if (config('category_entry_list_field_search') == 'entry') {
                $sortFields = ACMS_Filter::entryField($SQL, $this->Field);
                foreach ($sortFields as $name) {
                    $SQL->addSelect($name);
                }
            } else {
                ACMS_Filter::categoryField($SQL, $this->Field);
            }
        }
    }

    protected function buildQuery($cid, &$Tpl)
    {
        $list = ['entry_id', 'entry_code', 'entry_status', 'entry_approval', 'entry_form_status', 'entry_sort', 'entry_user_sort', 'entry_category_sort', 'entry_title',
            'entry_link', 'entry_datetime', 'entry_start_datetime', 'entry_end_datetime', 'entry_posted_datetime', 'entry_updated_datetime', 'entry_summary_range', 'entry_indexing',
            'entry_members_only', 'entry_primary_image', 'entry_current_rev_id', 'entry_last_update_user_id', 'entry_category_id', 'entry_user_id', 'entry_form_id', 'entry_blog_id',
            'category_id', 'category_code', 'category_status', 'category_parent', 'category_sort', 'category_name', 'category_scope', 'category_indexing', 'category_blog_id'
        ];

        $subCategory = isset($this->_config['subCategory']) && $this->_config['subCategory'] === 'on';

        $sql = SQL::newSelect('entry', 'entry');
        foreach ($list as $name) {
            $sql->addSelect($name);
        }
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id', 'category', 'entry');
        $this->filterQuery($sql);
        ACMS_Filter::categoryStatus($sql);
        $where = SQL::newWhere();
        $where->addWhereOpr('entry_category_id', $cid, '=', 'AND', 'entry');
        if ($subCategory) {
            $subCategorySql = SQL::newSelect('entry_sub_category', 'sub_category');
            $subCategorySql->setSelect(SQL::newField(1, null, false));
            $subCategorySql->addWhereOpr('entry_sub_category_eid', SQL::newField('entry.entry_id'), '=', 'AND', 'sub_category');
            $subCategorySql->addWhereOpr('entry_sub_category_id', $cid, '=', 'AND', 'sub_category');
            $existsSql = SQL::newOprExists($subCategorySql);
            $where->addWhere($existsSql, 'OR');
        }
        $sql->addWhere($where);
        $sortFd = ACMS_Filter::entryOrder($sql, $this->_config['order'], $this->uid, $cid);
        if (!empty($sortFd)) {
            $sql->setGroup($sortFd);
        }
        $sql->addGroup('entry_id');

        $sql->setLimit($this->_config['limit']);
        $eQ = $sql->get(dsn());

        return $eQ;
    }

    protected function buildUnit($eRow, &$Tpl, $cid, $level, $count = 0)
    {
        $eid = intval($eRow['entry_id']);
        $cid = intval($eRow['entry_category_id']);
        if (!empty($eRow['entry_link'])) {
            $entryUrl   = $eRow['entry_link'];
        } else {
            $entryUrl   = acmsLink([
                'bid'   => $this->bid,
                'cid'   => $cid,
                'eid'   => $eid,
            ]);
        }
        $vars = [];
        $vars += [
            'entryUrl'      => $entryUrl,
            'entryTitle'    => addPrefixEntryTitle(
                $eRow['entry_title'],
                $eRow['entry_status'],
                $eRow['entry_start_datetime'],
                $eRow['entry_end_datetime'],
                $eRow['entry_approval']
            ),
            'entryLevel'    => $level,
            'entryCode'     => $eRow['entry_code'],
            'entryId'       => $eid,
            'entry:loop.class'  => $this->_config['entryLoopClass'],
        ];
        if (isset($eRow['entry_members_only']) && $eRow['entry_members_only'] === 'on') {
            $Tpl->add(['membersOnly', 'entry:loop']);
        }
        if (!empty($cid)) {
            $categoryName = $eRow['category_name'];
            $categoryCode = $eRow['category_code'];
            $categoryUrl = acmsLink([
                'bid'   => $eRow['category_blog_id'],
                'cid'   => $cid,
            ]);
            $vars += [
                'categoryName' => $categoryName,
                'categoryCode' => $categoryCode,
                'categoryUrl' => $categoryUrl,
                'cid' => $cid,
            ];
        }
        $vars   += $this->buildField(loadEntryField($eid), $Tpl);
        $Tpl->add('entry:loop', $vars);
    }

    protected function preBuildUnit()
    {
    }

    protected function eagerLoadCategoryFields()
    {
        $SQL = SQL::newSelect('category');
        $SQL->setSelect('category_id');
        if ($this->cid) {
            ACMS_Filter::categoryTree($SQL, $this->cid, 'descendant');
        }
        ACMS_Filter::categoryStatus($SQL);

        $Where  = SQL::newWhere();
        $Where->addWhereOpr('category_blog_id', $this->bid, '=', 'OR');
        $Where->addWhereOpr('category_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);

        $categoryIds = DB::query($SQL->get(dsn()), 'list');

        return eagerLoadField($categoryIds, 'cid');
    }

    /**
     * ルート変数を取得
     *
     * @return array
     */
    protected function getRootVars(): array
    {
        return [
            'parent.loop.class' => $this->_config['categoryParentLoopClass'] ?? '',
        ];
    }
}
