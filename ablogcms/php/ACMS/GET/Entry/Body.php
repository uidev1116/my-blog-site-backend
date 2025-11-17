<?php

use Acms\Modules\Get\Helpers\Entry\EntryQueryHelper;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;
use Acms\Modules\Get\Helpers\Entry\EntryBodyHelper;
use Acms\Services\Entry\Exceptions\NotFoundException;
use Acms\Services\Facades\Template as TplHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Login;
use Acms\Services\Unit\UnitCollection;

class ACMS_GET_Entry_Body extends ACMS_GET_Entry
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    public $_axis = [ // phpcs:ignore
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    /**
     * @inheritDoc
     */
    public $_scope = [ // phpcs:ignore
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
     * EagerLoadedData
     *
     * @var array
     */
    protected $eagerLoadedData = [];

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
     * コンフィグのロード
     *
     * @return array<string, mixed>
     */
    function initConfig(): array
    {
        return [
            'order' => [
                $this->order ? $this->order : config('entry_body_order'),
                config('entry_body_order2'),
            ],
            'categoryOrder' => config('entry_body_category_order'),
            'limit' => (int) config('entry_body_limit'),
            'offset' => (int) config('entry_body_offset'),
            'displayIndexingOnly' => config('entry_body_indexing') === 'on',
            'displayMembersOnly' => config('entry_summary_members_only') === 'on',
            'displaySubcategoryEntries' => config('entry_body_sub_category') === 'on',
            'newItemPeriod' => (int) config('entry_body_newtime'),
            'includeTags' => config('entry_body_tag_on') === 'on',
            'fulltextEnabled' => config('entry_body_summary_on') === 'on',
            'fulltextWidth' => (int) config('entry_body_fulltext_width'),
            'fulltextMarker' => config('entry_body_fulltext_marker'),
            'fixedSummaryRange' => (int) config('entry_body_fix_summary_range'),
            'displayAllUnits' => config('entry_body_show_all_index') === 'on',
            'geolocationEnabled' => config('entry_body_geolocation_on') === 'on',
            'includeRelatedEntries' => config('entry_body_related_entry_on') === 'on',
            'notfoundStatus404' => config('entry_body_notfound_status_404') === 'on',
            // ページネーション
            'simplePagerEnabled' => config('entry_body_simple_pager_on') === 'on',
            'paginationEnabled' => config('entry_body_pager_on') === 'on',
            'paginationDelta' => (int) config('entry_body_pager_delta', 4),
            'paginationCurrentAttr' => config('entry_body_pager_cur_attr'),
            // 前後リンク
            'serialNaviEnabled' => config('entry_body_serial_navi_on') === 'on',
            'serialNaviIgnoreCategory' => config('entry_body_serial_navi_ignore_category') === 'on',
            // マイクロページ
            'micropagerEnabled' => config('entry_body_micropage') === 'on',
            'micropagerDelta' => (int) config('entry_body_micropager_delta', 4),
            'micropagerCurrentAttr' => config('entry_body_micropager_cur_attr'),
            // 画像系
            'includeMainImage' => config('entry_body_image_on') === 'on',
            'mainImageTarget' => config('entry_body_main_image_target', 'unit'),
            'mainImageFieldName' => config('entry_body_main_image_field_name'),
            'imageX' => (int) config('entry_body_image_x', 200),
            'imageY' => (int) config('entry_body_image_y', 200),
            'imageTrim' => config('entry_body_image_trim') === 'on',
            'imageZoom' => config('entry_body_image_zoom') === 'on',
            'imageCenter' => config('entry_body_image_center') === 'on',
            // フィールド・情報
            'includeEntryFields' => config('entry_body_entry_field_on') === 'on',
            'includeCategory' => config('entry_body_category_info_on') === 'on',
            'includeCategoryFields' => config('entry_body_category_field_on') === 'on',
            'includeUser' => config('entry_body_user_info_on') === 'on',
            'includeUserFields' => config('entry_body_user_field_on') === 'on',
            'includeBlog' => config('entry_body_blog_info_on') === 'on',
            'includeBlogFields' => config('entry_body_blog_field_on') === 'on',
            // Entry_Body専用
            'image_viewer' => config('entry_body_image_viewer'),
            'includeDatetime' => config('entry_body_date_on') === 'on',
            'includeDetailDatetime' => config('entry_body_detail_date_on') === 'on',
            'includeComment' => config('entry_body_comment_on') === 'on',
            'parentLoopClass' => config('entry_body_parent_loop_class'),
            'loopClass' => config('entry_body_loop_class'),
        ];
    }

    /**
     * Main
     */
    public function get()
    {
        try {
            if (!$this->setConfigTrait()) {
                return '';
            }
            $tpl = new Template($this->tpl, new ACMS_Corrector());
            TplHelper::buildModuleField($tpl);

            // 起動
            $this->boot();

            if ($this->isEntryDetailPage()) {
                // エントリー詳細ページ
                $this->entryPage($tpl);
            } else {
                // エントリー一覧ページ
                $this->entryIndex($tpl);
            }
        } catch (NotFoundException $e) {
            return $this->resultsNotFound($tpl);
        }
        return $tpl->get();
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
     *
     * @param Template $tpl
     * @return void
     * @throws NotFoundException
     */
    protected function entryPage(Template $tpl): void
    {
        if (!is_int($this->eid)) {
            throw new NotFoundException();
        }
        $sql = $this->entryQueryHelper->buildEntryQuery($this->eid, RVID);
        $q = $sql->get(dsn());
        $entry = Database::query($q, 'row');
        if (empty($entry)) {
            throw new NotFoundException();
        }
        $this->entries[] = $entry;
        $eid = (int) $entry['entry_id'];
        $this->entryBodyHelper->setIsMembersOnlyEntry(($entry['entry_members_only'] ?? 'off') === 'on');
        $entry['entry_title'] = $this->entryBodyHelper->getFixTitle($entry['entry_title']);

        $vars = [];
        $rvid = RVID;
        if (!RVID && $entry['entry_approval'] === 'pre_approval') { // @phpstan-ignore-line
            $rvid = 1;
        }
        $allUnitCollection = $this->entryBodyHelper->getAllUnitCollection($eid, $rvid);

        // ユニットを組み立て
        $this->buildEntryUnit($tpl, $entry, $allUnitCollection);
        // 前後リンクを組み立て
        $this->buildSerialNavi($tpl, $entry);
        // マイクロページを組み立て
        $this->buildMicroPage($tpl, $allUnitCollection);
        // Eager Loading
        $this->eagerLoadedData = $this->eagerLoadEntryBody([$entry], $rvid);
        // テンプレートを組み立て
        $this->buildBodyField($tpl, $vars, $entry);
        // 動的フォームを表示・非表示
        $this->buildFormBody($tpl, $entry);
        // エントリー一件を表示
        $tpl->add('entry:loop', $vars);

        $rootVars = [];
        if ($this->config['serialNaviEnabled'] ?? false) {
            $rootVars = array_merge($rootVars, [
                'upperUrl' => acmsLink([
                    'eid' => null,
                ]),
            ]);
        }
        $rootVars = array_merge($rootVars, $this->getRootVars());
        $tpl->add(null, $rootVars);
    }

    /**
     * エントリー一覧ページ
     *
     * @param Template $tpl
     * @return void
     * @throws NotFoundException
     */
    protected function entryIndex(Template $tpl): void
    {
        $rootVars = [];
        // クエリ組み立て
        $sql = $this->entryQueryHelper->buildEntryIndexQuery();
        $q = $sql->get(dsn());
        // エントリ取得
        $this->entries = DB::query($q, 'all');
        foreach ($this->entries as $entry) {
            ACMS_RAM::entry($entry['entry_id'], $entry);
        }
        if (empty($this->entries)) {
            throw new NotFoundException();
        }
        // 次ページが存在するかどうか
        $nextPage = false;
        if (count($this->entries) > $this->config['limit']) {
            array_pop($this->entries);
            $nextPage = true;
        }
        // シンプルページャーの組み立て
        $this->buildSimplePager($tpl, $nextPage);
        // ページネーションの組み立て
        $rootVars += $this->buildPagination($tpl);
        // エントリ一覧組み立て
        $this->buildEntryIndex($tpl);
        // ルート変数を設定
        $rootVars = array_merge($rootVars, $this->getRootVars());
        $tpl->add(null, $rootVars);
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
            'mainImageTarget' => $this->config['mainImageTarget'] ?? 'unit',
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
     * シンプルページャーの組み立て
     * @param Template $tpl
     * @param bool $nextPage
     * @return void
     */
    protected function buildSimplePager(Template $tpl, bool $nextPage): void
    {
        if (!($this->config['simplePagerEnabled'] ?? false)) {
            return;
        }
        $data = $this->entryHelper->buildSimplePager($this->page, $nextPage);
        if ($prevPageLink = ($data['prevPageLink'] ?? false)) {
            $tpl->add('prevPage', [
                'url' => $prevPageLink,
            ]);
        } else {
            $tpl->add('prevPageNotFound');
        }
        if ($nextPageLink = ($data['nextPageLink'] ?? false)) {
            $tpl->add('nextPage', [
                'url' => $nextPageLink,
            ]);
        } else {
            $tpl->add('nextPageNotFound');
        }
    }

    /**
     * ページネーションを組み立て
     * @param Template $tpl
     * @return array
     * @throws Exception
     */
    protected function buildPagination(Template $tpl): array
    {
        $order = $this->config['order'][0] ?? null;
        $countQuery = $this->entryQueryHelper->getCountQuery();
        if (($this->config['paginationEnabled'] ?? false) && $order !== 'random') {
            $total = (int) DB::query($countQuery->get(dsn()), 'one') - (int) $this->config['offset'];
            return TplHelper::buildPager(
                $this->page,
                (int) $this->config['limit'],
                $total,
                $this->config['paginationDelta'] ?? 3,
                $this->config['paginationCurrentAttr'] ?? '',
                $tpl
            );
        }
        return [];
    }

    /**
     * エントリー一覧を組み立て
     * @param Template $tpl
     * @return void
     */
    protected function buildEntryIndex(Template $tpl): void
    {
        $this->eagerLoadedData = $this->eagerLoadEntryBody($this->entries);

        foreach ($this->entries as $i => $entry) {
            $vars = [];
            $serial = ++$i;
            $eid = (int) $entry['entry_id'];
            $entry['entry_title'] = $this->entryBodyHelper->getFixTitle($entry['entry_title']);

            // ユニットを組み立て
            $allUnitCollection = $this->entryBodyHelper->getAllUnitCollection($eid);
            $displayUnitCollection = $this->entryBodyHelper->getDisplayUnitCollection($entry, $allUnitCollection);
            if (count($displayUnitCollection) > 0) {
                $this->entryBodyHelper->buildColumn($displayUnitCollection, $tpl, $eid);
            }
            if (count($allUnitCollection) > count($displayUnitCollection)) {
                $vars['continueName'] = $entry['entry_title'];
                $vars['continueUrl'] = acmsLink([
                    'bid' => $entry['entry_blog_id'],
                    'eid' => $eid,
                ]);
            }
            // エントリーを組み立て
            $this->buildBodyField($tpl, $vars, $entry, $serial);
            $tpl->add('entry:loop', $vars);
        }
    }

    /**
     * 動的フォームの表示・非表示
     *
     * @param Template $tpl
     * @param array $entry
     * @return void
     */
    protected function buildFormBody(Template $tpl, array $entry): void
    {
        if (
            isset($entry['entry_form_id']) &&
            !empty($entry['entry_form_id']) &&
            isset($entry['entry_form_status']) &&
            $entry['entry_form_status'] === 'open' &&
            config('form_edit_action_direct') === 'on'
        ) {
            $tpl->add('formBody');
        }
    }

    /**
     * ユニットを組み立て
     *
     * @param Template $tpl
     * @param array $entry
     * @param \Acms\Services\Unit\UnitCollection $allUnitCollection
     * @return void
     */
    protected function buildEntryUnit(Template $tpl, array $entry, UnitCollection $allUnitCollection): void
    {
        $eid = (int) $entry['entry_id'];
        $summaryRange = strlen($entry['entry_summary_range'] ?? '') ? (int) $entry['entry_summary_range'] : null;
        $publicUnitCollection = $this->entryBodyHelper->getPublicUnitCollection($allUnitCollection, $summaryRange);
        $isMembersOnlyEntry = $this->entryBodyHelper->getIsMembersOnly();

        if ($isMembersOnlyEntry) {
            $tpl->add(['membersOnly', 'entry:loop']);
        }
        if ($isMembersOnlyEntry && $summaryRange !== null && $this->entryBodyHelper->containsMembersOnlyUnitOnMicroPage($allUnitCollection, $summaryRange, $this->page)) {
            // 会員限定ユニットが表示ページに含まれている場合、continueLinkブロックを追加
            $tpl->add(['continueLink', 'entry:loop'], [
                'dummy' => 'dummy',
            ]);
        }
        if (count($publicUnitCollection) > 0) {
            $this->entryBodyHelper->buildColumn($publicUnitCollection, $tpl, $eid);
        } else {
            // ユニットがない場合
            $tpl->add('unit:loop');
        }
    }

    /**
     * 前後リンクを組み立て
     *
     * @param Template $tpl
     * @param array $entry
     * @return void
     */
    protected function buildSerialNavi(Template $tpl, array $entry): void
    {
        if (!($this->config['serialNaviEnabled'] ?? false)) {
            return;
        }
        $data = $this->entryHelper->buildSerialNavi((int) $entry['entry_id'], $this->config['order'][0], $this->config['serialNaviIgnoreCategory'] ?? false, $this->Field);

        if ($prevLink = ($data['prevLink'] ?? false)) {
            $tpl->add('prevLink', [
                'name' => $prevLink['name'],
                'url' => $prevLink['url'],
                'eid' => $prevLink['eid'],
            ]);
        } else {
            $tpl->add('prevNotFound');
        }
        if ($nextLink = ($data['nextLink'] ?? false)) {
            $tpl->add('nextLink', [
                'name' => $nextLink['name'],
                'url' => $nextLink['url'],
                'eid' => $nextLink['eid'],
            ]);
        } else {
            $tpl->add('nextNotFound');
        }
    }

    /**
     * マイクロページネーションを組み立て
     *
     * @param Template $tpl
     * @param \Acms\Services\Unit\UnitCollection $allUnitCollection
     * @return void
     */
    protected function buildMicroPage(Template $tpl, UnitCollection $allUnitCollection): void
    {
        if (!is_int($this->eid)) {
            return;
        }
        if (!($this->config['micropagerEnabled'] ?? false)) {
            return;
        }
        $micropage = $this->page;
        $micropageAmount = $this->entryBodyHelper->countMicroPageAmount($allUnitCollection);
        if ($micropageAmount < 1) {
            return;
        }
        $breakUnit = $this->entryBodyHelper->getBreakUnitOnMicroPage($allUnitCollection, $micropage);
        if ($breakUnit) {
            $linkVars = [];
            $breakUnit->formatMultiLangUnitDataTrait($breakUnit->getField1(), $linkVars, 'label');
            $linkVars['url'] = acmsLink([
                '_inherit' => true,
                'eid' => $this->eid,
                'page' => $micropage + 1,
            ]);
            $tpl->add('micropageLink', $linkVars);
        }

        $vars = [];
        $delta = $this->config['micropagerDelta'] ?? 4;
        $curAttr = $this->config['micropagerCurrentAttr'] ?? '';
        $vars += TplHelper::buildPager($micropage, 1, $micropageAmount, $delta, $curAttr, $tpl, 'micropager');
        $tpl->add('micropager', $vars);
    }

    /**
     * Not Found
     *
     * @param Template $tpl
     * @return string
     */
    protected function resultsNotFound(Template $tpl): string
    {
        $tpl->add('notFound');
        if ($this->config['notfoundStatus404'] === 'on') {
            httpStatusCode('404 Not Found');
        }
        return $tpl->get();
    }

    /**
     * カテゴリーを組み立て
     *
     * @param Template $tpl
     * @param int $cid
     * @param int $bid
     * @return void
     */
    function buildCategory(Template $tpl, int $cid, int $bid): void
    {
        $sql = SQL::newSelect('category');
        $sql->addSelect('category_id');
        $sql->addSelect('category_name');
        $sql->addSelect('category_code');
        $sql->addWhereOpr('category_indexing', 'on');
        ACMS_Filter::categoryTree($sql, $cid, 'ancestor-or-self');
        $sql->addOrder('category_left', 'DESC');
        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');

        $_all = [];
        while ($row = DB::next($statement)) {
            $_all[] = $row;
        }
        switch ($this->config['categoryOrder'] ?? '') {
            case 'child_order':
                break;
            case 'parent_order':
                $_all = array_reverse($_all);
                break;
            case 'current_order':
                $_all = [array_shift($_all)];
                break;
            default:
                break;
        }
        while ($_row = array_shift($_all)) {
            if (!empty($_all[0])) {
                $tpl->add(['glue', 'category:loop']);
            }
            $tpl->add('category:loop', [
                'name' => $_row['category_name'],
                'code' => $_row['category_code'],
                'url' => acmsLink([
                    'bid' => $bid,
                    'cid' => $_row['category_id'],
                ]),
            ]);
            $_all[] = DB::next($statement);
        }
    }

    /**
     * サブカテゴリーを組み立て
     *
     * @param Template $tpl
     * @param int $eid
     * @param int|null $rvid
     * @return void
     */
    function buildSubCategory(Template $tpl, int $eid, ?int $rvid): void
    {
        $subCategories = $this->eagerLoadedData['subCategory'][$eid] ?? [];
        foreach ($subCategories as $i => $category) {
            if ($i !== count($subCategories) - 1) {
                $tpl->add(['glue', 'sub_category:loop']);
            }
            $tpl->add('sub_category:loop', [
                'name' => $category['category_name'],
                'code' => $category['category_code'],
                'url' => acmsLink([
                    'cid' => $category['category_id'],
                ]),
            ]);
        }
    }

    /**
     * コメント件数を組み立て
     *
     * @param int $eid
     * @return array
     */
    function buildCommentAmount(int $eid): array
    {
        $sql = SQL::newSelect('comment');
        $sql->addSelect('*', 'comment_amount', null, 'COUNT');
        $sql->addWhereOpr('comment_entry_id', $eid);
        if (!sessionWithCompilation() && SUID !== ACMS_RAM::entryUser($eid)) {
            $sql->addWhereOpr('comment_status', 'close', '<>');
        }
        return [
            'commentAmount' => intval(DB::query($sql->get(dsn()), 'one')),
            'commentUrl' => acmsLink([
                'eid' => $eid,
            ]),
        ];
    }

    /**
     * 位置情報を組み立て
     *
     * @param int $eid
     * @return array
     */
    protected function buildGeolocation(int $eid): array
    {
        $sql = SQL::newSelect('geo');
        $sql->addSelect('geo_geometry', 'latitude', null, 'ST_Y');
        $sql->addSelect('geo_geometry', 'longitude', null, 'ST_X');
        $sql->addSelect('geo_zoom');
        $sql->addWhereOpr('geo_eid', $eid);

        if ($row = DB::query($sql->get(dsn()), 'row')) {
            return [
                'geo_lat' => $row['latitude'],
                'geo_lng' => $row['longitude'],
                'geo_zoom' => $row['geo_zoom'],
            ];
        }
        return [];
    }

    /**
     * タグを組み立て
     *
     * @param Template $tpl
     * @param int $eid
     * @return void
     */
    protected function buildEntryTag(Template $tpl, int $eid): void
    {
        $tags = $this->eagerLoadedData['tag'][$eid] ?? [];
        foreach ($tags as $i => $tag) {
            if ($i === 0) {
                $tpl->add(['glue', 'tag:loop']);
            }
            $tpl->add('tag:loop', [
                'name' => $tag['tag_name'],
                'url' => acmsLink([
                    'bid' => $tag['tag_blog_id'],
                    'tag' => $tag['tag_name'],
                ]),
            ]);
        }
    }

    /**
     * 編集画面を組み立て
     *
     * @param int $bid
     * @param int $uid
     * @param int|null $cid
     * @param int $eid
     * @param Template $tpl
     * @param string|string[] $block
     * @return void
     */
    protected function buildAdminEntryAction(
        int $bid,
        int $uid,
        ?int $cid,
        int $eid,
        Template $tpl,
        $block = []
    ): void {
        if (!Login::isLoggedIn()) {
            // ユーザーがログインしていない場合は処理を終了
            return;
        }
        $block = empty($block) ? [] : (is_array($block) ? $block : [$block]);

        if (!$this->entryBodyHelper->canEditEntry($bid, $uid, $eid)) {
            // 編集権限がない場合は処理を終了
            return;
        }
        $block = array_merge(['adminEntryAction'], $block);

        $entry = $this->createAdminEntry($eid, $bid, $cid);

        if (!sessionWithApprovalAdministrator() || $entry['status.approval'] !== 'pre_approval') {
            // 最終承認者ではないか、エントリーが承認前でない場合に編集ブロックを追加
            $this->buildEditBlock($tpl, $block, $entry);
        }

        if (BID === $bid) {
            // エントリーのステータスに応じたブロックを追加
            $this->buildStatusBlock($tpl, $block, $eid, $entry);
        }

        // 削除オプションの追加
        if (Entry::canDelete($eid)) {
            $this->buildDeleteBlock($tpl, $block, $entry);
        }

        $tpl->add($block);
    }

    /**
     * エントリー編集用の情報を組み立てる
     *
     * @param int $eid
     * @param int $bid
     * @param int|null $cid
     * @return array
     */
    private function createAdminEntry(int $eid, int $bid, ?int $cid): array
    {
        return [
            'bid' => $bid,
            'cid' => $cid,
            'eid' => $eid,
            'status.approval' => ACMS_RAM::entryApproval($eid),
            'status.title' => ACMS_RAM::entryTitle($eid),
            'status.category' => ACMS_RAM::categoryName($cid),
            'status.url' => acmsLink(['bid' => $bid, 'cid' => $cid, 'eid' => $eid]),
        ];
    }

    /**
     * 編集ブロックの組み立て
     *
     * @param Template $tpl
     * @param array $block
     * @param array $entry
     */
    private function buildEditBlock(Template $tpl, array $block, array $entry): void
    {
        $tpl->add(array_merge(['edit'], $block), $entry);
        $tpl->add(array_merge(['revision'], $block), $entry);
    }

    /**
     * ステータスブロックを組み立て
     *
     * @param Template $tpl
     * @param array $block
     * @param int $eid
     * @param array $entry
     */
    private function buildStatusBlock(Template $tpl, array $block, int $eid, array $entry): void
    {
        // エントリーのステータスに応じてブロックを追加
        $statusBlock = ('open' === ACMS_RAM::entryStatus($eid)) ? 'close' : 'open';
        $tpl->add(array_merge([$statusBlock], $block), $entry);
    }

    /**
     * 削除オプションを追加
     *
     * @param Template $tpl
     * @param array $block
     * @param array $entry
     */
    private function buildDeleteBlock(Template $tpl, array $block, array $entry): void
    {
        $tpl->add(array_merge(['delete'], $block), $entry);
    }

    /**
     * Bodyを組み立て
     *
     * @param Template $tpl
     * @param array $vars
     * @param array $row
     * @param int $serial
     * @return void
     */
    protected function buildBodyField(Template $tpl, array &$vars, array $row, int $serial = 0): void
    {
        $bid = intval($row['entry_blog_id']);
        $uid = intval($row['entry_user_id']);
        $cid = $row['entry_category_id'] ? intval($row['entry_category_id']) : null;
        $eid = intval($row['entry_id']);
        $inheritUrl = acmsLink([
            'bid' => $bid,
            'eid' => $eid,
        ]);
        $permalink = acmsLink([
            'bid' => $bid,
            'cid' => $cid,
            'eid' => $eid,
            'sid' => null,
        ], false);

        $RVID_ = RVID;
        if (!RVID && $row['entry_approval'] === 'pre_approval') { // @phpstan-ignore-line
            $RVID_ = 1;
        }
        if ($serial != 0) {
            if ($serial % 2 == 0) {
                $oddOrEven = 'even';
            } else {
                $oddOrEven = 'odd';
            }
            $vars['iNum'] = $serial;
            $vars['sNum'] = (($this->page - 1) * idval($this->config['limit'])) + $serial;
            $vars['oddOrEven'] = $oddOrEven;
        }
        // build tag
        if ($this->config['includeTags'] ?? false) {
            $this->buildEntryTag($tpl, $eid);
        }
        // build category loop
        if (!empty($cid) && $this->config['includeCategory']) {
            $this->buildCategory($tpl, $cid, $bid);
        }
        // build sub category loop
        if ($this->config['includeCategory']) {
            $this->buildSubCategory($tpl, $eid, $RVID_);
        }
        // build comment/trackbak/geolocation
        if ('on' == config('comment') && $this->config['includeComment']) {
            $vars += $this->buildCommentAmount($eid);
        }
        if ($this->config['geolocationEnabled'] ?? false) {
            $vars += $this->buildGeolocation($eid);
        }
        // build summary
        if ($this->config['fulltextEnabled']) {
            $vars = TplHelper::buildSummaryFulltext($vars, $eid, $this->eagerLoadedData['fullText']);
            $width = $this->config['fulltextWidth'] ?? 0;
            if (isset($vars['summary']) && $width > 0) {
                $marker = $this->config['fulltextMarker'] ?? '';
                $vars['summary'] = mb_strimwidth($vars['summary'], 0, $width, $marker, 'UTF-8');
            }
        }
        // build primary image
        $clid = strval($row['entry_primary_image']);
        if ($this->config['includeMainImage'] ?? false) {
            $config = [
                'imageX' => $this->config['imageX'] ?? 200,
                'imageY' => $this->config['imageX'] ?? 200,
                'imageTrim' => $this->config['imageTrim'] ?? false,
                'imageCenter' => $this->config['imageZoom'] ?? false,
                'imageZoom' => $this->config['imageCenter'] ?? false,
            ];
            $tpl->add('mainImage', TplHelper::buildImage($tpl, $eid, $clid, $config, $this->eagerLoadedData['mainImage']));
        }
        // build related entry
        if ($this->config['includeRelatedEntries'] ?? false) {
            TplHelper::buildRelatedEntriesList($tpl, $eid, $this->eagerLoadedData['relatedEntry'], ['relatedEntry', 'entry:loop']);
        } else {
            $tpl->add(['relatedEntry', 'entry:loop']);
        }
        // admin
        $this->buildAdminEntryAction($bid, $uid, $cid, $eid, $tpl, 'entry:loop');
        // build entry field
        if (($this->config['includeEntryFields'] ?? false) && isset($this->eagerLoadedData['entryField'][$eid])) {
            $vars += TplHelper::buildField($this->eagerLoadedData['entryField'][$eid], $tpl, 'entry:loop', 'entry');
        }
        // build user field
        if ($this->config['includeUser'] ?? false) {
            $Field = new Field();
            if (($this->config['includeUserFields'] ?? false) && isset($this->eagerLoadedData['userField'][$uid])) {
                $Field = $this->eagerLoadedData['userField'][$uid];
            }
            $Field->setField('fieldUserName', ACMS_RAM::userName($uid));
            $Field->setField('fieldUserCode', ACMS_RAM::userCode($uid));
            $Field->setField('fieldUserStatus', ACMS_RAM::userStatus($uid));
            $Field->setField('fieldUserMail', ACMS_RAM::userMail($uid));
            $Field->setField('fieldUserMailMobile', ACMS_RAM::userMailMobile($uid));
            $Field->setField('fieldUserUrl', ACMS_RAM::userUrl($uid));
            $Field->setField('fieldUserIcon', loadUserIcon($uid));
            if ($large = loadUserLargeIcon($uid)) {
                $Field->setField('fieldUserLargeIcon', $large);
            }
            if ($orig = loadUserOriginalIcon($uid)) {
                $Field->setField('fieldUserOrigIcon', $orig);
            }
            $tpl->add('userField', TplHelper::buildField($Field, $tpl));
        }
        // build category field
        if ($cid && $this->config['includeCategory']) {
            $Field = new Field();
            if (($this->config['includeCategoryFields'] ?? false) && isset($this->eagerLoadedData['categoryField'][$cid])) {
                $Field = $this->eagerLoadedData['categoryField'][$cid];
            }
            $Field->setField('fieldCategoryName', ACMS_RAM::categoryName($cid));
            $Field->setField('fieldCategoryCode', ACMS_RAM::categoryCode($cid));
            $Field->setField('fieldCategoryUrl', acmsLink([
                'bid' => $bid,
                'cid' => $cid,
            ]));
            $Field->setField('fieldCategoryId', $cid);
            $tpl->add('categoryField', TplHelper::buildField($Field, $tpl));
        }
        // build blog field
        if ($this->config['includeBlog'] ?? false) {
            $Field = new Field();
            if (($this->config['includeBlogFields'] ?? false) && isset($this->eagerLoadedData['blogField'][$bid])) {
                $Field = $this->eagerLoadedData['blogField'][$bid];
            }
            $Field->setField('fieldBlogName', ACMS_RAM::blogName($bid));
            $Field->setField('fieldBlogCode', ACMS_RAM::blogCode($bid));
            $Field->setField('fieldBlogUrl', acmsLink(['bid' => $bid]));
            $tpl->add('blogField', TplHelper::buildField($Field, $tpl));
        }
        $link = (config('entry_body_link_url') === 'on') ? $row['entry_link'] : '';
        $vars += [
            'status' => $row['entry_status'],
            'titleUrl' => !empty($link) ? $link : $permalink,
            'title' => addPrefixEntryTitle(
                $row['entry_title'],
                $row['entry_status'],
                $row['entry_start_datetime'],
                $row['entry_end_datetime'],
                $row['entry_approval']
            ),
            'inheritUrl' => $inheritUrl,
            'permalink' => $permalink,
            'posterName' => ACMS_RAM::userName($uid),
            'entry:loop.bid' => $bid,
            'entry:loop.uid' => $uid,
            'entry:loop.cid' => $cid,
            'entry:loop.eid' => $eid,
            'entry:loop.bcd' => ACMS_RAM::blogCode($bid),
            'entry:loop.ucd' => ACMS_RAM::userCode($uid),
            'entry:loop.ccd' => ACMS_RAM::categoryCode($cid),
            'entry:loop.ecd' => ACMS_RAM::entryCode($eid),
            'entry:loop.class' => $this->config['loopClass'] ?? '',
            'sort' => $row['entry_sort'],
            'usort' => $row['entry_user_sort'],
            'csort' => $row['entry_category_sort']
        ];
        if (!empty($link)) {
            $vars += [
                'link' => $link,
            ];
        }
        // build date
        if ($this->config['includeDatetime'] ?? false) {
            $vars += TplHelper::buildDate($row['entry_datetime'], $tpl, 'entry:loop');
        }
        if ($this->config['detail_date_on'] ?? false) {
            $vars += TplHelper::buildDate($row['entry_updated_datetime'], $tpl, 'entry:loop', 'udate#');
            $vars += TplHelper::buildDate($row['entry_posted_datetime'], $tpl, 'entry:loop', 'pdate#');
            $vars += TplHelper::buildDate($row['entry_start_datetime'], $tpl, 'entry:loop', 'sdate#');
            $vars += TplHelper::buildDate($row['entry_end_datetime'], $tpl, 'entry:loop', 'edate#');
        }
        // build new
        if (strtotime($row['entry_datetime']) + $this->config['newItemPeriod'] > requestTime()) {
            $tpl->add(['new:touch', 'entry:loop']); // 後方互換
            $tpl->add(['new', 'entry:loop']);
        }
    }

    /**
     * ルート変数を取得
     *
     * @return array
     */
    public function getRootVars(): array
    {
        return [
            'parent.loop.class' => $this->config['parentLoopClass'] ?? '',
        ];
    }

    /**
     * エントリー詳細ページかどうか
     *
     * @return bool
     */
    protected function isEntryDetailPage(): bool
    {
        return strval($this->eid) === strval(intval($this->eid)) && ($this->eid ?? 0) > 0;
    }
}
