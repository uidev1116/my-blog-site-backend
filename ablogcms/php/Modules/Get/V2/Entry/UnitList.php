<?php

namespace Acms\Modules\Get\V2\Entry;

use Acms\Modules\Get\Helpers\Entry\UnitListHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Media;
use RuntimeException;

class UnitList extends Summary
{
    /**
     * @inheritDoc
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'cid' => 'global',
        'eid' => 'global',
        'start' => 'global',
        'end' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\UnitListHelper
     */
    protected $unitListHelper;

    /**
     * コンフィグの取得
     *
     * @return array{
     *   order: array{string},
     *   limit: int,
     *   offset: 0,
     *   paginationEnabled: bool,
     *   paginationDelta: int,
     *   includeEntry: bool,
     *   includeEntryFields: bool,
     *   includeCategory: bool,
     *   includeCategoryFields: bool,
     *   includeUser: bool,
     *   includeUserFields: bool,
     *   includeBlog: bool,
     *   includeBlogFields: bool,
     * }
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            'order' => [$config->get('column_list_order')],
            'limit' => $this->limit ?? (int) $config->get('column_list_limit'),
            'offset' => 0,
            'paginationEnabled' => true,
            'paginationDelta' => (int) $config->get('column_list_pager_delta', 4),
            // フィールド・情報
            'includeEntry' => $config->get('column_list_entry_on') === 'on',
            'includeEntryFields' => $config->get('column_list_entry_field') === 'on',
            'includeCategory' => $config->get('column_list_category_on') === 'on',
            'includeCategoryFields' => $config->get('column_list_category_field_on') === 'on',
            'includeUser' => $config->get('column_list_user_on') === 'on',
            'includeUserFields' => $config->get('column_list_user_field_on') === 'on',
            'includeBlog' => $config->get('column_list_blog_on') === 'on',
            'includeBlogFields' => $config->get('column_list_blog_field_on') === 'on',
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new RuntimeException('Not found config.');
            }
            $this->boot();
            $vars = [];
            $sql = $this->unitListHelper->buildUnitListQuery();
            $q = $sql->get(dsn());
            $unitData = Database::query($q, 'all');
            if (count($unitData) > $this->config['limit']) {
                array_pop($unitData);
            }
            $vars['items'] = $this->buildUnit($unitData);
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
        parent::boot();
        $this->unitListHelper = new UnitListHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * テンプレートの組み立て
     *
     * @return array
     */
    protected function buildUnit(array $unitData): array
    {
        $eagerLoad = $this->entryHelper->eagerLoad($unitData, [
            'includeMainImage' => false,
            'includeFulltext' => false,
            'includeTags' => false,
            'includeEntryFields' => (bool) ($this->config['includeEntryFields'] ?? false),
            'includeUserFields' => (bool) ($this->config['includeUserFields'] ?? false),
            'includeBlogFields' => (bool) ($this->config['includeBlogFields'] ?? false),
            'includeCategoryFields' => (bool) ($this->config['includeCategoryFields'] ?? false),
            'includeSubCategories' => false,
            'includeRelatedEntries' => false,
        ]);

        /** @var \Acms\Services\Unit\Repository $unitRepository */
        $unitRepository = Application::make('unit-repository');
        $collection = $unitRepository->loadModels($unitData);
        $unitRepository->eagerLoadCustomUnitFields($collection);
        $mediaEagerLoading = Media::mediaEagerLoadFromUnit($collection);

        $acmsTplEngine = Application::make('template.acms');
        $acmsTplEngine->load('/include/unit.html', config('theme'), BID);
        $tpl = $acmsTplEngine->getTemplate();

        $units = [];
        foreach ($unitData as $unit) {
            $data = $this->entryHelper->buildUnitList($unit, [
                'includeEntry' => $this->config['includeEntry'] ?? false,
                'includeBlog' => $this->config['includeBlog'] ?? false,
                'includeUser' => $this->config['includeUser'] ?? false,
                'includeCategory' => $this->config['includeCategory'] ?? false,
            ], $eagerLoad, $mediaEagerLoading, $unitRepository, $tpl);
            if ($data) {
                $units[] = $data;
            }
        }
        return $units;
    }

    /**
     * ページネーションの組み立て
     *
     * @return array|null
     */
    protected function buildPagination(): ?array
    {
        return $this->entryHelper->buildPagination($this->unitListHelper->getCountQuery());
    }
}
