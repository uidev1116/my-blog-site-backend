<?php

class ACMS_GET_Category_EntrySummary extends ACMS_GET_Category_EntryList
{
    public $_axis = [
        'bid'   => 'self',
        'cid'   => 'self',
    ];

    public $_endGluePoint = null;

    protected function initVars()
    {
        $config = [
            'categoryOrder'             => config('category_entry_summary_category_order'),
            'categoryEntryListLevel'    => config('category_entry_summary_level'),
            'categoryIndexing'          => config('category_entry_summary_category_indexing'),
            'entryAmountZero'           => config('category_entry_summary_entry_amount_zero'),
            'subCategory'               => config('category_entry_summary_sub_category'),
            'order'                     => config('category_entry_summary_order'),
            'limit'                     => intval(config('category_entry_summary_limit')),
            'offset'                    => intval(config('category_entry_summary_offset')),
            'indexing'                  => config('category_entry_summary_indexing'),
            'secret'                    => config('category_entry_summary_secret'),
            'notfound'                  => config('mo_category_entry_summary_notfound'),
            'noimage'                   => config('category_entry_summary_noimage'),
            'unit'                      => config('category_entry_summary_unit'),
            'newtime'                   => config('category_entry_summary_newtime'),
            'imageX'                    => intval(config('category_entry_summary_image_x')),
            'imageY'                    => intval(config('category_entry_summary_image_y')),
            'imageTrim'                 => config('category_entry_summary_image_trim'),
            'imageZoom'                 => config('category_entry_summary_image_zoom'),
            'imageCenter'               => config('category_entry_summary_image_center'),
            'mainImageOn'               => config('category_entry_summary_image_on'),
            'mainImageTarget'           => config('category_entry_summary_main_image_target', 'unit'),
            'mainImageFieldName'        => config('category_entry_summary_main_image_field_name'),
            'categoryParentLoopClass'   => config('category_entry_summary_category_parent_loop_class'),
            'categoryLoopClass'         => config('category_entry_summary_category_loop_class'),
            'fulltextWidth'             => config('category_entry_summary_fulltext_width'),
            'fulltextMarker'            => config('category_entry_summary_fulltext_marker'),
            'loop_class'                => config('category_entry_summary_entry_loop_class'),

            'entryFieldOn'              => config('category_entry_summary_entry_field_on'),
            'categoryInfoOn'            => 'on',
            'categoryFieldOn'           => config('category_entry_summary_category_field_on'),
            'userInfoOn'                => 'on',
            'userFieldOn'               => config('category_entry_summary_user_field_on'),
            'blogInfoOn'                => 'on',
            'blogFieldOn'               => config('category_entry_summary_blog_field_on'),
        ];
        if (!empty($this->order)) {
            $config['order'] = $this->order;
        }

        return $config;
    }

    protected function buildQuery($cid, &$Tpl)
    {
        $list = ['entry_id', 'entry_code', 'entry_status', 'entry_approval', 'entry_form_status', 'entry_sort', 'entry_user_sort', 'entry_category_sort', 'entry_title',
            'entry_link', 'entry_datetime', 'entry_start_datetime', 'entry_end_datetime', 'entry_posted_datetime', 'entry_updated_datetime', 'entry_summary_range', 'entry_indexing',
            'entry_members_only', 'entry_primary_image', 'entry_current_rev_id', 'entry_last_update_user_id', 'entry_category_id', 'entry_user_id', 'entry_form_id', 'entry_blog_id', 'blog_id', 'blog_code',
            'blog_status', 'blog_parent', 'blog_name', 'blog_domain', 'blog_indexing', 'blog_alias_status', 'blog_alias_sort', 'blog_alias_primary', 'category_id', 'category_code',
            'category_status', 'category_parent', 'category_sort', 'category_name', 'category_scope', 'category_indexing', 'category_blog_id'
        ];

        $subCategory = isset($this->_config['subCategory']) && $this->_config['subCategory'] === 'on';


        $sql = SQL::newSelect('entry', 'entry');
        foreach ($list as $name) {
            $sql->addSelect($name);
        }
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id', 'blog', 'entry');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id', 'category', 'entry');
        $this->filterQuery($sql);
        ACMS_Filter::categoryStatus($sql);
        $where = SQL::newWhere();
        $where->addWhereOpr('entry_category_id', $cid, '=', 'AND');
        if ($subCategory) {
            $subCategorySql = SQL::newSelect('entry_sub_category', 'sub_category');
            $subCategorySql->setSelect(SQL::newField(1, null, false));
            $subCategorySql->addWhereOpr('entry_sub_category_eid', SQL::newField('entry.entry_id'), '=', 'AND', 'sub_category');
            $subCategorySql->addWhereOpr('entry_sub_category_id', $cid, '=', 'AND', 'sub_category');
            $existsSql = SQL::newOprExists($subCategorySql);
            $where->addWhere($existsSql, 'OR');
        }
        $sql->addWhere($where);

        $limit  = $this->_config['limit'];
        $offset = intval($this->_config['offset']);
        if (1 > $limit) {
            return '';
        }

        $sortFd = ACMS_Filter::entryOrder($sql, $this->_config['order'], $this->uid, $cid);
        $sql->setLimit($limit, $offset);

        if (!empty($sortFd)) {
            $sql->setGroup($sortFd);
        }
        $sql->addGroup('entry_id');

        $q = $sql->get(dsn());

        $all = DB::query($q, 'all');
        if (empty($all)) {
            return false;
        }
        $this->_endGluePoint = count($all);

        return $q;
    }

    protected function preBuildUnit()
    {
        $entryIds = [];
        $blogIds = [];
        $userIds = [];
        $categoryIds = [];

        foreach ($this->entries as $entry) {
            if (!empty($entry['entry_id'])) {
                $entryIds[] = $entry['entry_id'];
            }
            if (!empty($entry['entry_blog_id'])) {
                $blogIds[] = $entry['entry_blog_id'];
            }
            if (!empty($entry['entry_user_id'])) {
                $userIds[] = $entry['entry_user_id'];
            }
            if (!empty($entry['entry_category_id'])) {
                $categoryIds[] = $entry['entry_category_id'];
            }
        }

        // メイン画像のEagerLoading
        if (!isset($this->_config['mainImageOn']) || $this->_config['mainImageOn'] === 'on') {
            $target = $this->_config['mainImageTarget'] ?? 'unit';
            $fieldName = $this->_config['mainImageFieldName'] ?? '';
            $this->eagerLoadingData['mainImage'] = Tpl::eagerLoadMainImage($this->entries, $target, $fieldName);
        }
        // フルテキストのEagerLoading
        if (!isset($this->_config['fullTextOn']) || $this->_config['fullTextOn'] === 'on') {
            $this->eagerLoadingData['fullText'] = Tpl::eagerLoadFullText($entryIds);
        }
        // タグのEagerLoading
        $this->eagerLoadingData['tag'] =  Tpl::eagerLoadTag($entryIds);
        // エントリーフィールドのEagerLoading
        if (!isset($this->_config['entryFieldOn']) || $this->_config['entryFieldOn'] === 'on') {
            $this->eagerLoadingData['entryField'] = eagerLoadField($entryIds, 'eid');
        }
        // ユーザーフィールドのEagerLoading
        if (isset($this->_config['userInfoOn']) && $this->_config['userInfoOn'] === 'on') {
            $this->eagerLoadingData['userField'] = eagerLoadField($userIds, 'uid');
        }
        // ブログフィールドのEagerLoading
        if (isset($this->_config['blogInfoOn']) && $this->_config['blogInfoOn'] === 'on') {
            $this->eagerLoadingData['blogField'] = eagerLoadField($blogIds, 'bid');
        }
        // カテゴリーフィールドのEagerLoading
        if (isset($this->_config['categoryInfoOn']) && $this->_config['categoryInfoOn'] === 'on') {
            $this->eagerLoadingData['categoryField'] = eagerLoadField($categoryIds, 'cid');
        }
        // サブカテゴリーのEagerLoading
        if (isset($this->_config['categoryInfoOn']) && $this->_config['categoryInfoOn'] === 'on') {
            $this->eagerLoadingData['subCategory'] = eagerLoadSubCategories($entryIds);
        }
    }

    protected function buildUnit($eRow, &$Tpl, $cid, $level, $count = 0)
    {
        $this->buildSummary($Tpl, $eRow, $count, $this->_endGluePoint, $this->_config, [], $this->eagerLoadingData);
    }

    /**
     * @inheritDoc
     */
    protected function filterQuery($SQL)
    {
        $BlogSub = null;
        if (!empty($this->bid)) {
            if ($this->blogAxis() === 'self') {
                $SQL->addWhereOpr('entry_blog_id', $this->bid);
                if ('on' === $this->_config['secret']) {
                    ACMS_Filter::blogDisclosureSecretStatus($SQL);
                } else {
                    ACMS_Filter::blogStatus($SQL);
                }
            } else {
                $BlogSub = SQL::newSelect('blog');
                $BlogSub->setSelect('blog_id');
                ACMS_Filter::blogTree($BlogSub, $this->bid, $this->blogAxis());
                if ('on' === $this->_config['secret']) {
                    ACMS_Filter::blogDisclosureSecretStatus($BlogSub);
                } else {
                    ACMS_Filter::blogStatus($BlogSub);
                }
            }
        }
        if ($uid = intval($this->uid)) {
            $SQL->addWhereOpr('entry_user_id', $uid);
        }
        if (!empty($this->eid)) {
            $SQL->addWhereOpr('entry_id', $this->eid);
        }
        ACMS_Filter::entrySpan($SQL, $this->start, $this->end);
        ACMS_Filter::entrySession($SQL);

        if ('on' === $this->_config['secret']) {
            ACMS_Filter::categoryDisclosureSecretStatus($SQL);
        } else {
            ACMS_Filter::categoryStatus($SQL);
        }

        if (!empty($this->tags)) {
            ACMS_Filter::entryTag($SQL, $this->tags);
        }
        if (!empty($this->keyword)) {
            ACMS_Filter::entryKeyword($SQL, $this->keyword);
        }
        if (!empty($this->Field)) {
            if (config('category_entry_summary_field_search') == 'entry') {
                $sortFields = ACMS_Filter::entryField($SQL, $this->Field);
                foreach ($sortFields as $name) {
                    $SQL->addSelect($name);
                }
            } else {
                ACMS_Filter::categoryField($SQL, $this->Field);
            }
        }
        if ('on' == $this->_config['indexing']) {
            $SQL->addWhereOpr('entry_indexing', 'on');
        }
        if ('on' !== $this->_config['noimage'] && $this->_config['mainImageTarget'] !== 'field') {
            $SQL->addWhereOpr('entry_primary_image', null, '<>');
        }

        //-------------------------
        // filter (blog, category)
        if ($BlogSub) {
            $SQL->addWhereIn('entry_blog_id', DB::subQuery($BlogSub));
        }
    }
}
