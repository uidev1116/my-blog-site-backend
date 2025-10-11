<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Services\Facades\Database;
use Acms\Modules\Get\Helpers\CalendarHelper;
use SQL_Select;
use SQL;
use ACMS_Filter;
use RuntimeException;

class Calendar extends Base
{
    use \Acms\Traits\Utilities\EagerLoadingTrait;

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
     * @var \Acms\Modules\Get\Helpers\CalendarHelper
     */
    protected $calendarHelper;

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            $config = $this->loadModuleConfig();
            $this->calendarHelper = new CalendarHelper($this->getBaseParams([]));
            $baseDate = !$this->start || substr($this->start, 0, 10) === '1000-01-01'
                    ? date('Y-m-d', requestTime())
                    : substr($this->start, 0, 10);
            $mode = $config->get('v2_calendar_mode', 'month');
            $startWeekDay = (int) $config->get('v2_calendar_begin_week', 0); // 0 = Sunday
            $pastDays = (int) $config->get('v2_calendar_past_days', 3);
            $futureDays = (int) $config->get('v2_calendar_future_days', 3);

            $sql = $this->buildEntryCalendarQuery($this->bid, $this->cid, $this->uid, [
                'order' => $config->get('v2_calendar_order', 'datetime-asc'),
                'limit' => $config->get('v2_calendar_max_entry_count', 99),
                'startDate' => $this->calendarHelper->getStartDate($mode, $baseDate, $startWeekDay, $pastDays),
                'endDate' => $this->calendarHelper->getEndDate($mode, $baseDate, $startWeekDay, $futureDays),
            ]);
            $q = $sql->get(dsn());
            $entries = Database::query($q, 'all');
            $buildEntries = $this->buildEntries($entries);
            $weekLabels = $config->getArray('v2_calendar_week_label') ?
                $config->getArray('v2_calendar_week_label') :
                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            /** @var 'month'|'range' $mode */
            $vars = $this->calendarHelper->buildCalendar([
                'mode' => $mode,
                'weekLabels' => $weekLabels,
                'baseDate' => $baseDate,
                'startWeekDay' => $startWeekDay, // 0 = Sunday
                'showPadding' => true,
                'pastDays' => $pastDays, // for 'range' mode
                'futureDays' => $futureDays, // for 'range' mode
            ]);
            $vars['weeks'] = $this->addEntryDataToCalendar($vars['weeks'], $buildEntries);
            $vars['moduleFields'] = $this->buildModuleField();

            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * sqlの組み立て
     *
     * @param integer $bid
     * @param integer|null $cid
     * @param integer|null $uid
     * $param array{
     *  order: string, // 'datetime-asc' | 'datetime-desc' | 'id-asc' | 'id-desc' ...
     * } $options
     * @return SQL_Select
     */
    protected function buildEntryCalendarQuery(int $bid, ?int $cid = null, ?int $uid = null, array $options = []): SQL_Select
    {
        $order = $options['order'] ?? 'datetime-asc';
        $limit = $options['limit'] ?? 99;
        $startDate = $options['startDate'] ?? date('Y-m-01 00:00:00', requestTime());
        $endDate = $options['endDate'] ?? date('Y-m-t 00:00:00', requestTime());

        $sql = SQL::newSelect('entry');
        $sql->addSelect(SQL::newFunction('entry_datetime', ['SUBSTR', 0, 10]), 'entry_date', null, 'DISTINCT');
        $sql->addSelect('entry_id');
        $sql->addSelect('entry_approval');
        $sql->addSelect('entry_title');
        $sql->addSelect('entry_category_id');
        $sql->addSelect('entry_blog_id');
        $sql->addSelect('entry_link');
        $sql->addSelect('entry_datetime');
        $sql->addSelect('entry_start_datetime');
        $sql->addSelect('entry_end_datetime');
        $sql->addSelect('entry_status');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        ACMS_Filter::blogTree($sql, $bid, $this->blogAxis());
        ACMS_Filter::blogStatus($sql);
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        if ($cid) {
            ACMS_Filter::categoryTree($sql, $cid, $this->categoryAxis());
        }
        ACMS_Filter::categoryStatus($sql);
        ACMS_Filter::entrySession($sql);
        ACMS_Filter::entrySpan($sql, $startDate, $endDate);
        ACMS_Filter::entryOrder($sql, $order, $uid, $cid);
        $sql->setLimit($limit);

        return $sql;
    }

    /**
     * エントリー情報を組み立てる
     *
     * @param array $entries
     * @return array
     */
    protected function buildEntries(array $entries): array
    {
        $items = [];
        if (!$entries) {
            return [];
        }
        $entryIds = array_map(function ($entry) {
            return (int) $entry['entry_id'];
        }, $entries);
        $eagerLoadingField = $this->eagerLoadFieldTrait($entryIds, 'eid');

        foreach ($entries as $entry) {
            $date = $entry['entry_date'];
            if (!isset($items[$date])) {
                $items[$date] = [];
            }
            $eid = (int) $entry['entry_id'];
            $bid = (int) $entry['entry_blog_id'];
            $link = $entry['entry_link'];
            $link = $link ? $link : acmsLink([
                'bid' => $bid,
                'eid' => $eid,
            ]);
            $item = [
                'eid' => $eid,
                'cid' => is_numeric($entry['entry_category_id']) ? (int) $entry['entry_category_id'] : null,
                'bid' => (int) $entry['entry_blog_id'],
                'status' => $entry['entry_status'],
                'title' => addPrefixEntryTitle(
                    $entry['entry_title'],
                    $entry['entry_status'],
                    $entry['entry_start_datetime'],
                    $entry['entry_end_datetime'],
                    $entry['entry_approval']
                ),
                'date' => $entry['entry_datetime'],
            ];
            if ($link !== '#') {
                $item['url']  = $link;
            }
            $item['fields'] = isset($eagerLoadingField[$eid]) ? $this->buildFieldTrait($eagerLoadingField[$eid]) : null;
            $items[$date][] = $item;
        }
        return $items;
    }

    /**
     * カレンダーにエントリー情報を追加
     *
     * @param array $weeks
     * @param array $entries
     * @return array
     */
    protected function addEntryDataToCalendar(array $weeks, array $entries): array
    {
        $newWeeks = [];
        foreach ($weeks as $week) {
            $newWeek = [];
            foreach ($week as $day) {
                $date = $day['date'];
                $day['entries'] = isset($entries[$date]) ? $entries[$date] : null;
                $newWeek[] = $day;
            }
            $newWeeks[] = $newWeek;
        }
        return $newWeeks;
    }
}
