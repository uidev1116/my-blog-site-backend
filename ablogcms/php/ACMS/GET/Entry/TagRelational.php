<?php

use Acms\Modules\Get\Helpers\Entry\TagRelationalHelper;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;

class ACMS_GET_Entry_TagRelational extends ACMS_GET_Entry_Summary
{
    public $_scope = [
        'eid' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\TagRelationalHelper
     */
    protected $tagRelationalHelper;

    /**
     * コンフィグの取得
     *
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'order' => $this->order ? $this->order : config('entry_tag-relational_order'),
            'orderFieldName' => '',
            'noNarrowDownSort' => false,
            'limit' => (int) config('entry_tag-relational_limit'),
            'offset' => 0,
            'unit' => 1,
            'parentLoopClass' => config('entry_tag-relational_parent_loop_class'),
            'loopClass' => config('entry_tag-relational_loop_class'),
            'newItemPeriod' => 0,
            'displayIndexingOnly' => config('entry_tag-relational_indexing') === 'on',
            'displayMembersOnly' => config('entry_tag-relational_members_only') === 'on',
            'displaySubcategoryEntries' => false,
            'displaySecretEntry' => config('entry_tag-relational_secret') === 'on',
            'dateOn' => true,
            'detailDateOn' => false,
            'notfoundStatus404' => config('entry_tag-relational_notfound_status_404') === 'on',
            'fulltextEnabled' => true,
            'fulltextWidth' => (int) config('entry_tag-relational_fulltext_width'),
            'fulltextMarker' => config('entry_tag-relational_fulltext_marker'),
            'includeTags' => false,
            'hiddenCurrentEntry' => false,
            'hiddenPrivateEntry' => false,
            'includeRelatedEntries' => false,
            // 画像系
            'includeMainImage' => true,
            'mainImageTarget' => config('entry_tag-relational_main_image_target', 'unit'),
            'mainImageFieldName' => config('entry_tag-relational_main_image_field_name'),
            'displayNoImageEntry' => config('entry_tag-relational_noimage') === 'on',
            'imageX' => (int) config('entry_tag-relational_image_x', 200),
            'imageY' => (int) config('entry_tag-relational_image_y', 200),
            'imageTrim' => config('entry_tag-relational_image_trim') === 'on',
            'imageZoom' => config('entry_tag-relational_image_zoom') === 'on',
            'imageCenter' => config('entry_tag-relational_image_center') === 'on',
            // ページネーション
            'simplePagerEnabled' => false,
            'paginationEnabled' => false,
            'paginationDelta' => 0,
            'paginationCurrentAttr' => '',
            // フィールド・情報
            'includeEntryFields' => config('entry_tag-relational_entry_field') === 'on',
            'includeCategory' => config('entry_tag-relational_category_on') === 'on',
            'includeCategoryFields' => config('entry_tag-relational_category_field_on') === 'on',
            'includeUser' => config('entry_tag-relational_user_on') === 'on',
            'includeUserFields' => config('entry_tag-relational_user_field_on') === 'on',
            'includeBlog' => config('entry_tag-relational_blog_on') === 'on',
            'includeBlogFields' => config('entry_tag-relational_blog_field_on') === 'on',
            // 表示モード
            'relatedEntryMode' => false,
            'relatedEntryType' => '',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function boot(): void
    {
        $this->entryHelper = new EntryHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
        $this->tagRelationalHelper = new TagRelationalHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * @inheritDoc
     */
    protected function buildQuery(): SQL_Select
    {
        return $this->tagRelationalHelper->buildQuery();
    }

    /**
     * 件数取得用のクエリを組み立て
     *
     * @return SQL_Select
     */
    protected function buildCountQuery(): SQL_Select
    {
        return $this->tagRelationalHelper->getCountQuery();
    }
}
