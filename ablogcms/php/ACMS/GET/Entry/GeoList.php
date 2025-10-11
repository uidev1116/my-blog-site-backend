<?php

use Acms\Modules\Get\Helpers\Entry\GeoListHelper;

class ACMS_GET_Entry_GeoList extends ACMS_GET_Entry_Summary
{
    public $_axis = [
        'bid' => 'self',
        'cid' => 'self',
    ];

    public $_scope = [
        'eid' => 'global',
    ];

    /**
     * @var float|null
     */
    protected $lat;

    /**
     * @var float|null
     */
    protected $lng;

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\GeoListHelper
     */
    protected $geoListHelper;

    /**
     * @inheritDoc
     */
    public function initConfig(): array
    {
        return [
            'referencePoint' => config('entry_geo-list_reference_point'),
            'within' => floatval(config('entry_geo-list_within')),
            // 基本
            'order' => null,
            'limit' => (int) config('entry_geo-list_limit'),
            'offset' => (int) config('entry_geo-list_offset'),
            'unit' => (int) config('entry_geo-list_unit'),
            'parentLoopClass' => config('entry_geo-list_parent_loop_class'),
            'loopClass' => config('entry_geo-list_loop_class'),
            'newItemPeriod' => (int) config('entry_geo-list_newtime'),
            'displayIndexingOnly' => config('entry_geo-list_indexing') === 'on',
            'displayMembersOnly' => config('entry_geo-list_members_only') === 'on',
            'displaySubcategoryEntries' => false,
            'displaySecretEntry' => config('entry_geo-list_secret') === 'on',
            'dateOn' => true,
            'detailDateOn' => config('entry_geo-list_date') === 'on',
            'notfoundBlock' => config('mo_entry_geo-list_notfound') === 'on',
            'notfoundStatus404' => config('entry_geo-list_notfound_status_404') === 'on',
            'fulltextEnabled' => config('entry_geo-list_fulltext') === 'on',
            'fulltextWidth' => (int) config('entry_geo-list_fulltext_width'),
            'fulltextMarker' => config('entry_geo-list_fulltext_marker'),
            'includeTags' => config('entry_geo-list_tag') === 'on',
            'hiddenCurrentEntry' => config('entry_geo-list_hidden_current_entry') === 'on',
            'hiddenPrivateEntry' => config('entry_summary_hidden_private_entry') === 'on',
            'includeRelatedEntries' => config('entry_geo-list_hidden_private_entry') === 'on',
            // 画像系
            'includeMainImage' => config('entry_geo-list_image_on') === 'on',
            'mainImageTarget' => config('entry_geo-list_main_image_target', 'unit'),
            'mainImageFieldName' => config('entry_geo-list_main_image_field_name'),
            'displayNoImageEntry' => config('entry_geo-list_noimage') === 'on',
            'imageX' => (int) config('entry_geo-list_image_x', 200),
            'imageY' => (int) config('entry_geo-list_image_y', 200),
            'imageTrim' => config('entry_geo-list_image_trim') === 'on',
            'imageZoom' => config('entry_geo-list_image_zoom') === 'on',
            'imageCenter' => config('entry_geo-list_image_center') === 'on',
            // ページネーション
            'simplePagerEnabled' => config('entry_geo-list_simple_pager_on') === 'on',
            'paginationEnabled' => config('entry_geo-list_pager_on') === 'on',
            'paginationDelta' => (int) config('entry_geo-list_pager_delta', 4),
            'paginationCurrentAttr' => config('entry_geo-list_pager_cur_attr'),
            // フィールド・情報
            'includeEntryFields' => config('entry_geo-list_entry_field') === 'on',
            'includeCategory' => config('entry_geo-list_category_on') === 'on',
            'includeCategoryFields' => config('entry_geo-list_category_field_on') === 'on',
            'includeUser' => config('entry_geo-list_user_on') === 'on',
            'includeUserFields' => config('entry_geo-list_user_field_on') === 'on',
            'includeBlog' => config('entry_geo-list_blog_on') === 'on',
            'includeBlogFields' => config('entry_geo-list_blog_field_on') === 'on',
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
        parent::boot();
        $this->geoListHelper = new GeoListHelper($this->getBaseParams([
            'config' => $this->config,
            'get' => $this->Get,
        ]));
        $this->geoListHelper->setReferencePoint();
        $this->lat = $this->geoListHelper->getLat();
        $this->lng = $this->geoListHelper->getLng();
    }

    /**
     * @inheritDoc
     */
    protected function buildQuery(): SQL_Select
    {
        return $this->geoListHelper->buildQuery();
    }

    /**
     * @inheritDoc
     */
    protected function buildCountQuery(): SQL_Select
    {
        return $this->geoListHelper->getCountQuery();
    }

    /**
     * @inheritDoc
     */
    protected function preBuild(Template $tpl): array
    {
        if ($this->config['referencePoint'] === 'url_query_string' && (!$this->lat || !$this->lng)) {
            $tpl->add('notFoundGeolocation');
            return [false, true];
        }
        if (!$this->lat || !$this->lng) {
            return [false, false];
        }
        return [true, true];
    }
}
