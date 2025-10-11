<?php

namespace Acms\Modules\Get\Helpers\Blog;

use Acms\Services\Facades\Database;
use SQL;
use SQL_Select;
use Field;

class GeoListHelper extends BlogHelper
{
    /**
     * @var float|null
     */
    protected $lat = null;

    /**
     * @var float|null
     */
    protected $lng = null;

    /**
     * @var Field
     */
    protected $get;

    /**
     * コンストラクタ
     *
     * @inheritDoc
     */
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->get = $params['get'] ?? new Field();
    }

    /**
     * 基準点となる位置情報（違度）を取得
     *
     * @return float|null
     */
    public function getLat(): ?float
    {
        return $this->lat;
    }

    /**
     * 基準点となる位置情報（経度）を取得
     *
     * @return float|null
     */
    public function getLng(): ?float
    {
        return $this->lng;
    }

    /**
     * 基準点となる位置情報を取得
     *
     * @return void
     */
    public function setReferencePoint(): void
    {
        if ($this->config['referencePoint'] === 'url_context' && $this->bid) {
            $sql = SQL::newSelect('geo', 'geo');
            $sql->addSelect('geo_geometry', 'lat', 'geo', 'ST_Y');
            $sql->addSelect('geo_geometry', 'lng', 'geo', 'ST_X');
            $sql->addWhereOpr('geo_bid', $this->bid);
            $q = $sql->get(dsn());
            if ($data = Database::query($q, 'row')) {
                $this->lat = $data['lat'];
                $this->lng = $data['lng'];
            }
        } elseif ($this->config['referencePoint'] === 'url_query_string') {
            $this->lat = (float)$this->get->get('lat');
            $this->lng = (float)$this->get->get('lng');
        }
    }

    /**
     * sqlの組み立て
     *
     * @return SQL_Select
     */
    public function buildGeoListQuery()
    {
        $sql = SQL::newSelect('geo');
        $sql->addLeftJoin('blog', 'blog_id', 'geo_bid');
        $sql->addSelect('*');
        $sql->addSelect('geo_geometry', 'longitude', null, 'ST_X');
        $sql->addSelect('geo_geometry', 'latitude', null, 'ST_Y');
        if ($this->lat && $this->lng) {
            $sql->addGeoDistance('geo_geometry', $this->lng, $this->lat, 'distance');
        }
        if ($this->config['referencePoint'] === 'url_context' && $this->bid) {
            $sql->addWhereOpr('geo_bid', $this->bid, '<>');
        }
        $within = $this->config['within'];
        if ($within > 0) {
            $within = $within * 1000;
            $sql->addHaving(SQL::newOpr('distance', $within, '<'));
        }

        $this->filterBlogQuery($sql, $this->bid, $this->keyword, $this->Field);
        $sql->addOrder('distance', 'ASC');
        $this->limitBlogQuery($sql, (int) $this->config['limit']);
        $sql->setGroup('blog_id');

        return $sql;
    }
}
