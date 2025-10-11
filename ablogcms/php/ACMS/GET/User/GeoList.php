<?php

use Acms\Modules\Get\Helpers\User\GeoListHelper;

class ACMS_GET_User_GeoList extends ACMS_GET_User_Search
{
    public $_axis = [
        'bid' => 'self',
        'cid' => 'self',
    ];

    public $_scope = [
        'uid' => 'global',
    ];

    /**
     * 緯度
     *
     * @var float|null
     */
    protected $lat;

    /**
     * 経度
     *
     * @var float|null
     */
    protected $lng;

    /**
     * @var \Acms\Modules\Get\Helpers\User\GeoListHelper
     */
    protected $geoListHelper;

    /**
     * @inheritDoc
     */
    protected function initConfig(): array
    {
        return [
            'referencePoint' => config('user_geo-list_reference_point'),
            'within'  => floatval(config('user_geo-list_within')),
            'indexing' => config('user_geo-list_indexing'),
            'auth' => configArray('user_geo-list_auth'),
            'status' => configArray('user_geo-list_status'),
            'mail_magazine' => configArray('user_geo-list_mail_magazine'),
            'limit' => intval(config('user_geo-list_limit')),
            'parent_loop_class' => config('user_geo-list_parent_loop_class'),
            'loop_class' => config('user_geo-list_loop_class'),
            'pager_delta' => config('user_geo-list_pager_delta'),
            'pager_cur_attr' => config('user_geo-list_pager_cur_attr'),
            'entry_list_enable' => config('user_geo-list_entry_list_enable'),
            'entry_list_order' => config('user_geo-list_entry_list_order'),
            'entry_list_limit' => config('user_geo-list_entry_list_limit'),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function boot(): void
    {
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
        return $this->geoListHelper->buildGeoListQuery();
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

    /**
     * @inheritDoc
     */
    protected function buildPagination(Template $tpl): array
    {
        return [];
    }
}
