<?php

namespace Acms\Modules\Get\V2\Category;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Category\GeoListHelper;
use Acms\Services\Facades\Database;
use ACMS_RAM;
use SQL_Select;
use RuntimeException;

class GeoList extends Base
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @var \Acms\Modules\Get\Helpers\Category\GeoListHelper
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
            'referencePoint' => $config->get('category_geo-list_reference_point'),
            'within' => (float) $config->get('category_geo-list_within'),
            'limit' => $this->limit ?? (int) $config->get('category_geo-list_limit'),
            'pager_delta' => $config->get('category_geo-list_pager_delta'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new RuntimeException('Failed to set config.');
            }
            $vars = [];
            // 起動
            $this->boot();
            // SQL組み立て
            $sql = $this->buildQuery();
            // カテゴリー取得
            $categories = $this->getCategories($sql);
            // カスタム処理
            $vars = $this->preBuild($vars, $categories);
            // 一覧組み立て
            $vars['items'] = $this->build($categories);
            $vars['pagination'] = $this->buildPagination();
            $vars['moduleFields'] = $this->buildModuleField();

            return $vars;
        } catch (\Exception $e) {
            return [];
        }
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
     * クエリの組み立て
     *
     * @return SQL_Select
     */
    protected function buildQuery(): SQL_Select
    {
        return $this->geoListHelper->buildGeoListQuery();
    }

    /**
     * ビルド前のカスタム処理
     *
     * @param array $vars
     * @param array $categories
     * @return array
     */
    protected function preBuild(array $vars, array $categories): array
    {
        $vars['hasLocation'] = $this->hasLocation;
        return $vars;
    }

    /**
     * 一覧の組み立て
     *
     * @param array $categories
     * @return array
     */
    protected function build(array $categories): array
    {
        return $this->geoListHelper->buildGeoList($categories) ?? [];
    }

    /**
     * ページネーションの組み立て
     *
     * @return array|null
     */
    protected function buildPagination(): ?array
    {
        if (!$this->hasLocation) {
            return null;
        }
        $countQuery = $this->geoListHelper->getCountQuery();
        return $this->geoListHelper->buildPagination($countQuery);
    }

    /**
     * カテゴリーの取得
     *
     * @param SQL_Select $sql
     * @return array
     */
    protected function getCategories(SQL_Select $sql): array
    {
        if (!$this->hasLocation) {
            return [];
        }
        $categories = Database::query($sql->get(dsn()), 'all');
        foreach ($categories as $category) {
            $cid = (int) $category['category_id'];
            ACMS_RAM::category($cid, $category);
        }
        return $categories;
    }
}
