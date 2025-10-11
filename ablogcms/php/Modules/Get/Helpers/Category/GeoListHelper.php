<?php

namespace Acms\Modules\Get\Helpers\Category;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Database;
use SQL;
use SQL_Select;
use ACMS_Filter;
use Field;

class GeoListHelper extends BaseHelper
{
    use \Acms\Traits\Utilities\FieldTrait;
    use \Acms\Traits\Utilities\EagerLoadingTrait;
    use \Acms\Traits\Utilities\PaginationTrait;

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
     * カテゴリー数取得用のSQLを返す
     *
     * @return SQL_Select
     */
    public function getCountQuery(): SQL_Select
    {
        return $this->countQuery;
    }

    /**
     * 基準点となる位置情報を取得
     *
     * @return void
     */
    public function setReferencePoint(): void
    {
        if ($this->config['referencePoint'] === 'url_context' && $this->cid) {
            $SQL = SQL::newSelect('geo', 'geo');
            $SQL->addSelect('geo_geometry', 'lat', 'geo', 'ST_Y');
            $SQL->addSelect('geo_geometry', 'lng', 'geo', 'ST_X');
            $SQL->addWhereOpr('geo_cid', $this->cid);
            $q = $SQL->get(dsn());
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
    public function buildGeoListQuery(): SQL_Select
    {
        $sql = SQL::newSelect('geo');
        $sql->addLeftJoin('category', 'category_id', 'geo_cid');
        $sql->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        $sql->addSelect('*');
        $sql->addSelect('geo_geometry', 'longitude', null, 'ST_X');
        $sql->addSelect('geo_geometry', 'latitude', null, 'ST_Y');
        if ($this->lat && $this->lng) {
            $sql->addGeoDistance('geo_geometry', $this->lng, $this->lat, 'distance');
        }
        if ($this->config['referencePoint'] === 'url_context' && $this->cid) {
            $sql->addWhereOpr('geo_cid', $this->cid, '<>');
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
        $sql->setGroup('category_id');

        return $sql;
    }

    /**
     * 絞り込みクエリ組み立て
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterQuery(SQL_Select $sql): void
    {
        if ($this->cid) {
            $sql->addWhereOpr('category_parent', $this->cid);
        }
        $sql->addWhereOpr('blog_indexing', 'on');
        ACMS_Filter::categoryStatus($sql);
        if ($this->keyword) {
            ACMS_Filter::categoryKeyword($sql, $this->keyword);
        }
        if ($this->Field) {
            ACMS_Filter::categoryField($sql, $this->Field);
        }
    }

    /**
     * limitクエリ組み立て
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function limitQuery($sql): void
    {
        $limit = $this->config['limit'];
        $from = ($this->page - 1) * $limit;
        $sql->setLimit($limit, $from);
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
        $this->countQuery->setSelect(SQL::newFunction('category_id', 'DISTINCT'), 'category_amount', null, 'COUNT');
    }

    /**
     * カテゴリー一覧の組み立て
     *
     * @param array $categories
     * @return array
     */
    public function buildGeoList(array $categories): ?array
    {
        $items = [];
        $categoryIds = array_map(function ($category) {
            return (int) $category['category_id'];
        }, $categories);
        $eagerLoadingField = $this->eagerLoadFieldTrait($categoryIds, 'cid');

        foreach ($categories as $row) {
            $cid = (int) $row['category_id'];
            $vars = [
                'cid' => $cid,
                'code' => $row['category_code'] ?? null,
                'name' => $row['category_name'] ?? null,
                'indexing' => $row['category_indexing'] ?? null,
                'blog_id' => (int) $row['category_blog_id'],
            ];
            $vars['url'] = acmsLink([
                'bid' => $row['category_blog_id'],
                'cid' => $cid,
            ], false);
            $vars['fields'] = isset($eagerLoadingField[$cid]) ? $this->buildFieldTrait($eagerLoadingField[$cid]) : null;
            $vars['geo'] = [
                'lat' => $row['latitude'] ?? null,
                'lng' => $row['longitude'] ?? null,
                'zoom' => $row['geo_zoom'] ?? null,
                'distance' => $row['distance'] ?? null,
            ];
            $items[] = $vars;
        }
        return $items;
    }

    /**
     * ページネーションを組み立て
     *
     * @param SQL_Select $countQuery
     * @return null|array
     */
    public function buildPagination(SQL_Select $countQuery): ?array
    {
        $q = $countQuery->get(dsn());
        $itemsAmount = (int) Database::query($q, 'one');
        return $this->buildPaginationTrait(
            $this->page,
            $itemsAmount,
            $this->config['limit'],
            $this->config['pager_delta']
        );
    }
}
