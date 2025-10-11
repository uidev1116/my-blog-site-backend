<?php

namespace Acms\Modules\Get\V2\Entry;

use Acms\Modules\Get\Helpers\Entry\GeoListHelper;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;
use SQL_Select;

class GeoList extends Summary
{
    /**
     * @inheritDoc
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'self',
        'cid' => 'self',
    ];

    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'eid' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\GeoListHelper
     */
    protected $geoListHelper;

    /**
     * @var bool
     */
    protected $hasLocation = false;

    /**
     * @inheritDoc
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            'referencePoint' => $config->get('entry_geo-list_reference_point'),
            'within' => floatval($config->get('entry_geo-list_within')),
            // 基本
            'order' => null,
            'limit' => $this->limit ?? (int) $config->get('entry_geo-list_limit'),
            'offset' => (int) $config->get('entry_geo-list_offset'),
            'unit' => (int) $config->get('entry_geo-list_unit'),
            'newItemPeriod' => (int) $config->get('entry_geo-list_newtime'),
            'displayIndexingOnly' => $config->get('entry_geo-list_indexing') === 'on',
            'displayMembersOnly' => $config->get('entry_geo-list_members_only') === 'on',
            'displaySubcategoryEntries' => false,
            'displaySecretEntry' => $config->get('entry_geo-list_secret') === 'on',
            'detailDateOn' => $config->get('entry_geo-list_date') === 'on',
            'notfoundBlock' => $config->get('mo_entry_geo-list_notfound') === 'on',
            'notfoundStatus404' => $config->get('entry_geo-list_notfound_status_404') === 'on',
            'fulltextEnabled' => $config->get('entry_geo-list_fulltext') === 'on',
            'fulltextWidth' => (int) $config->get('entry_geo-list_fulltext_width'),
            'fulltextMarker' => $config->get('entry_geo-list_fulltext_marker'),
            'includeTags' => $config->get('entry_geo-list_tag') === 'on',
            'hiddenCurrentEntry' => $config->get('entry_geo-list_hidden_current_entry') === 'on',
            'hiddenPrivateEntry' => $config->get('entry_summary_hidden_private_entry') === 'on',
            'includeRelatedEntries' => $config->get('entry_geo-list_hidden_private_entry') === 'on',
            // 画像系
            'includeMainImage' => $config->get('entry_geo-list_image_on') === 'on',
            'mainImageTarget' => $config->get('entry_geo-list_main_image_target', 'field'),
            'mainImageFieldName' => $config->get('entry_geo-list_main_image_field_name'),
            'displayNoImageEntry' => $config->get('entry_geo-list_noimage') === 'on',
            'imageX' => (int) $config->get('entry_geo-list_image_x', 200),
            'imageY' => (int) $config->get('entry_geo-list_image_y', 200),
            'imageTrim' => $config->get('entry_geo-list_image_trim') === 'on',
            'imageZoom' => $config->get('entry_geo-list_image_zoom') === 'on',
            'imageCenter' => $config->get('entry_geo-list_image_center') === 'on',
            // ページネーション
            'simplePagerEnabled' => $config->get('entry_geo-list_simple_pager_on') === 'on',
            'paginationEnabled' => $config->get('entry_geo-list_pager_on') === 'on',
            'paginationDelta' => (int) $config->get('entry_geo-list_pager_delta', 4),
            'paginationCurrentAttr' => $config->get('entry_geo-list_pager_cur_attr'),
            // フィールド・情報
            'includeEntryFields' => $config->get('entry_geo-list_entry_field') === 'on',
            'includeCategory' => $config->get('entry_geo-list_category_on') === 'on',
            'includeCategoryFields' => $config->get('entry_geo-list_category_field_on') === 'on',
            'includeUser' => $config->get('entry_geo-list_user_on') === 'on',
            'includeUserFields' => $config->get('entry_geo-list_user_field_on') === 'on',
            'includeBlog' => $config->get('entry_geo-list_blog_on') === 'on',
            'includeBlogFields' => $config->get('entry_geo-list_blog_field_on') === 'on',
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
        $this->geoListHelper = new GeoListHelper($this->getBaseParams([
            'config' => $this->config,
            'get' => $this->Get,
        ]));

        $this->geoListHelper->setReferencePoint();
        $this->hasLocation = $this->geoListHelper->getLat() && $this->geoListHelper->getLng();
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
    protected function getEntries(SQL_Select $q): array
    {
        if (!$this->hasLocation) {
            return [];
        }
        return parent::getEntries($q);
    }

    /**
     * @inheritDoc
     */
    protected function buildPagination(): ?array
    {
        if (!$this->hasLocation) {
            return null;
        }
        return $this->entryHelper->buildPagination($this->geoListHelper->getCountQuery());
    }

    /**
     * @inheritDoc
     */
    protected function preBuild(array $vars, array $entries): array
    {
        $vars['hasLocation'] = $this->hasLocation;
        return $vars;
    }
}
