<?php

namespace Acms\Modules\Get\Helpers\User;

use Acms\Services\Facades\Database;
use SQL;
use SQL_Select;
use Field;

class GeoListHelper extends UserHelper
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
        if ($this->config['referencePoint'] === 'url_context' && $this->uid) {
            $sql = SQL::newSelect('geo', 'geo');
            $sql->addSelect('geo_geometry', 'lat', 'geo', 'ST_Y');
            $sql->addSelect('geo_geometry', 'lng', 'geo', 'ST_X');
            $sql->addWhereOpr('geo_uid', $this->uid);
            $sql->addWhereOpr('geo_blog_id', BID);
            $q = $sql->get(dsn());
            if ($data = Database::query($q, 'row')) {
                $this->lat = $data['lat'];
                $this->lng = $data['lng'];
            }
        } elseif ($this->config['referencePoint'] === 'url_query_string') {
            $this->lat = (float) $this->get->get('lat');
            $this->lng = (float) $this->get->get('lng');
        }
    }

    /**
     * sqlの組み立て
     *
     * @return SQL_Select
     */
    public function buildGeoListQuery(): SQL_Select
    {
        $sql = SQL::newSelect('geo');
        $sql->addLeftJoin('user', 'user_id', 'geo_uid');
        $sql->addLeftJoin('blog', 'blog_id', 'user_blog_id');
        $sql->addSelect('*');
        $sql->addSelect('geo_geometry', 'longitude', null, 'ST_X');
        $sql->addSelect('geo_geometry', 'latitude', null, 'ST_Y');
        if ($this->lat && $this->lng) {
            $sql->addGeoDistance('geo_geometry', $this->lng, $this->lat, 'distance');
        }
        if ($this->config['referencePoint'] === 'url_context' && $this->uid) {
            $sql->addWhereOpr('geo_uid', $this->uid, '<>');
        }
        $within = $this->config['within'];
        if ($within > 0) {
            $within = $within * 1000;
            $sql->addHaving(SQL::newOpr('distance', $within, '<'));
        }

        $this->filterQuery($sql);
        $this->setCountQuery($sql);
        $sql->addOrder('distance', 'ASC');
        $this->limitQuery($sql);

        return $sql;
    }

    /**
     * ユーザー数取得sqlの準備
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function setCountQuery(SQL_Select $sql): void
    {
        $this->countQuery = SQL::newSelect(clone $sql, 'amount');
        $this->countQuery->setSelect(SQL::newFunction('user_id', 'DISTINCT'), 'user_amount', null, 'COUNT');
    }

    /**
     * limitクエリ組み立て
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function limitQuery(SQL_Select $sql): void
    {
        $limit = $this->config['limit'];
        $from = ($this->page - 1) * $limit;
        $sql->setLimit($limit, $from);
    }
}
