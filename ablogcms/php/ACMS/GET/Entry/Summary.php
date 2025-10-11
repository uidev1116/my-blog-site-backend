<?php

use Acms\Services\Facades\Database;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Modules\Get\Helpers\Entry\EntryQueryHelper;
use Acms\Modules\Get\Helpers\Entry\EntryHelper;

class ACMS_GET_Entry_Summary extends ACMS_GET_Entry
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    public $_axis = [ // phpcs:ignore
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
     * @var array
     */
    protected $entries = [];

    /**
     * @var SQL_Select|null
     */
    protected $countSql = null;

    /**
     * コンフィグの取得
     *
     * @return array<string, mixed>
     */
    public function initConfig(): array
    {
        return [
            'order' => [
                $this->order ? $this->order : config('entry_summary_order'),
                config('entry_summary_order2'),
            ],
            'orderFieldName' => config('entry_summary_order_field_name'),
            'noNarrowDownSort' => config('entry_summary_no_narrow_down_sort') === 'on',
            'limit' => (int) config('entry_summary_limit'),
            'offset' => (int) config('entry_summary_offset'),
            'unit' => (int) config('entry_summary_unit'),
            'parentLoopClass' => config('entry_summary_parent_loop_class'),
            'loopClass' => config('entry_summary_loop_class'),
            'newItemPeriod' => (int) config('entry_summary_newtime'),
            'displayIndexingOnly' => config('entry_summary_indexing') === 'on',
            'displayMembersOnly' => config('entry_summary_members_only') === 'on',
            'displaySubcategoryEntries' => config('entry_summary_sub_category') === 'on',
            'displaySecretEntry' => config('entry_summary_secret') === 'on',
            'dateOn' => config('entry_summary_base_date', 'on') === 'on',
            'detailDateOn' => config('entry_summary_date') === 'on',
            'notfoundBlock' => config('mo_entry_summary_notfound') === 'on',
            'notfoundStatus404' => config('entry_summary_notfound_status_404') === 'on',
            'fulltextEnabled' => config('entry_summary_fulltext') === 'on',
            'fulltextWidth' => (int) config('entry_summary_fulltext_width'),
            'fulltextMarker' => config('entry_summary_fulltext_marker'),
            'includeTags' => config('entry_summary_tag') === 'on',
            'hiddenCurrentEntry' => config('entry_summary_hidden_current_entry') === 'on',
            'hiddenPrivateEntry' => config('entry_summary_hidden_private_entry') === 'on',
            'includeRelatedEntries' => config('entry_summary_related_entry_on') === 'on',
            // 画像系
            'includeMainImage' => config('entry_summary_image_on') === 'on',
            'mainImageTarget' => config('entry_summary_main_image_target', 'unit'),
            'mainImageFieldName' => config('entry_summary_main_image_field_name'),
            'displayNoImageEntry' => config('entry_summary_noimage') === 'on',
            'imageX' => (int) config('entry_summary_image_x', 200),
            'imageY' => (int) config('entry_summary_image_y', 200),
            'imageTrim' => config('entry_summary_image_trim') === 'on',
            'imageZoom' => config('entry_summary_image_zoom') === 'on',
            'imageCenter' => config('entry_summary_image_center') === 'on',
            // ページネーション
            'simplePagerEnabled' => config('entry_summary_simple_pager_on') === 'on',
            'paginationEnabled' => config('entry_summary_pager_on') === 'on',
            'paginationDelta' => (int) config('entry_summary_pager_delta', 4),
            'paginationCurrentAttr' => config('entry_summary_pager_cur_attr'),
            // フィールド・情報
            'includeEntryFields' => config('entry_summary_entry_field') === 'on',
            'includeCategory' => config('entry_summary_category_on') === 'on',
            'includeCategoryFields' => config('entry_summary_category_field_on') === 'on',
            'includeUser' => config('entry_summary_user_on') === 'on',
            'includeUserFields' => config('entry_summary_user_field_on') === 'on',
            'includeBlog' => config('entry_summary_blog_on') === 'on',
            'includeBlogFields' => config('entry_summary_blog_field_on') === 'on',
            // 表示モード
            'relatedEntryMode' => config('entry_summary_relational') === 'on',
            'relatedEntryType' => config('entry_summary_relational_type'),
        ];
    }

    /**
     * 起動
     *
     * @return string
     */
    public function get()
    {
        if (!$this->setConfigTrait()) {
            return '';
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);

        $this->boot();
        $sql = $this->buildQuery();
        $this->countSql = $this->buildCountQuery();
        $this->entries = $this->getEntries($sql);
        [$isRunnable, $renderTpl] = $this->preBuild($tpl);
        if (!$isRunnable) {
            if ($renderTpl) {
                return $tpl->get();
            } else {
                return '';
            }
        }
        if ($this->buildNotFound($tpl)) {
            return $tpl->get();
        }
        $hasNextPage = false;
        if (count($this->entries) > $this->config['limit']) {
            array_pop($this->entries);
            $hasNextPage = true;
        }
        $this->buildEntries($tpl);
        if ($this->buildNotFound($tpl)) {
            return $tpl->get();
        }
        if (!$this->entries) {
            return '';
        }
        $this->buildSimplePager($tpl, $hasNextPage);
        $vars = $this->getRootVars();
        $vars += $this->buildFullspecPager($tpl);
        $tpl->add(null, $vars);

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
     * エントリーの取得
     *
     * @param SQL_Select $sql
     * @return array
     */
    protected function getEntries(SQL_Select $sql): array
    {
        $q = $sql->get(dsn());
        $entries = Database::query($q, 'all');
        foreach ($entries as $entry) {
            ACMS_RAM::entry($entry['entry_id'], $entry);
        }
        return $entries;
    }

    /**
     * 件数取得用のクエリを組み立て
     *
     * @return SQL_Select
     */
    protected function buildCountQuery(): SQL_Select
    {
        return $this->entryQueryHelper->getCountQuery();
    }

    /**
     * ビルド前のカスタム処理
     *
     * @param Template $tpl
     * @return array{0: bool, 1: bool} 0: 処理を続けるかどうか, 1: ここまでの処理をレンダリングするかどうか
     */
    protected function preBuild(Template $tpl): array
    {
        return [true, true];
    }

    /**
     * テンプレートの組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildEntries($tpl)
    {
        $gluePoint = count($this->entries);
        $eagerLoad = $this->entryHelper->eagerLoad($this->entries, [
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
        ]);
        foreach ($this->entries as $i => $row) {
            $i++;
            TemplateHelper::buildSummary($tpl, $row, $i, $gluePoint, [
                'limit' => (int) $this->config['limit'],
                'unit' => (int) $this->config['unit'],
                'loop_class' => $this->config['loopClass'] ?? '',
                'newtime' => (int) ($this->config['newItemPeriod'] ?? 0),
                'fulltextWidth' => (int) ($this->config['fulltextWidth'] ?? 0),
                'fulltextMarker' => $this->config['fulltextMarker'] ?? '',
                'dateOn' => ($this->config['dateOn'] ?? true) ? 'on' : 'off',
                'detailDateOn' => ($this->config['detailDateOn'] ?? false) ? 'on' : 'off',
                'blogInfoOn' => ($this->config['includeBlog'] ?? false) ? 'on' : 'off',
                'blogFieldOn' => ($this->config['includeBlogFields'] ?? false) ? 'on' : 'off',
                'categoryInfoOn' => ($this->config['includeCategory'] ?? false) ? 'on' : 'off',
                'categoryFieldOn' => ($this->config['includeCategoryFields'] ?? false) ? 'on' : 'off',
                'userInfoOn' => ($this->config['includeUser'] ?? false) ? 'on' : 'off',
                'userFieldOn' => ($this->config['includeUserFields'] ?? false) ? 'on' : 'off',
                'imageX' => (int) ($this->config['imageX'] ?? 200),
                'imageY' => (int) ($this->config['imageY'] ?? 200),
                'imageTrim' => ($this->config['imageTrim'] ?? false) ? 'on' : 'off',
                'imageCenter' => ($this->config['imageCenter'] ?? false) ? 'on' : 'off',
                'imageZoom' => ($this->config['imageZoom'] ?? false) ? 'on' : 'off',
            ], [], $this->page, $eagerLoad);
        }
    }

    /**
     * NotFound時のテンプレート組み立て
     *
     * @param Template $tpl
     * @return bool
     */
    public function buildNotFound($tpl)
    {
        if (!empty($this->entries)) {
            return false;
        }
        if (!($this->config['notfoundBlock'] ?? false)) {
            return false;
        }
        $tpl->add('notFound');
        $tpl->add(null, $this->entryHelper->getRootVars());
        if ($this->config['notfoundStatus404'] ?? false) {
            httpStatusCode('404 Not Found');
        }
        return true;
    }

    /**
     * シンプルページャーの組み立て
     *
     * @param Template $tpl
     * @param bool $hasNextPage
     * @return void
     */
    protected function buildSimplePager(Template $tpl, bool $hasNextPage)
    {
        if (!($this->config['simplePagerEnabled'] ?? false)) {
            return;
        }
        // prev page
        if ($this->page > 1) {
            $tpl->add('prevPage', [
                'url' => acmsLink([
                    'page' => $this->page - 1,
                ], true),
            ]);
        } else {
            $tpl->add('prevPageNotFound');
        }
        // next page
        if ($hasNextPage) {
            $tpl->add('nextPage', [
                'url' => acmsLink([
                    'page' => $this->page + 1,
                ], true),
            ]);
        } else {
            $tpl->add('nextPageNotFound');
        }
    }

    /**
     * フルスペックページャーの組み立て
     *
     * @param Template $tpl
     * @return array
     */
    public function buildFullspecPager($tpl)
    {
        $vars = [];
        if (isset($this->config['order'][0]) && 'random' === $this->config['order'][0]) {
            return $vars;
        }
        if (!($this->config['paginationEnabled'] ?? false)) {
            return $vars;
        }
        if ($this->countSql) {
            $itemsAmount = intval(DB::query($this->countSql->get(dsn()), 'one'));
            $itemsAmount -= $this->config['offset'];
            $vars += TemplateHelper::buildPager($this->page, $this->config['limit'], $itemsAmount, $this->config['paginationDelta'], $this->config['paginationCurrentAttr'], $tpl);
        }
        return $vars;
    }

    /**
     * ルート変数を取得
     *
     * @return array
     */
    public function getRootVars(): array
    {
        return array_merge($this->entryHelper->getRootVars(), [
            'parent.loop.class' => $this->config['parentLoopClass'] ?? '',
        ]);
    }
}
