<?php

namespace Acms\Modules\Get\V2\Entry;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Entry\EntryQueryHelper;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;
use Acms\Services\Facades\Database;
use ACMS_RAM;
use SQL_Select;

class Summary extends Base
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * 階層の設定
     * @var array<'bid' | 'cid', string>
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'self',
        'cid' => 'self',
    ];

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
            'order' => [
                $this->order ? $this->order : $config->get('entry_summary_order'),
                $config->get('entry_summary_order2'),
            ],
            'orderFieldName' => $config->get('entry_summary_order_field_name'),
            'noNarrowDownSort' => $config->get('entry_summary_no_narrow_down_sort') === 'on',
            'limit' => $this->limit ?? (int) $config->get('entry_summary_limit'),
            'offset' => (int) $config->get('entry_summary_offset'),
            'newItemPeriod' => (int) $config->get('entry_summary_newtime'),
            'displayIndexingOnly' => $config->get('entry_summary_indexing') === 'on',
            'displayMembersOnly' => $config->get('entry_summary_members_only') === 'on',
            'displaySubcategoryEntries' => $config->get('entry_summary_sub_category') === 'on',
            'displaySecretEntry' => $config->get('entry_summary_secret') === 'on',
            'notfoundStatus404' => $config->get('entry_summary_notfound_status_404') === 'on',
            'fulltextEnabled' => $config->get('entry_summary_fulltext') === 'on',
            'fulltextWidth' => (int) $config->get('entry_summary_fulltext_width'),
            'fulltextMarker' => $config->get('entry_summary_fulltext_marker'),
            'includeTags' => $config->get('entry_summary_tag') === 'on',
            'hiddenCurrentEntry' => $config->get('entry_summary_hidden_current_entry') === 'on',
            'hiddenPrivateEntry' => $config->get('entry_summary_hidden_private_entry') === 'on',
            'includeRelatedEntries' => $config->get('entry_summary_related_entry_on') === 'on',
            // 画像系
            'includeMainImage' => $config->get('entry_summary_image_on') === 'on',
            'mainImageTarget' => config('entry_summary_main_image_target', 'field'),
            'mainImageFieldName' => config('entry_summary_main_image_field_name'),
            'displayNoImageEntry' => $config->get('entry_summary_noimage') === 'on',
            // ページネーション
            'simplePagerEnabled' => $config->get('entry_summary_simple_pager_on') === 'on',
            'paginationEnabled' => $config->get('entry_summary_pager_on') === 'on',
            'paginationDelta' => (int) $config->get('entry_summary_pager_delta', 4),
            // フィールド・情報
            'includeEntryFields' => $config->get('entry_summary_entry_field') === 'on',
            'includeCategory' => $config->get('entry_summary_category_on') === 'on',
            'includeCategoryFields' => $config->get('entry_summary_category_field_on') === 'on',
            'includeUser' => $config->get('entry_summary_user_on') === 'on',
            'includeUserFields' => $config->get('entry_summary_user_field_on') === 'on',
            'includeBlog' => $config->get('entry_summary_blog_on') === 'on',
            'includeBlogFields' => $config->get('entry_summary_blog_field_on') === 'on',
            // 表示モード
            'relatedEntryMode' => $config->get('entry_summary_relational') === 'on',
            'relatedEntryType' => $config->get('entry_summary_relational_type'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new \RuntimeException('Failed to set config.');
            }
            // 起動
            $this->boot();
            // SQLの組み立て
            $sql = $this->buildQuery();
            // ルート変数
            $vars = $this->buildRootVars() ?? [];
            // エントリ取得
            $entries = $this->getEntries($sql);
            // Not Found ステータス
            $this->notFoundStatus($entries);
            // カスタム処理
            $vars = $this->preBuild($vars, $entries);
            // 次ページが存在するかどうか
            $hasNextPage = false;
            if (count($entries) > $this->config['limit']) {
                array_pop($entries);
                $hasNextPage = true;
            }
            // エントリ一覧組み立て
            $vars['items'] = $this->buildEntries($entries);
            // ページャー
            $vars['pager'] = $this->buildSimplePager($hasNextPage);
            // ページネーション
            $vars['pagination'] = $this->buildPagination();
            // モジュールフィールド
            $vars['moduleFields'] = $this->buildModuleField();
            // 変数を修正
            $vars = $this->fixVars($vars);

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
        $this->entryQueryHelper = new EntryQueryHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
        $this->entryHelper = new EntryHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * クエリの組み立て
     *
     * @return SQL_Select
     */
    protected function buildQuery(): SQL_Select
    {
        $relatedEntryIds = ($this->config['relatedEntryMode'] ?? false) ?
            $this->entryHelper->getRelationalEntryIds((int) ($this->eid ?? EID), $this->config['relatedEntryType']) : [];

        return $this->entryQueryHelper->buildEntryIndexQuery($relatedEntryIds);
    }

    /**
     * エントリの取得
     *
     * @param SQL_Select $sql
     * @return array
     */
    protected function getEntries(SQL_Select $sql): array
    {
        $entries = Database::query($sql->get(dsn()), 'all');
        foreach ($entries as $entry) {
            ACMS_RAM::entry($entry['entry_id'], $entry);
        }
        return $entries;
    }

    /**
     * ルート変数の組み立て
     *
     * @return array|null
     */
    protected function buildRootVars(): ?array
    {
        return $this->entryHelper->getRootVars();
    }

    /**
     * Not Found ステータスの設定
     *
     * @param array $entries
     * @return void
     */
    protected function notFoundStatus(array $entries): void
    {
        if (!$entries && ($this->config['notfoundStatus404'] ?? false)) {
            $this->entryHelper->notFoundStatus();
        }
    }

    /**
     * ビルド前のカスタム処理
     *
     * @param array $vars
     * @param array $entries
     * @return array
     */
    protected function preBuild(array $vars, array $entries): array
    {
        return $vars;
    }

    /**
     * 変数の修正
     *
     * @param array $vars
     * @return array
     */
    protected function fixVars(array $vars): array
    {
        return $vars;
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
            'includeTags' => $this->config['includeTags'] ?? false,
            'includeEntryFields' => $this->config['includeEntryFields'] ?? false,
            'includeUserFields' => $this->config['includeUserFields'] ?? false,
            'includeBlogFields' => $this->config['includeBlogFields'] ?? false,
            'includeCategoryFields' => $this->config['includeCategoryFields'] ?? false,
            'includeSubCategories' => $this->config['includeCategory'] ?? false,
            'includeRelatedEntries' => $this->config['includeRelatedEntries'] ?? false,
        ]);
        $buildEntries = [];
        foreach ($entries as $i => $row) {
            $i++;
            $buildEntries[] = $this->entryHelper->buildEntry($row, [
                'includeBlog' => $this->config['includeBlog'] ?? false,
                'includeUser' => $this->config['includeUser'] ?? false,
                'includeCategory' => $this->config['includeCategory'] ?? false,
                'fulltextWidth' => (int) ($this->config['fulltextWidth'] ?? 200),
                'fulltextMarker' => $this->config['fulltextMarker'] ?? '',
                'newItemPeriod' => (int) ($this->config['newItemPeriod'] ?? 0),
            ], [], $eagerLoad);
        }
        return $buildEntries;
    }

    /**
     * シンプルページャーの組み立て
     *
     * @param boolean $hasNextPage
     * @return array|null
     */
    protected function buildSimplePager(bool $hasNextPage): ?array
    {
        return $this->entryHelper->buildSimplePager($this->page, $hasNextPage);
    }

    /**
     * ページネーションの組み立て
     *
     * @return array|null
     */
    protected function buildPagination(): ?array
    {
        return $this->entryHelper->buildPagination($this->entryQueryHelper->getCountQuery());
    }
}
