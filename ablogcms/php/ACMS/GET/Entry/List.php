<?php

use Acms\Services\Logger\Deprecated;

class ACMS_GET_Entry_List extends ACMS_GET_Entry_Summary
{
    /**
     * @inheritdoc
     */
    public function initConfig(): array
    {
        return [
            'order' => [
                $this->order ? $this->order : config('entry_list_order'),
                config('entry_list_order2'),
            ],
            'orderFieldName' => config('entry_list_order_field_name'),
            'noNarrowDownSort' => config('entry_list_no_narrow_down_sort') === 'on',
            'limit' => (int) config('entry_list_limit'),
            'offset' => (int) config('entry_list_offset'),
            'unit' => (int) config('entry_list_unit'),
            'loopClass' => config('entry_list_loop_class'),
            'newItemPeriod' => (int) config('entry_list_newtime'),
            'displayIndexingOnly' => config('entry_list_indexing') === 'on',
            'displayMembersOnly' => config('entry_list_members_only') === 'on',
            'displaySubcategoryEntries' => config('entry_list_sub_category') === 'on',
            'displaySecretEntry' => config('entry_list_secret') === 'on',
            'dateOn' => true,
            'detailDateOn' => true,
            'notfoundBlock' => config('mo_entry_list_notfound') === 'on',
            'notfoundStatus404' => config('entry_list_notfound_status_404') === 'on',
            'fulltextEnabled' => false,
            'fulltextWidth' => 0,
            'fulltextMarker' => '',
            'includeTags' => false,
            'hiddenCurrentEntry' => config('entry_list_hidden_current_entry') === 'on',
            'hiddenPrivateEntry' => config('entry_list_hidden_private_entry') === 'on',
            'includeRelatedEntries' => false,
            // 画像系
            'includeMainImage' => false,
            'mainImageTarget' => 'unit',
            'mainImageFieldName' => '',
            'displayNoImageEntry' => config('entry_list_noimage') === 'on',
            'imageX' => 200,
            'imageY' => 200,
            'imageTrim' => false,
            'imageZoom' => false,
            'imageCenter' => false,
            // ページネーション
            'simplePagerEnabled' => false,
            'paginationEnabled' => false,
            'paginationDelta' => (int) config('entry_list_pager_delta', 3),
            'paginationCurrentAttr' => config('entry_list_pager_cur_attr'),
            // フィールド・情報
            'includeEntryFields' => true,
            'includeCategory' => false,
            'includeCategoryFields' => false,
            'includeUser' =>  false,
            'includeUserFields' =>  false,
            'includeBlog' =>  false,
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
        Deprecated::once('Entry_List モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Entry_Summary モジュール',
        ]);
        return parent::get();
    }
}
