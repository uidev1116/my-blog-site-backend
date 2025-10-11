<?php

use Acms\Services\Logger\Deprecated;

class ACMS_GET_Entry_Photo extends ACMS_GET_Entry_Summary
{
    /**
     * @inheritdoc
     */
    public function initConfig(): array
    {
        return [
            'order' => [
                $this->order ? $this->order : config('entry_photo_order'),
                config('entry_photo_order2'),
            ],
            'orderFieldName' => config('entry_photo_order_field_name'),
            'noNarrowDownSort' => config('entry_photo_no_narrow_down_sort') === 'on',
            'limit' => (int) config('entry_photo_limit'),
            'offset' => (int) config('entry_photo_offset'),
            'unit' => (int) config('entry_photo_unit'),
            'loopClass' => config('entry_photo_loop_class'),
            'newItemPeriod' => (int) config('entry_photo_newtime'),
            'displayIndexingOnly' => config('entry_photo_indexing') === 'on',
            'displayMembersOnly' => config('entry_photo_members_only') === 'on',
            'displaySubcategoryEntries' => config('entry_photo_sub_category') === 'on',
            'displaySecretEntry' => config('entry_photo_secret') === 'on',
            'dateOn' => true,
            'detailDateOn' => true,
            'notfoundBlock' => config('mo_entry_photo_notfound') === 'on',
            'notfoundStatus404' => config('entry_photo_notfound_status_404') === 'on',
            'fulltextEnabled' => false,
            'fulltextWidth' => 0,
            'fulltextMarker' => '',
            'includeTags' => false,
            'hiddenCurrentEntry' => config('entry_photo_hidden_current_entry') === 'on',
            'hiddenPrivateEntry' => config('entry_photo_hidden_private_entry') === 'on',
            'includeRelatedEntries' => false,
            // 画像系
            'includeMainImage' => true,
            'mainImageTarget' => config('entry_photo_main_image_target', 'unit'),
            'mainImageFieldName' => config('entry_photo_main_image_field_name'),
            'displayNoImageEntry' => config('entry_photo_noimage') === 'on',
            'imageX' => (int) config('entry_photo_image_x', 200),
            'imageY' => (int) config('entry_photo_image_y', 200),
            'imageTrim' => config('entry_photo_image_trim') === 'on',
            'imageZoom' => config('entry_photo_image_zoom') === 'on',
            'imageCenter' => config('entry_photo_image_center') === 'on',
            // ページネーション
            'simplePagerEnabled' => false,
            'paginationEnabled' => true,
            'paginationDelta' => (int) config('entry_photo_pager_delta', 3),
            'paginationCurrentAttr' => config('entry_photo_pager_cur_attr'),
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
        Deprecated::once('Entry_Photo モジュール', [
            'since' => '3.2.0',
            'alternative' => ' Entry_Summary モジュール',
        ]);
        return parent::get();
    }
}
