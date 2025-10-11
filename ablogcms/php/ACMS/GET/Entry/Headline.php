<?php

use Acms\Services\Logger\Deprecated;

class ACMS_GET_Entry_Headline extends ACMS_GET_Entry_Summary
{
    public $_scope = [
        'uid'       => 'global',
        'cid'       => 'global',
        'eid'       => 'global',
        'keyword'   => 'global',
        'tag'       => 'global',
        'field'     => 'global',
        'start'     => 'global',
        'end'       => 'global',
        'page'      => 'global',
    ];

    /**
     * @inheritdoc
     */
    public function initConfig(): array
    {
        return [
            'order'            => [
                $this->order ? $this->order : config('entry_headline_order'),
                config('entry_headline_order2'),
            ],
            'orderFieldName' => config('entry_headline_order_field_name'),
            'noNarrowDownSort' => config('entry_headline_no_narrow_down_sort') === 'on',
            'limit' => (int) config('entry_headline_limit'),
            'offset' => (int) config('entry_headline_offset'),
            'unit' => (int) config('entry_headline_unit'),
            'loopClass' => config('entry_headline_loop_class'),
            'newItemPeriod' => (int) config('entry_headline_newtime'),
            'displayIndexingOnly' => config('entry_headline_indexing') === 'on',
            'displayMembersOnly' => config('entry_headline_members_only') === 'on',
            'displaySubcategoryEntries' => config('entry_headline_sub_category') === 'on',
            'displaySecretEntry' => config('entry_headline_secret') === 'on',
            'dateOn' => true,
            'detailDateOn' => true,
            'notfoundBlock' => config('mo_entry_headline_notfound') === 'on',
            'notfoundStatus404' => config('entry_headline_notfound_status_404') === 'on',
            'fulltextEnabled' => false,
            'fulltextWidth' => 0,
            'fulltextMarker' => '',
            'includeTags' => false,
            'hiddenCurrentEntry' => config('entry_headline_hidden_current_entry') === 'on',
            'hiddenPrivateEntry' => config('entry_headline_hidden_private_entry') === 'on',
            'includeRelatedEntries' => false,
            // 画像系
            'includeMainImage' => false,
            'mainImageTarget' => 'unit',
            'mainImageFieldName' => '',
            'displayNoImageEntry' => config('entry_headline_noimage') === 'on',
            'imageX' => 200,
            'imageY' => 200,
            'imageTrim' => false,
            'imageZoom' => false,
            'imageCenter' => false,
            // ページネーション
            'simplePagerEnabled' => config('entry_headline_simple_pager_on') === 'on',
            'paginationEnabled' => config('entry_headline_pager_on') === 'on',
            'paginationDelta' => (int) config('entry_headline_pager_delta', 4),
            'paginationCurrentAttr' => config('entry_headline_pager_cur_attr'),
            // フィールド・情報
            'includeEntryFields' => true,
            'includeCategory' => true,
            'includeCategoryFields' => false,
            'includeUser' =>  false,
            'includeUserFields' =>  false,
            'includeBlog' =>  true,
            'includeBlogFields' => false,
            // 表示モード
            'relatedEntryMode' => false,
            'relatedEntryType' => '',
        ];
    }

    /**
     * @inheritdoc
     */
    public function get()
    {
        Deprecated::once('Entry_Headline モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Entry_Summary モジュール',
        ]);
        return parent::get();
    }
}
