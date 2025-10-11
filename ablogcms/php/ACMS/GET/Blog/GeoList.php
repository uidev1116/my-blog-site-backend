<?php

use Acms\Modules\Get\Helpers\Blog\GeoListHelper;

class ACMS_GET_Blog_GeoList extends ACMS_GET_Blog_ChildList
{
    use \Acms\Traits\Modules\ConfigTrait;

    public $_scope = [
        'bid' => 'global',
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
     * @var \Acms\Modules\Get\Helpers\Blog\GeoListHelper
     */
    protected $geoListHelper;

    /**
     * @return array{
     *  referencePoint: string,
     *  within: float,
     *  order: string,
     *  limit: int,
     *  parent_loop_class: string,
     *  loop_class: string,
     *  geoLocation: bool,
     * }
     */
    protected function initConfig(): array
    {
        return [
            'referencePoint' => config('blog_geo-list_reference_point'),
            'within'  => floatval(config('blog_geo-list_within')),
            'order' => config('blog_geo-list_order'),
            'limit' => intval(config('blog_geo-list_limit')),
            'parent_loop_class' => config('blog_geo-list_parent_loop_class'),
            'loop_class' => config('blog_geo-list_loop_class'),
            'geoLocation' => false,
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
}
