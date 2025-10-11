<?php

namespace Acms\Services\Facades;

/**
 * @method static void buildModuleField(\Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, int|null $mid = null, bool $show = false) モジュールフィールドをビルド
 * @method static array<string, string|false> buildDate(int|string $datetime, \Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, string[]|string $block = [], string $prefix = 'date#') 日付をビルド
 * @method static void injectMediaField(\Field $Field, bool $force = false) Fieldにメディアデータを注入
 * @method static void injectRichEditorField(\Field $Field, bool $force = true) Fieldにリッチエディタデータを注入
 * @method static void injectBlockEditorField(\Field $Field, bool $resizeImage = true) Fieldにブロックエディタデータを注入
 * @method static array buildField(\Field $Field, \Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, string[]|string $block = [], string|null $scp = null, array $loop_vars = []) フィールドをビルド
 * @method static array buildPager(int $page, int $limit, int $amount, int $delta, string $curAttr, \Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, string[]|string $block = [], \SQL $Q = null) ページャーをビルド
 * @method static array buildSummaryFulltext(array $vars, int $eid, array<int<1, max>, \Acms\Services\Unit\UnitCollection> $eagerLoadingData) サマリーをビルド
 * @method static void buildTag(\Acms\Services\View\Contracts\ViewInterface|\Template $tpl, int $eid, array $eagerLoadingData, string[] $blocks) タグをビルド
 * @method static bool buildAdminFormColumn(array $data, \Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, string[]|string $block = []) 管理者フォームのカラムをビルド
 * @method static string spreadModule(string $moduleName, string $moduleID, string $moduleTpl, bool $onlyLayout = false) モジュールを展開
 * @method static array<int<1, max>, \Acms\Services\Unit\UnitCollection> eagerLoadFullText(int[] $entryIds) ユニットのEagerLoading
 * @method static array eagerLoadTag(int[] $eidArray) タグのEagerLoading
 * @method static array eagerLoadRelatedEntry(int[] $eidArray) 関連エントリのEagerLoading
 * @method static array eagerLoadMainImage(array $entries, string|null $target = 'unit', string|null $fieldName = '') メイン画像のEagerLoading
 * @method static array buildImage(\Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, int $entryId, string $pimageId, array $config, array{unit: array<string, \Acms\Services\Unit\Contracts\Model>, media: array<int, array<string, mixed>>, fieldMainImage?: array<int, array<string, mixed>>} $eagerLoadingData) 画像をビルド
 * @method static void buildRelatedEntries(\Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, int[] $eids, string[]|string $block, string $start, string $end, string $relatedBlock = 'related:loop', string|null $thumbnailField = '') 関連エントリをビルド
 * @method static void buildRelatedEntriesList(\Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, int $eid, array<int, array<string, array<array>>> $eagerLoadingData, string[]|string $block = []) 関連エントリのリストをビルド
 * @method static void buildSummary(\Acms\Services\View\Contracts\ViewInterface|\Template $Tpl, array $row, int $count, string $gluePoint, array $config, array $extraVars = [], int $page = 1, array $eagerLoadingData = []) サマリーをビルド
 */
class Template extends Facade
{
    protected static $instance;

    /**
     * @return string
     */
    protected static function getServiceAlias()
    {
        return 'template.acms.helper';
    }

    /**
     * @return bool
     */
    protected static function isCache()
    {
        return true;
    }
}
