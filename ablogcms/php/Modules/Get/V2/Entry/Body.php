<?php

namespace Acms\Modules\Get\V2\Entry;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Entry\EntryQueryHelper;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;
use Acms\Modules\Get\Helpers\Entry\EntryBodyHelper;
use Acms\Services\Entry\Exceptions\NotFoundException;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Entry;
use Acms\Services\Unit\UnitCollection;
use Acms\Services\Facades\Common;
use ACMS_Corrector;
use ACMS_RAM;
use Exception;
use RuntimeException;
use Template;

class Body extends Base
{
    use \Acms\Traits\Modules\ConfigTrait;

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
        'uid' => 'global',
        'cid' => 'global',
        'eid' => 'global',
        'keyword' => 'global',
        'tag' => 'global',
        'field' => 'global',
        'date' => 'global',
        'start' => 'global',
        'end' => 'global',
        'page' => 'global',
        'order' => 'global',
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
     * @var \Acms\Modules\Get\Helpers\Entry\EntryBodyHelper
     */
    protected $entryBodyHelper;

    /**
     * @var array
     */
    protected $entries = [];

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
                $this->order ? $this->order : $config->get('entry_body_order'),
                $config->get('entry_body_order2'),
            ],
            'categoryOrder' => $config->get('entry_body_category_order'),
            'limit' => $this->limit ?? (int) $config->get('entry_body_limit'),
            'offset' => (int) $config->get('entry_body_offset'),
            'displayIndexingOnly' => $config->get('entry_body_indexing') === 'on',
            'displayMembersOnly' => $config->get('entry_summary_members_only') === 'on',
            'displaySubcategoryEntries' => $config->get('entry_body_sub_category') === 'on',
            'newItemPeriod' => (int) $config->get('entry_body_newtime'),
            'includeTags' => $config->get('entry_body_tag_on') === 'on',
            'fulltextEnabled' => $config->get('entry_body_summary_on') === 'on',
            'fulltextWidth' => (int) $config->get('entry_body_fulltext_width'),
            'fulltextMarker' => $config->get('entry_body_fulltext_marker'),
            'fixedSummaryRange' => (int) $config->get('entry_body_fix_summary_range'),
            'displayAllUnits' => $config->get('entry_body_show_all_index') === 'on',
            'geolocationEnabled' => $config->get('entry_body_geolocation_on') === 'on',
            'includeRelatedEntries' => $config->get('entry_body_related_entry_on') === 'on',
            'notfoundStatus404' => $config->get('entry_body_notfound_status_404') === 'on',
            // ページネーション
            'simplePagerEnabled' => $config->get('entry_body_simple_pager_on') === 'on',
            'paginationEnabled' => $config->get('entry_body_pager_on') === 'on',
            'paginationDelta' => (int) $config->get('entry_body_pager_delta', 4),
            // 前後リンク
            'serialNaviEnabled' => $config->get('entry_body_serial_navi_on') === 'on',
            'serialNaviIgnoreCategory' => $config->get('entry_body_serial_navi_ignore_category') === 'on',
            // マイクロページ
            'micropagerEnabled' => $config->get('entry_body_micropage') === 'on',
            'micropagerDelta' => (int) $config->get('entry_body_micropager_delta', 4),
            // 画像系
            'includeMainImage' => $config->get('entry_body_image_on') === 'on',
            'mainImageTarget' => config('entry_body_main_image_target', 'field'),
            'mainImageFieldName' => config('entry_body_main_image_field_name'),
            // フィールド・情報
            'includeEntryFields' => $config->get('entry_body_entry_field_on') === 'on',
            'includeCategory' => $config->get('entry_body_category_info_on') === 'on',
            'includeCategoryFields' => $config->get('entry_body_category_field_on') === 'on',
            'includeUser' => $config->get('entry_body_user_info_on') === 'on',
            'includeUserFields' => $config->get('entry_body_user_field_on') === 'on',
            'includeBlog' => $config->get('entry_body_blog_info_on') === 'on',
            'includeBlogFields' => $config->get('entry_body_blog_field_on') === 'on',
        ];
    }

    /**
     *
     * @return array|never
     */
    public function get(): array
    {
        try {
            if (!$this->setConfigTrait()) {
                return [];
            }
            // 起動
            $this->boot();
            $vars = [];

            if (strval($this->eid) === strval(intval($this->eid)) && ($this->eid ?? 0) > 0) {
                // エントリー詳細ページ
                $vars = $this->entryPage();
            } else {
                // エントリー一覧ページ
                $vars = $this->entryIndex();
            }
        } catch (NotFoundException $e) {
            if ($this->config['notfoundStatus404'] ?? false) {
                $this->entryHelper->notFoundStatus();
            }
        }
        return $vars;
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
        $this->entryBodyHelper = new EntryBodyHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * エントリー詳細ページ
     * @return array
     * @throws Exception
     * @throws NotFoundException
     */
    protected function entryPage(): array
    {
        if (!$this->eid) {
            throw new RuntimeException('Entry ID (eid) is not set.');
        }
        $sql = $this->entryQueryHelper->buildEntryQuery($this->eid, RVID);
        $q = $sql->get(dsn());
        $entry = Database::query($q, 'row');
        if (empty($entry)) {
            throw new NotFoundException();
        }
        $vars = [
            'pageType' => 'entry',
        ];
        $this->entries[] = $entry;
        $eid = (int) $entry['entry_id'];
        $this->entryBodyHelper->setIsMembersOnlyEntry(($entry['entry_members_only'] ?? 'off') === 'on');
        $rvid = RVID;
        if (!RVID && $entry['entry_approval'] === 'pre_approval') { // @phpstan-ignore-line
            $rvid = 1;
        }
        $eagerLoaded = $this->eagerLoadEntryBody([$entry], $rvid);

        // 一覧URL
        $vars['indexUrl'] = acmsLink([
            'bid' => $this->bid,
            'eid' => null,
        ]);
        // ユニットを取得
        $allUnitCollection = $this->entryBodyHelper->getAllUnitCollection($eid, $rvid);
        $summaryRange = strlen($entry['entry_summary_range'] ?? '') ? (int) $entry['entry_summary_range'] : null;
        $publicUnitCollection = $this->entryBodyHelper->getPublicUnitCollection($allUnitCollection, $summaryRange);
        // 前後リンクを組み立て
        $vars['serialNavi'] = ($this->config['serialNaviEnabled'] ?? false) ? $this->entryHelper->buildSerialNavi($eid, $this->config['order'][0], $this->config['serialNaviIgnoreCategory'] ?? false, $eagerLoaded['entryField'][$eid] ?? null) : null;
        // マイクロページを組み立て
        $vars['microPagination'] = ($this->config['micropagerEnabled'] ?? false) ? $this->buildMicroPagination($allUnitCollection) : null;
        // モジュールフィールド
        $vars['fields'] = $this->buildModuleField();
        // エントリーのタイトルを修正
        $entry['entry_title'] = $this->entryBodyHelper->getFixTitle($entry['entry_title']);
        // エントリーを組み立て
        $builtEntry = $this->buildEntry($entry, $eagerLoaded);
        // ユニットを組み立て
        $tpl = $this->getUnitTemplate();
        $builtEntry['body'] = $this->buildUnitHtml($eid, $publicUnitCollection, $tpl);
        // 会員限定ユニットを含むかどうか
        $builtEntry['hasSecretUnits'] = $this->entryBodyHelper->getIsMembersOnly() && $summaryRange !== null && $this->entryBodyHelper->containsMembersOnlyUnitOnMicroPage($allUnitCollection, $summaryRange, $this->page);
        // 動的フォームを表示するかどうか
        $builtEntry['isFormVisible'] = $this->isFormVisible($entry);
        // 編集情報の組み立て
        $builtEntry['editorialInfo'] = $this->buildEditorialInfo($entry);
        $vars['items'] = [];
        $vars['items'][] = $builtEntry;

        return $vars;
    }

    /**
     * エントリー一覧ページ
     * @return array
     * @throws NotFoundException
     */
    protected function entryIndex(): array
    {
        // クエリ組み立て
        $sql = $this->entryQueryHelper->buildEntryIndexQuery();
        $q = $sql->get(dsn());

        // エントリ取得
        $this->entries = Database::query($q, 'all');
        foreach ($this->entries as $entry) {
            ACMS_RAM::entry($entry['entry_id'], $entry);
        }
        // Not Found ステータス
        if (!$this->entries && ($this->config['notfoundStatus404'] ?? false)) {
            throw new NotFoundException();
        }
        // 次ページが存在するかどうか
        $hasNextPage = false;
        if (count($this->entries) > $this->config['limit']) {
            array_pop($this->entries);
            $hasNextPage = true;
        }
        // ルート変数
        $vars = $this->entryHelper->getRootVars();
        $vars['pageType'] = 'index';
        // エントリ一覧組み立て
        $vars['items'] = $this->buildEntryIndex();
        // ページャー
        $vars['pager'] = $this->entryHelper->buildSimplePager($this->page, $hasNextPage);
        // ページネーション
        $vars['pagination'] = $this->buildPagination();
        // モジュールフィールド
        $vars['moduleFields'] = $this->buildModuleField();

        return $vars;
    }

    /**
     * Eager Loading
     * @param array $entries
     * @param ?int $rvid
     * @return array
     */
    protected function eagerLoadEntryBody(array $entries, ?int $rvid = null): array
    {
        return $this->entryHelper->eagerLoad($entries, [
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
        ], $rvid);
    }

    /**
     * エントリー1件を組み立てs
     * @param array $entry
     * @param array $eagerLoaded
     * @return array
     */
    protected function buildEntry(array $entry, array $eagerLoaded): array
    {
        return $this->entryHelper->buildEntry($entry, [
            'includeBlog' => $this->config['includeBlog'] ?? false,
            'includeUser' => $this->config['includeUser'] ?? false,
            'includeCategory' => $this->config['includeCategory'] ?? false,
            'fulltextWidth' => (int) ($this->config['fulltextWidth'] ?? 200),
            'fulltextMarker' => $this->config['fulltextMarker'] ?? '',
            'newItemPeriod' => (int) ($this->config['newItemPeriod'] ?? 0),
        ], [], $eagerLoaded);
    }

    /**
     * エントリー一覧を組み立て
     * @return array
     * @throws Exception
     */
    protected function buildEntryIndex(): array
    {
        $entries = [];
        $eagerLoaded = $this->eagerLoadEntryBody($this->entries);
        $unitTemplate = $this->getUnitTemplate();

        foreach ($this->entries as $entry) {
            // エントリーを組み立て
            $builtEntry = $this->buildEntry($entry, $eagerLoaded);
            // ユニットを組み立て
            $eid = (int) $entry['entry_id'];
            $rvid = RVID;
            if (!RVID && $entry['entry_approval'] === 'pre_approval') { // @phpstan-ignore-line
                $rvid = 1;
            }
            $allUnitCollection = $this->entryBodyHelper->getAllUnitCollection($eid, $rvid);
            $displayUnitCollection = $this->entryBodyHelper->getDisplayUnitCollection($entry, $allUnitCollection);
            $builtEntry['hasMoreUnits'] = count($allUnitCollection) > count($displayUnitCollection);
            $builtEntry['body'] = $this->buildUnitHtml((int) $entry['entry_id'], $displayUnitCollection, $unitTemplate);
            // 編集情報を組み立て
            $builtEntry['editorialInfo'] = $this->buildEditorialInfo($entry);
            $entries[] = $builtEntry;
        }
        return $entries;
    }

    /**
     * ユニットテンプレートを取得
     * @return string
     */
    protected function getUnitTemplate(): string
    {
        $acmsTplEngine = Application::make('template.acms');
        $acmsTplEngine->load('/include/unit.html', config('theme'), BID);
        return $acmsTplEngine->getTemplate();
    }

    /**
     * ユニットのHTMLを組み立て
     * @param int $eid
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @return string
     */
    protected function buildUnitHtml(int $eid, UnitCollection $collection, string $tpl): string
    {
        Common::setForceV1Build(true);
        $tplEngine = new Template($tpl, new ACMS_Corrector());
        $this->entryBodyHelper->buildColumn($collection, $tplEngine, $eid);

        $unitHtml = buildIF($tplEngine->get());
        $unitHtml = removeComments($unitHtml);
        $unitHtml = removeBlank($unitHtml);
        Common::setForceV1Build(false);

        if (isApiBuildOrV2Module()) {
            $unitHtml = Common::convertRelativeUrlsToAbsolute($unitHtml, BASE_URL);
        }
        $unitHtml = Common::replaceDeliveryUrlAll($unitHtml);

        return $unitHtml;
    }

    /**
     * ページネーションを組み立て
     * @return null|array
     */
    protected function buildPagination(): ?array
    {
        return $this->entryHelper->buildPagination($this->entryQueryHelper->getCountQuery());
    }

    /**
     * マイクロページネーションを組み立て
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @return null|array
     */
    protected function buildMicroPagination(UnitCollection $collection): ?array
    {
        return $this->entryBodyHelper->buildMicroPagination($collection);
    }

    /**
     * 動的フォームを表示するかどうか
     * @param array $entry
     * @return bool
     */
    protected function isFormVisible(array $entry): bool
    {
        if (
            ($entry['entry_form_id'] ?? false) &&
            ($entry['entry_form_status'] ?? false) === 'open' &&
            config('form_edit_action_direct') === 'on'
        ) {
            return true;
        }
        return false;
    }

    /**
     * 編集情報を組み立て
     * @param array $entry
     * @return null|array
     */
    protected function buildEditorialInfo(array $entry): ?array
    {
        if (!Login::isLoggedIn()) {
            // ユーザーがログインしていない場合は処理を終了
            return null;
        }
        $vars = [
            'editBtn' => false,
            'publishBtn' => false,
            'deleteBtn' => false,
            'revisionBtn' => false,
        ];
        $bid = isset($entry['entry_blog_id']) ? (int) $entry['entry_blog_id'] : null;
        $uid = isset($entry['entry_user_id']) ? (int) $entry['entry_user_id'] : null;
        $eid = isset($entry['entry_id']) ? (int) $entry['entry_id'] : null;
        if ($bid === null || $uid === null || $eid === null) {
            // ブログID、ユーザーID、エントリーIDが存在しない場合は不正なエントリーとして処理を終了
            return null;
        }
        if (!$this->entryBodyHelper->canEditEntry($bid, $uid, $eid)) {
            // 編集権限がない場合は処理を終了
            return null;
        }

        if (!sessionWithApprovalAdministrator() || $entry['entry_approval'] !== 'pre_approval') {
            // 最終承認者ではないか、エントリーが承認前でない場合に編集ブロックを追加
            $vars['editBtn'] = true;
            $vars['revisionBtn'] = true;
        }

        if (BID === $bid) {
            // 現在のブログIDとエントリーのブログIDが一致する場合
            $vars['publishBtn'] = 'open' !== $entry['entry_status'];
        }

        // 削除オプションの追加
        if (Entry::canDelete($eid)) {
            $vars['deleteBtn'] = true;
        }

        return $vars;
    }
}
