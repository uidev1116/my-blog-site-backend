<?php

namespace Acms\Modules\Get\Helpers\Entry;

use SQL;
use SQL_Select;
use Field;
use Acms\Services\Facades\Database;

class GeoListHelper extends EntryQueryHelper
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
    public function setReferencePoint()
    {
        if ($this->config['referencePoint'] === 'url_context' && $this->eid) {
            $sql = SQL::newSelect('geo', 'geo');
            $sql->addSelect('geo_geometry', 'lat', 'geo', 'ST_Y');
            $sql->addSelect('geo_geometry', 'lng', 'geo', 'ST_X');
            $sql->addWhereOpr('geo_eid', $this->eid);
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
     * sql組み立て
     *
     * @return SQL_Select
     */
    public function buildQuery(): SQL_Select
    {
        $within = $this->config['within'];

        $sql = SQL::newSelect('geo');
        $sql->addLeftJoin('entry', 'entry_id', 'geo_eid');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $sql->addSelect('*');
        $sql->addSelect('geo_geometry', 'longitude', null, 'ST_X');
        $sql->addSelect('geo_geometry', 'latitude', null, 'ST_Y');

        if ($this->config['referencePoint'] === 'url_context' && $this->eid) {
            $sql->addWhereOpr('geo_eid', $this->eid, '<>');
        }
        $this->filterQuery($sql, []);
        $this->setCountQuery($sql);
        $this->limitQuery($sql);

        if ($this->lat && $this->lng) {
            $sql->addGeoDistance('geo_geometry', $this->lng, $this->lat, 'distance');
            if ($within > 0) {
                $within = $within * 1000;
                $sql->addHaving(SQL::newOpr('distance', $within, '<'));
            }
            $sql->addOrder('distance', 'ASC');
        }

        return $sql;
    }

    /**
     * エントリー数取得sqlの準備
     *
     * @param SQL_Select $sql
     * @return void
     */
    public function setCountQuery(SQL_Select $sql): void
    {
        $temp = clone $sql;
        $temp->setSelect('entry_id');
        if ($this->lat && $this->lng) {
            $temp->addGeoDistance('geo_geometry', $this->lng, $this->lat, 'distance');
        }

        $this->countQuery = SQL::newSelect($temp, 'count');
        $this->countQuery->setSelect(SQL::newFunction('entry_id', 'DISTINCT'), 'entry_amount', null, 'COUNT');
    }

    /**
     * エントリーの絞り込み
     *
     * @param SQL_Select $sql
     * @return bool
     */
    public function entryFilterQuery(SQL_Select $sql): bool
    {
        return false;
    }
}
