<?php

use Acms\Modules\Get\Helpers\Entry\CalendarHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Entry_Calendar extends ACMS_GET
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'self',
        'cid' => 'self',
    ];

    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
        'date' => 'global',
        'start' => 'global',
        'end' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\Entry\CalendarHelper
     */
    protected $entryCalendarHelper;

    /**
     * コンフィグの取得
     *
     * @return array{
     *   mode: 'month' | 'week' | 'days' | 'until_days',
     *   pagerCount: int,
     *   order: string,
     *   aroundEntry: 'on' | 'off',
     *   beginWeek: int,
     *   maxEntryCount: int,
     *   weekLabels: string[],
     *   today: string,
     *   dateOrder: 'asc' | 'desc'
     * }
     */
    protected function initConfig(): array
    {
        return [
            'mode' => in_array(config('entry_calendar_mode'), ['month', 'week', 'days', 'until_days'], true)
                ? config('entry_calendar_mode')
                : 'month',
            'pagerCount' => intval(config('entry_calendar_pager_count')) !== 0
                ? intval(config('entry_calendar_pager_count'))
                : 7,
            'order' => config('entry_calendar_order'),
            'aroundEntry' => config('entry_calendar_around') === 'on' ? 'on' : 'off',
            'beginWeek' => intval(config('entry_calendar_begin_week')),
            'maxEntryCount' => intval(config('entry_calendar_max_entry_count')) !== 0
                ? intval(config('entry_calendar_max_entry_count'))
                : 3,
            'weekLabels' => configArray('entry_calendar_week_label'),
            'today' => config('entry_calendar_today'),
            'dateOrder' => config('entry_calendar_date_order') === 'asc' ? 'asc' : 'desc',
        ];
    }

    function get()
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new RuntimeException('Not found config.');
            }
            $tpl = new Template($this->tpl, new ACMS_Corrector());
            $this->entryCalendarHelper = new CalendarHelper($this->getBaseParams([
                'config' => $this->config,
            ]));
            TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);

            $this->entryCalendarHelper->setDateContextVars();
            $this->entryCalendarHelper->setCalendarVars();
            $sql = $this->entryCalendarHelper->buildEntryCalendarQuery(
                $this->bid,
                $this->cid,
                $this->uid,
            );
            $q = $sql->get(dsn());
            $entries = Database::query($q, 'all');
            $this->buildWeekLabel($tpl);
            $this->buildForeSpacer($tpl, $entries, 'week:loop');
            $this->buildWeeks($tpl, $entries);
            $this->buildRearSpacer($tpl, $entries, 'week:loop');
            $tpl->add('date', $this->entryCalendarHelper->getDate());

            return $tpl->get();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * weekLabelループブロックの組み立て
     *
     * @param Template $tpl
     * @return void
     */
    protected function buildWeekLabel($tpl): void
    {
        $weekLabels = $this->entryCalendarHelper->getWeekLabel();
        foreach ($weekLabels as $label) {
            $tpl->add('weekLabel:loop', $label);
        }
    }

    /**
     * 前月のspacerの組み立て
     *
     * @param Template $tpl
     * @param array $entries
     * @param string $rootBlock
     * @return void
     */
    protected function buildForeSpacer(Template $tpl, array $entries, string $rootBlock): void
    {
        $prevPaddingDays = $this->entryCalendarHelper->getPrevMonthPaddingDays($entries);

        foreach ($prevPaddingDays as $paddingDay) {
            $paddingEntries = $paddingDay['entries'] ?? [];
            $this->buildEntries($tpl, $paddingEntries, array_merge(['foreEntry:loop', 'foreSpacer'], [$rootBlock]));
            $tpl->add(array_merge(['foreSpacer'], [$rootBlock]), [
                'prevDay' => $paddingDay['day'],
                'prevDate' => $paddingDay['date'],
                'w' => $paddingDay['w'],
                'week' => $paddingDay['week'],
            ]);
        }
    }

    /**
     * 前月のspacerの組み立て
     *
     * @param Template $tpl
     * @param array $entries
     * @param string $rootBlock
     * @return void
     */
    protected function buildRearSpacer(Template $tpl, array $entries, string $rootBlock): void
    {
        $paddingDays = $this->entryCalendarHelper->getNextMonthPaddingDays($entries);

        foreach ($paddingDays as $paddingDay) {
            $paddingEntries = $paddingDay['entries'] ?? [];
            $this->buildEntries($tpl, $paddingEntries, array_merge(['rearSpacer:loop', 'rearSpacer'], [$rootBlock]));
            $tpl->add(array_merge(['rearSpacer'], [$rootBlock]), [
                'nextDay' => $paddingDay['day'],
                'nextDate' => $paddingDay['date'],
                'w' => $paddingDay['w'],
                'week' => $paddingDay['week'],
            ]);
        }
        if ($rootBlock) {
            $tpl->add($rootBlock);
        }
    }

    /**
     * エントリーのループの組み立て
     *
     * @param Template $tpl
     * @param array $entries
     * @param string|array $blocks
     * @return void
     */
    protected function buildEntries(Template $tpl, array $entries, $blocks): void
    {
        foreach ($entries as $entry) {
            $vars = [
                'eid' => $entry['eid'],
                'title' => $entry['title'],
                'cid' => $entry['cid'],
                'bid' => $entry['bid'],
                'status' => $entry['status'],
                'url' => $entry['url'] ?? null,
            ];
            $vars += $this->buildDate($entry['datetime'], $tpl, $blocks);
            $vars += $this->buildField(loadEntryField($entry['eid']), $tpl, $blocks);
            $tpl->add($blocks, $vars);
        }
    }

    protected function buildWeeks(Template $tpl, array $entries): void
    {
        $data = $this->entryCalendarHelper->getDays($entries);

        if ($this->config['mode'] === 'month') {
            foreach ($data as $weeks) {
                foreach ($weeks as $day) {
                    $this->buildDay($tpl, $day, ['week:loop']);
                }
                $tpl->add('week:loop');
            }
        } else {
            foreach ($data as $day) {
                $this->buildDay($tpl, $day, []);
            }
        }
    }

    /**
     * 日付のループの組み立て
     *
     * @param Template $tpl
     * @param array $day
     * @param string[] $blocks
     * @return void
     */
    protected function buildDay(Template $tpl, array $day, $blocks): void
    {
        $block = array_merge(['day:loop'], $blocks);
        $dayEntries = $day['entries'] ?? [];
        $this->buildEntries($tpl, $dayEntries, array_merge(['entry:loop'], $block));
        $tpl->add($block, [
            'week' => $day['week'],
            'w' => $day['w'],
            'day' => $day['day'],
            'date' => $day['date'],
            'today' => $day['today'] ?? null,
        ]);
    }
}
