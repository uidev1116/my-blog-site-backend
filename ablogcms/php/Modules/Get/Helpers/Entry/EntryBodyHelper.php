<?php

namespace Acms\Modules\Get\Helpers\Entry;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Preview;
use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\UnitCollection;
use Exception;
use Template;

class EntryBodyHelper extends BaseHelper
{
    use \Acms\Traits\Utilities\PaginationTrait;

    /**
     * 会員限定記事
     *
     * @var bool
     */
    private $isMembersOnlyEntry = false;

    /**
     * 会員限定記事かどうかを取得
     *
     * @return boolean
     */
    public function getIsMembersOnly(): bool
    {
        return $this->isMembersOnlyEntry;
    }

    /**
     * 会員限定記事かどうかを設定
     *
     * @param bool $isMembersOnly
     * @return void
     */
    public function setIsMembersOnlyEntry(bool $isMembersOnly): void
    {
        $this->isMembersOnlyEntry = $isMembersOnly;
    }

    /**
     * 修正したエントリータイトルを取得
     *
     * @param string $title
     * @return string
     */
    public function getFixTitle(string $title): string
    {
        if (!IS_LICENSED) {
            return '[test]' . $title;
        }
        return $title;
    }

    /**
     * ユニットを取得
     * @param int $eid
     * @param int | null $rvid
     * @return \Acms\Services\Unit\UnitCollection
     * @throws Exception
     */
    public function getAllUnitCollection(int $eid, ?int $rvid = null): UnitCollection
    {
        /** @var \Acms\Services\Unit\Repository $unitService */
        $unitService = Application::make('unit-repository');
        return $unitService->loadUnits($eid, $rvid);
    }

    /**
     * 一覧で表示するユニットを取得
     * @param array $entry
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @return \Acms\Services\Unit\UnitCollection
     */
    public function getDisplayUnitCollection(array $entry, UnitCollection $collection): UnitCollection
    {
        $summaryRange = (int) ($this->config['fixedSummaryRange'] ?? 0);
        if (!$summaryRange) {
            $summaryRange = (int) ($entry['entry_summary_range'] ?? 0);
        }
        if ($this->config['displayAllUnits'] ?? false) {
            $summaryRange = 0;
        }
        if (count($collection) > 0 && $summaryRange > 0) {
            $collection = $collection->slice(0, $summaryRange);
        }
        return $collection;
    }

    /**
     * 公開ユニットのみ取得
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param int | null $summaryRange
     * @return \Acms\Services\Unit\UnitCollection
     */
    public function getPublicUnitCollection(UnitCollection $collection, ?int $summaryRange): UnitCollection
    {
        if ($this->isMembersOnlyEntry && $summaryRange !== null) {
            // 会員限定ユニットを除外
            $collection = $collection->slice(0, $summaryRange);
        }
        if (($this->config['micropagerEnabled'] ?? false) && $this->page > 0) {
            $collection = $this->filterUnitsByMicroPage($collection, $this->page);
        }
        return $collection;
    }

    /**
     * ユニットの描画
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param Template $tpl
     * @param int $eid
     * @return void
     */
    public function buildColumn(UnitCollection $collection, Template $tpl, int $eid): void
    {
        /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
        $unitRenderingService = Application::make('unit-rendering-front');
        $unitRenderingService->render($collection, $tpl, $eid);
    }

    /**
     * 指定したマイクロページで会員限定ユニットが含まれているかどうか
     * @param \Acms\Services\Unit\UnitCollection $collection エントリーが持つ全てのユニットを含む配列
     * @param int $summaryRange
     * @param int $micropage
     * @return bool
     */
    public function containsMembersOnlyUnitOnMicroPage(UnitCollection $collection, int $summaryRange, int $micropage): bool
    {
        if ($summaryRange >= count($collection)) {
            // 会員限定記事のバーが最後の場合は、会員限定ユニットは含まれない（ページ内のユニットはすべて公開ユニット）
            return false;
        }

        // 公開ユニット内の合計ページ数を取得
        $publicPageAmount = 1;
        foreach ($collection->tree()->getRoots() as $i => $node) {
            // 改ページユニットはルート階層にしか設置できないため、ルート階層のユニットのみを対象にカウントする
            if ($node->unit::getUnitType() === 'break' && $i < $summaryRange) {
                // 会員限定記事のバーより前のユニット（= 公開ユニット）の場合のみカウント
                $publicPageAmount += 1;
            }
        }
        // 公開ユニット内の合計ページ数が表示ページより大きい場合は、会員限定ユニットは含まれない（ページ内のユニットはすべて公開ユニット）
        if ($publicPageAmount > $micropage) {
            return false;
        }

        return true;
    }

    /**
     * マイクロページネーションを組み立て
     * @param \Acms\Services\Unit\UnitCollection $allUnitCollection
     * @return null|array
     */
    public function buildMicroPagination(UnitCollection $allUnitCollection): ?array
    {
        $micropage = $this->page;
        $micropageAmount = $this->countMicroPageAmount($allUnitCollection);
        if ($micropageAmount < 1) {
            return null;
        }
        $maxPages = $this->config['micropagerDelta'] ?? 4;

        return $this->buildPaginationTrait($micropage, $micropageAmount, 1, $maxPages);
    }

    /**
     * マイクロページの総ページ数をカウント
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @return int
     */
    public function countMicroPageAmount(UnitCollection $collection): int
    {
        $page = 1;
        foreach ($collection->flat() as $unit) {
            if ($unit::getUnitType() === 'break') {
                $page += 1;
            }
        }
        return $page;
    }

    /**
     * 指定したマイクロページを分割する改ページユニットを取得
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param int<1, max> $micropage マイクロページ番号
     * @return \Acms\Services\Unit\Models\NewPage | null
     */
    public function getBreakUnitOnMicroPage(UnitCollection $collection, int $micropage): ?Model
    {
        $breakUnitCollection = $collection->filter(
            function ($unit) {
                return $unit instanceof \Acms\Services\Unit\Models\NewPage;
            }
        );
        $micropageCount = 1;
        foreach ($breakUnitCollection->flat() as $breakUnit) {
            /** @var \Acms\Services\Unit\Models\NewPage $breakUnit */
            if ($micropageCount === $micropage) {
                return $breakUnit;
            }
            $micropageCount += 1;
        }
        return null;
    }

    /**
     * 指定したマイクロページに表示するユニットで絞り込んで取得
     *
     * @param \Acms\Services\Unit\UnitCollection $collection
     * @param int<1, max> $micropage マイクロページ番号
     * @return \Acms\Services\Unit\UnitCollection
     */
    public function filterUnitsByMicroPage(UnitCollection $collection, int $micropage): UnitCollection
    {
        $filteredUnits = [];
        $micropageCount = 1;
        foreach ($collection->flat() as $unit) {
            if ($unit::getUnitType() === 'break') {
                $micropageCount += 1;
            }
            if ($micropageCount === $micropage) {
                $filteredUnits[] = $unit;
            }
            if ($micropageCount > $micropage) {
                break;
            }
        }
        return new UnitCollection($filteredUnits);
    }

    /**
     * エントリーの編集権限があるかを判定
     *
     * @param int $bid
     * @param int $uid
     * @param int $eid
     * @return bool
     */
    public function canEditEntry(int $bid, int $uid, int $eid): bool
    {
        if (Preview::isPreviewMode()) {
            return false;
        }
        if (timemachineMode()) {
            return false;
        }

        if (defined('LAYOUT_EDIT') && LAYOUT_EDIT === 'ON') {
            return false;
        }
        // ロール機能を利用している場合
        if (roleAvailableUser()) {
            // 全エントリーの編集権限があるか、自分のエントリー編集権限がある場合
            return roleAuthorization('entry_edit_all', $bid) || (roleAuthorization('entry_edit', $bid, $eid));
        }

        // 承認機能が有効で、かつ投稿者が自分のエントリーのみ編集可能な設定が無効な場合
        if (config('approval_contributor_edit_auth') !== 'on' && enableApproval()) {
            return true;
        }

        // 編集者権限がある場合
        if (sessionWithCompilation()) {
            return true;
        }

        // 投稿者権限があり、自分のエントリーの場合
        if (sessionWithContribution() && $uid === SUID) { // @phpstan-ignore-line
            return true;
        }

        // それ以外は編集権限なし
        return false;
    }
}
