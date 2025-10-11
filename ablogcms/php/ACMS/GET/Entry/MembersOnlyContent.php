<?php

use Acms\Services\Facades\Application;
use Acms\Services\Unit\UnitCollection;

class ACMS_GET_Entry_MembersOnlyContent extends ACMS_GET_Entry
{
    public $_scope = [
        'page' => 'global',
    ];

    /**
     * Main
     */
    public function get()
    {
        try {
            $this->validate();
            $tpl = new Template($this->tpl, new ACMS_Corrector());
            $entry = $this->getEntry(EID, RVID);
            $summaryRange = $this->getSummaryRange($entry);
            /** @var int<1, max> $entryId */
            $entryId = EID; // @phpstan-ignore-line varTag.nativeType
            /** @var int<1, max>|null $revisionId */
            $revisionId = RVID;
            $this->buildMembersOnlyUnit($tpl, $entryId, $revisionId, $entry, $summaryRange);

            return $tpl->get();
        } catch (\Exception $e) {
        }
        return '';
    }

    /**
     * バリデーター
     *
     * @return void
     * @throws RuntimeException
     */
    protected function validate(): void
    {
        if (!(!!ACMS_SID && !RVID) || !EID) {
            throw new RuntimeException('invalid access.');
        }
    }

    /**
     * エントリー詳細を取得
     *
     * @param int $eid
     * @param null|int $rvid
     * @return array
     * @throws RuntimeException
     */
    protected function getEntry(int $eid, ?int $rvid): array
    {
        if ($rvid) {
            $sql = SQL::newSelect('entry_rev');
            $sql->addWhereOpr('entry_rev_id', $rvid);
        } else {
            $sql = SQL::newSelect('entry');
        }
        $sql->addWhereOpr('entry_id', $eid);
        $entry = DB::query($sql->get(dsn()), 'row');
        if (empty($entry)) {
            throw new RuntimeException('not found entry.');
        }
        return $entry;
    }

    /**
     * 限定内容のユニットの位置を取得
     *
     * @param array $entry
     * @return int
     * @throws RuntimeException
     */
    protected function getSummaryRange(array $entry): ?int
    {
        $summaryRange = strval($entry['entry_summary_range']);
        $summaryRange = !!strlen($summaryRange) ? intval($summaryRange) : null;

        if ($summaryRange === null) {
            throw new \RuntimeException('showing all units.');
        }
        return $summaryRange;
    }

    /**
     * メンバー限定の続きのユニットを組み立て
     *
     * @param Template $tpl
     * @param int<1, max> $eid
     * @param int<1, max>|null $rvid
     * @param array $entry
     * @param int $summaryRange
     * @return void
     */
    protected function buildMembersOnlyUnit(Template $tpl, int $eid, ?int $rvid, array $entry, int $summaryRange): void
    {
        $rvid_ = $rvid;
        if (!$rvid && $entry['entry_approval'] === 'pre_approval') {
            $rvid_ = 1;
        }
        /** @var \Acms\Services\Unit\Repository $unitService */
        $unitService = Application::make('unit-repository');
        /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
        $unitRenderingService = Application::make('unit-rendering-front');

        $collection = $unitService->loadUnits($eid, $rvid_);
        $units = $collection->flat();
        $page = 1;
        foreach ($units as $i => $col) {
            if ($i >= $summaryRange) {
                break;
            }
            if ('break' === $col::getUnitType()) {
                $page += 1;
            }
        }
        $membersOnlyUnits = array_splice($units, $summaryRange);
        if (count($membersOnlyUnits) > 0) {
            $break = $page;
            $micropage = intval($this->page);
            $column2 = [];
            foreach ($membersOnlyUnits as $membersOnlyUnit) {
                if ('break' === $membersOnlyUnit::getUnitType()) {
                    $break++;
                }
                if ($micropage === $break) {
                    $column2[] = $membersOnlyUnit;
                }
            }
            $unitRenderingService->render(new UnitCollection($membersOnlyUnits), $tpl, $eid);
        }
    }
}
