<?php

namespace Acms\Modules\Get\V2\Blog;

use Acms\Modules\Get\Helpers\Blog\GeoListHelper;
use SQL_Select;

class GeoList extends ChildList
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * スコープの設定
     *
     * @inheritDoc
     */
    protected $scopes = [
        'bid' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Blog\GeoListHelper
     */
    protected $geoListHelper;

    /**
     * @var bool
     */
    protected $hasLocation = false;

    /**
     * @return array{
     *  order: string,
     *  limit: int,
     *  geoLocation: bool,
     * }
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            'referencePoint' => $config->get('blog_geo-list_reference_point'),
            'within'  => floatval($config->get('blog_geo-list_within')),
            'order' => $config->get('blog_geo-list_order'),
            'limit' => intval($config->get('blog_geo-list_limit')),
            'geoLocation' => false,
        ];
    }

    /**
     * 起動処理
     *
     * @return void
     */
    protected function boot(): void
    {
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
        return $this->geoListHelper->buildGeoListQuery();
    }

    /**
     * @inheritDoc
     */
    protected function getBlogs(SQL_Select $sql): array
    {
        if (!$this->hasLocation) {
            return [];
        }
        return parent::getBlogs($sql);
    }

    /**
     * @inheritDoc
     */
    protected function preBuild(array $vars, array $blogs): array
    {
        $vars['hasLocation'] = $this->hasLocation;
        return $vars;
    }
}
