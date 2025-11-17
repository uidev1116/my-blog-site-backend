<?php

namespace Acms\Modules\Get\V2\Category;

use Acms\Modules\Get\Helpers\Entry\EntryHelper;
use Acms\Modules\Get\Helpers\Entry\EntryQueryHelper;
use Acms\Services\Facades\Database;

class EntrySummary extends Tree
{
    /**
     * @var \Acms\Modules\Get\Helpers\Entry\EntryQueryHelper
     */
    protected $entryQueryHelper;

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\EntryHelper
     */
    protected $entryHelper;

    /**
     * コンフィグの取得
     *
     * @return array<string, mixed>
     */
    protected function initConfig(): array
    {
        $config = $this->loadModuleConfig();
        return [
            // カテゴリー系
            'categoryOrder' => $this->order ? $this->order : $config->get('category_entry_summary_category_order'),
            'displayCategoryWithoutEntry' => $config->get('category_entry_summary_entry_amount_zero') === 'on',
            'displayEntryCount' => false,
            'categoryDisplayDepth' => (int) $config->get('category_entry_summary_level', 99),
            'searchTarget' => $config->get('category_entry_summary_field_search'),
            'displayCategoryField' => $config->get('category_entry_summary_field') === 'on',
            'displayGeolocation' => false,
            'categoryDisplayIndexingOnly' => $config->get('category_entry_summary_category_indexing') === 'on',
            'countEntryInSubcategories' => false,

            // エントリー系
            'order' => $config->get('category_entry_summary_order'),
            'limit' => $this->limit ?? (int) $config->get('category_entry_summary_limit'),
            'offset' => (int) $config->get('category_entry_summary_offset'),
            'newItemPeriod' => (int) $config->get('category_entry_summary_newtime'),
            'displayIndexingOnly' => $config->get('category_entry_summary_indexing') === 'on',
            'displayMembersOnly' => false,
            'displaySubcategoryEntries' => $config->get('category_entry_summary_sub_category') === 'on',
            'displaySecretEntry' => $config->get('category_entry_summary_secret') === 'on',
            'notfoundStatus404' => false,
            'fulltextEnabled' => true,
            'fulltextWidth' => (int) $config->get('category_entry_summary_fulltext_width'),
            'fulltextMarker' => $config->get('category_entry_summary_fulltext_marker'),
            'includeTags' => false,
            'includeEntryFields' => $config->get('category_entry_summary_entry_field_on') === 'on',
            'includeUserFields' => $config->get('category_entry_summary_user_field_on') === 'on',
            'includeBlogFields' => $config->get('category_entry_summary_blog_field_on') === 'on',
            'includeMainImage' => $config->get('category_entry_summary_image_on') === 'on',
            'mainImageTarget' => config('category_entry_summary_main_image_target', 'field'),
            'mainImageFieldName' => config('category_entry_summary_main_image_field_name'),
            'displayNoImageEntry' => $config->get('category_entry_summary_noimage') === 'on',
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $vars = parent::get();
        $this->entryQueryHelper = new EntryQueryHelper($this->getBaseParams([
            'config' => $this->config,
            'categoryAxis' => 'self',
        ]));
        $this->entryHelper = new EntryHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
        $this->axis['cid'] = 'self';
        $vars['items'] = $this->addEntryData($vars['items']);

        return $vars;
    }

    protected function addEntryData(array $items): array
    {
        $newData = [];
        foreach ($items as $category) {
            // 子カテゴリの再帰的なフィルタリング
            if (isset($category['children']) && is_array($category['children'])) {
                $category['children'] = $this->addEntryData($category['children']);
            }
            $this->entryQueryHelper->setProperty('cid', (int) $category['cid']);
            $sql = $this->entryQueryHelper->buildEntryIndexQuery();
            $q = $sql->get(dsn());
            $entries = Database::query($q, 'all');
            if (count($entries) > $this->config['limit']) {
                array_pop($entries);
            }
            $category['entries'] = $this->buildEntries($entries);
            $newData[] = $category;
        }
        return $newData;
    }

    /**
     * テンプレートの組み立て
     *
     * @return array
     */
    protected function buildEntries(array $entries): array
    {
        $eagerLoad = $this->entryHelper->eagerLoad($entries, [
            'includeMainImage' => $this->config['includeMainImage'] ?? false,
            'mainImageTarget' => $this->config['mainImageTarget'] ?? 'field',
            'mainImageFieldName' => $this->config['mainImageFieldName'] ?? '',
            'includeFulltext' => $this->config['fulltextEnabled'] ?? false,
            'includeTags' => false,
            'includeEntryFields' => $this->config['includeEntryFields'] ?? false,
            'includeUserFields' => $this->config['includeUserFields'] ?? false,
            'includeBlogFields' => $this->config['includeBlogFields'] ?? false,
            'includeCategoryFields' => false,
            'includeSubCategories' => $this->config['displaySubcategoryEntries'] ?? false,
            'includeRelatedEntries' => false,
        ]);

        $entryData = [];
        foreach ($entries as $row) {
            $entryData[] = $this->entryHelper->buildEntry($row, [
                'includeBlog' => true,
                'includeUser' => true,
                'includeCategory' => false,
                'fulltextWidth' => (int) ($this->config['fulltextWidth'] ?? 200),
                'fulltextMarker' => $this->config['fulltextMarker'] ?? '',
                'newItemPeriod' => (int) ($this->config['newItemPeriod'] ?? 0),
            ], [], $eagerLoad);
        }
        return $entryData;
    }
}
