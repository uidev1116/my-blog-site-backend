<?php

namespace Acms\Services\Unit\Rendering;

use Acms\Services\Unit\Constants\UnitAlign;
use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Application;
use Acms\Services\Unit\UnitCollection;
use Acms\Services\Unit\UnitTreeNode;
use Acms\Services\Unit\UnitTree;
use Template;
use ACMS_RAM;
use Exception;

class Front
{
    /**
     * ユニットのルートブロック
     * @var string[]
     */
    protected $rootBlock = ['unit:loop'];

    /**
     * ユニットの描画
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param Template $tpl
     * @param int $eid
     * @return void
     */
    public function render(UnitCollection $collection, Template $tpl, int $eid): void
    {
        $entry = ACMS_RAM::entry($eid);
        if (is_null($entry)) {
            return;
        }

        // データの整理
        if (!$this->canDisplayInvisibleUnit(BID, $entry)) {
            // 非表示ユニットを表示しない場合
            $collection = $collection->filter(function (Model $unit) {
                return !$unit->isHidden();
            })->normalize();
        }

        // 変数の初期化
        $unitGroupEnable = $this->isUnitGroupEnabled($collection);
        $shouldRenderDirectEdit = $this->shouldRenderDirectEdit();
        $preAlign = null;


        // 表示に必要なデータを取得
        $unitRepository = Application::make('unit-repository');
        assert($unitRepository instanceof \Acms\Services\Unit\Repository);
        $unitRepository->eagerLoadCustomUnitFields($collection);
        $eagerLoadedMedia = Media::mediaEagerLoadFromUnit($collection);

        // 表示に必要なデータをセットする
        $collection->walk(function (Model $unit) use ($eagerLoadedMedia) {
            if ($unit instanceof \Acms\Services\Unit\Contracts\EagerLoadingMedia) {
                $unit->setEagerLoadedMedia($eagerLoadedMedia);
            }
        });

        $this->renderTree($collection->tree(), $tpl, [
            'unitGroupEnable' => $unitGroupEnable,
            'shouldRenderDirectEdit' => $shouldRenderDirectEdit,
            'preAlign' => $preAlign,
        ]);
    }

    /**
     * ツリーを描画
     *
     * @param UnitTree|UnitTreeNode $tree
     * @param Template $tpl
     * @param array{
     *    unitGroupEnable?: bool,
     *    shouldRenderDirectEdit?: bool,
     *    preAlign?: UnitAlign|null,
     * } $options
     * @return void
     */
    private function renderTree(
        UnitTree|UnitTreeNode $tree,
        Template $tpl,
        array $options = []
    ): void {
        $defaultOptions = [
            'unitGroupEnable' => false,
            'shouldRenderDirectEdit' => false,
            'preAlign' => null,
        ];
        /**
         * @var array{
         *  unitGroupEnable: bool,
         *  shouldRenderDirectEdit: bool,
         *  preAlign: UnitAlign|null,
         * } $config
         */
        $config = array_merge($defaultOptions, $options);

        $nodes = $tree instanceof UnitTree ? $tree->getRoots() : $tree->children;

        $nodeCount = count($nodes);
        $currentGroup = null;

        // 開始タグの追加
        if ($tree instanceof UnitTreeNode && $tree->unit instanceof \Acms\Services\Unit\Contracts\ParentUnit) {
            $tpl->add(array_merge(['tree#front'], $this->rootBlock));
            $tree->unit->render($tpl, [
                'utid' => $tree->unit->getId(),
                'unit_eid' => $tree->unit->getEntryId(),
            ], array_merge(['tree#front'], $this->rootBlock));
        }
        $tpl->add($this->rootBlock);

        foreach ($nodes as $i => $node) {
            if (
                $node->unit instanceof \Acms\Services\Unit\Contracts\ParentUnit
            ) {
                if (count($node->children) > 0) {
                    $this->renderTree($node, $tpl, $config);
                    $tpl->add($this->rootBlock);
                }
                continue;
            }

            if ($config['unitGroupEnable']) {
                // グループ開始
                $currentGroup = $this->renderGroup($tpl, $node->unit->getGroup(), $currentGroup);
            }

            // ダイレクト編集
            // renderClear で $unit->getAlign() が変更されるため、renderClear を実行する前である必要がある
            if ($config['shouldRenderDirectEdit']) {
                $this->renderDirectEdit($tpl, $node->unit);
            }
            // クリア・アライン
            if (config('unit_align_version', 'v2') === 'v1') {
                // ※ $unit->align が変更される可能性があるので注意
                $this->renderClear($tpl, $node->unit, $config['preAlign']);
            }
            // ユニット独自の描画
            $node->unit->render($tpl, [
                'utid' => $node->unit->getId(),
                'unit_eid' => $node->unit->getEntryId(),
                'unitGroupEnable' => $config['unitGroupEnable'] ? 'on' : 'off',
            ], $this->rootBlock);

            // 同一親ノードの最後のユニットの場合
            if (($nodeCount - 1) === $i) {
                if ($config['unitGroupEnable'] && $currentGroup !== null) {
                    $tpl->add(array_merge(['unitGroup#last'], $this->rootBlock));
                }
            }
            $tpl->add($this->rootBlock);
        }

        // 終了タグの追加
        if ($tree instanceof UnitTreeNode && $tree->unit instanceof \Acms\Services\Unit\Contracts\ParentUnit) {
            $tpl->add(array_merge(['tree#rear'], $this->rootBlock));
            $tree->unit->render($tpl, [
                'utid' => $tree->unit->getId(),
                'unit_eid' => $tree->unit->getEntryId(),
            ], array_merge(['tree#rear'], $this->rootBlock));
        }
        $tpl->add($this->rootBlock);
    }

    /**
     * サマリーを組み立て
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @return string[]
     */
    public function renderSummaryText(UnitCollection $collection): array
    {
        // データの整理
        $collection = $collection->filter(function (Model $unit) {
            return !$unit->isHidden();
        })->normalize();
        $textData = [];
        $collection->walkTree(function (UnitTreeNode $node) use (&$textData) {
            $data = $node->unit->getSummaryText();
            foreach ($data as $i => $txt) {
                if (isset($textData[$i])) {
                    $textData[$i] .= "{$txt} ";
                } else {
                    $textData[] = "{$txt} ";
                }
            }
        });
        $textData = array_map(function ($txt) {
            // HTMLタグを除去し、複数の空白や改行をまとめて半角スペース1つにする
            return trim(preg_replace('@\s+@u', ' ', strip_tags($txt)) ?? '');
        }, $textData);
        return $textData;
    }

    /**
     * ユニットグループを描画
     *
     * @deprecated ユニットグループは非推奨です。グループユニットを使用してください。
     * @param Template $tpl
     * @param string $group
     * @param string|null $currentGroup
     * @return ?string
     */
    protected function renderGroup(Template $tpl, string $group, ?string $currentGroup): ?string
    {
        if ($group === '') {
            return $currentGroup;
        }
        $class = $group;
        $count = 0;

        // close rear
        if (!!$currentGroup) {
            $tpl->add(array_merge(['unitGroup#rear'], $this->rootBlock));
        }
        // open front
        $grVars = ['class' => $class];
        if ($currentGroup === $class) {
            $count += 1;
            $grVars['i'] = $count;
        } else {
            $count = 1;
            $grVars['i'] = $count;
        }

        if ($class === config('unit_group_clear', 'acms-column-clear')) {
            $currentGroup = null;
        } else {
            $tpl->add(array_merge(['unitGroup#front'], $this->rootBlock), $grVars);
            $currentGroup = $class;
        }
        return $currentGroup;
    }

    /**
     * Clearの描画
     *
     * @deprecated 配置v1で利用されている機能です。配置v1以外では使用しないでください。
     * @param Template $tpl
     * @param Model $unit
     * @param UnitAlign|null $preAlign 1つ前のユニットの配置
     * @return void
     */
    protected function renderClear(Template $tpl, Model $unit, ?UnitAlign &$preAlign): void
    {
        if (!($unit instanceof \Acms\Services\Unit\Contracts\AlignableUnitInterface)) {
            // 配置を設定できないユニットの場合は強制的にclearブロックを追加する
            $tpl->add(array_merge(['clear'], $this->rootBlock));
            return;
        }
        $align = $unit->getAlign();
        (function () use ($align, $preAlign, $unit, $tpl) {
            if ($preAlign === null) {
                // 1つ前のユニットの配置が未設定の場合は何もしない
                return;
            };
            if ($align === UnitAlign::LEFT && $preAlign === UnitAlign::LEFT) {
                // 配置が左の場合は何もしない
                return;
            };
            if ($align === UnitAlign::RIGHT && $preAlign === UnitAlign::RIGHT) {
                // 配置が右の場合は何もしない
                return;
            }
            if ($align === UnitAlign::AUTO) {
                // 配置がautoの場合
                if ($preAlign === UnitAlign::LEFT) {
                    // 1つ前のユニットの配置が左の場合は何もしない
                    return;
                }
                if ($preAlign === UnitAlign::RIGHT) {
                    // 1つ前のユニットの配置が右の場合は何もしない
                    return;
                }
                if ($preAlign === UnitAlign::AUTO && $unit instanceof \Acms\Services\Unit\Models\Text) {
                    // 1つ前のユニットの配置がautoかつテキストユニットの場合は何もしない
                    return;
                }
            }

            // 以下の条件に当てはまる場合はclearブロックを追加する
            // 1. 配置が center
            // 2. 配置が left かつ 1つ前のユニットの配置が left ではない
            // 3. 配置が right かつ 1つ前のユニットの配置が right ではない
            // 4. 配置が auto かつ 1つ前のユニットの配置が auto に設定されたテキストユニットではない
            $tpl->add(array_merge(['clear'], $this->rootBlock));
        })();

        if ($align === UnitAlign::AUTO && !($unit instanceof \Acms\Services\Unit\Models\Text)) {
            // 配置がautoの場合は1つ前のユニットの配置を現在のユニットに設定する
            // これにより、配置を 左・おまかせ・おまかせのように設定すると横並びにする事が可能
            $unit->setAlign($preAlign ?? UnitAlign::AUTO);
        }
        $preAlign = $align;
    }

    /**
     * ダイレクト編集のためのブロック・変数を描画する
     * @deprecated ダイレクト編集は非推奨です。将来的に廃止を検討しています。
     * @param Template $tpl
     * @param Model $unit
     * @return void
     * @throws Exception
     */
    protected function renderDirectEdit(Template $tpl, Model $unit): void
    {
        $vars = [];
        $vars['unit:loop.type'] = $unit::getUnitType();
        $vars['unit:loop.utid'] = $unit->getId();
        $vars['unit:loop.unit_eid'] = $unit->getEntryId();
        $vars['unit:loop.sort'] = $unit->getSort();
        if ($unit instanceof \Acms\Services\Unit\Contracts\AlignableUnitInterface) {
            $vars['unit:loop.align'] = $unit->getAlign()->value;
        }
        $vars['unit:loop.status'] = $unit->getStatus()->value;
        $tpl->add(array_merge(['inplace#front'], $this->rootBlock), $vars);
        $tpl->add(array_merge(['inplace#rear'], $this->rootBlock));
    }

    /**
     * ダイレクト編集のためのブロック・変数を描画するかどうか
     * @deprecated ダイレクト編集は非推奨です。将来的に廃止を検討しています。
     * @return bool
     */
    protected function shouldRenderDirectEdit(): bool
    {
        return Entry::isDirectEditEnabled();
    }

    /**
     * 非表示ユニットを表示するかどうか
     *
     * @param int $bid
     * @param array $entry
     * @return bool
     */
    protected function canDisplayInvisibleUnit(int $bid, array $entry): bool
    {
        // 基本的な権限チェック
        if (!sessionWithContribution($bid)) {
            // 投稿者以上の権限がない場合は非表示ユニットを表示しない
            return false;
        }

        if (!roleEntryUpdateAuthorization($bid, $entry)) {
            // エントリ編集権限がない場合は非表示ユニットを表示しない
            return false;
        }

        // インプレース編集機能の設定チェック
        if (config('entry_edit_inplace_enable') !== 'on') {
            // インプレース編集機能が無効な場合は非表示ユニットを表示しない
            return false;
        }

        if (config('entry_edit_inplace') !== 'on') {
            // インプレース編集機能が無効な場合は非表示ユニットを表示しない
            return false;
        }

        // 承認機能関連のチェック
        if (enableApproval() && !sessionWithApprovalAdministrator()) {
            // 承認機能が有効かつ承認者でない場合は非表示ユニットを表示しない
            return false;
        }

        // エントリ状態のチェック
        if ($entry['entry_approval'] === 'pre_approval') {
            // 承認待ちのエントリの場合は非表示ユニットを表示しない
            return false;
        }

        // 表示画面のチェック
        /** @var 'entry' | 'index' | 'top' | null $view */
        $view = defined('VIEW') ? VIEW : null;
        if ($view !== 'entry') {
            // 詳細ページでない場合は非表示ユニットを表示しない
            return false;
        }

        return true;
    }

    /**
     * ユニットグループの有効無効を判定
     *
     * @param UnitCollection $collection
     * @return bool
     */
    protected function isUnitGroupEnabled(UnitCollection $collection): bool
    {
        if (config('unit_group') !== 'on') {
            return false;
        }
        $isEnabled = true;
        $collection->walkTree(function (UnitTreeNode $node) use (&$isEnabled) {
            if (count($node->children) > 0) {
                // tree構造を表現するユニットが存在する場合はユニットグループは無効
                // （tree構造とユニットグループは同時に利用できない）
                $isEnabled = false;
            }
        });
        return $isEnabled;
    }
}
