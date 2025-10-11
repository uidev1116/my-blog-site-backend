<?php

namespace Acms\Modules\Get\V2\User;

use Acms\Modules\Get\Helpers\User\GeoListHelper;
use SQL_Select;

class GeoList extends Search
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'uid' => 'global',
        'field' => 'global',
        'page' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\User\GeoListHelper
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
            'referencePoint' => $config->get('user_geo-list_reference_point'),
            'within'  => (float) $config->get('user_geo-list_within'),
            'indexing' => $config->get('user_geo-list_indexing'),
            'auth' => $config->getArray('user_geo-list_auth'),
            'status' => $config->getArray('user_geo-list_status'),
            'mail_magazine' => $config->getArray('user_geo-list_mail_magazine'),
            'limit' => $this->limit ?? (int) $config->get('user_geo-list_limit'),
            'pager_delta' => $config->get('user_geo-list_pager_delta'),
            'entry_list_enable' => $config->get('user_geo-list_entry_list_enable'),
            'entry_list_order' => $config->get('user_geo-list_entry_list_order'),
            'entry_list_limit' => $config->get('user_geo-list_entry_list_limit'),
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
     * @param array $users
     * @return array
     */
    protected function preBuild(array $vars, array $users): array
    {
        $vars['hasLocation'] = $this->hasLocation;
        return $vars;
    }

    /**
     * ユーザー一覧の組み立て
     *
     * @param array $users
     * @return array
     */
    protected function build(array $users): array
    {
        return $this->geoListHelper->buildUserIndex($users);
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
     * @inheritDoc
     */
    protected function getUsers(SQL_Select $sql): array
    {
        if (!$this->hasLocation) {
            return [];
        }
        return parent::getUsers($sql);
    }
}
