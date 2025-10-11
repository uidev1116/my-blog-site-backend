<?php

namespace Acms\Modules\Get\V2;

use Acms\Services\Facades\Database;
use Acms\Modules\Get\Helpers\CalendarHelper;
use RuntimeException;
use DateTime;

class CalendarYear extends Calendar
{
    use \Acms\Traits\Utilities\EagerLoadingTrait;

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
            $order = $config->get('v2_calendar_order', 'datetime-asc');
            $limit = (int) $config->get('v2_calendar_max_entry_count', 99);
            $startWeekDay = (int) $config->get('v2_calendar_begin_week', 0); // 0 = Sunday
            $weekLabels = $config->getArray('v2_calendar_week_label') ?
                $config->getArray('v2_calendar_week_label') :
                ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            $vars = [];
            $vars['date'] = $this->calendarHelper->getYearDate(new DateTime($baseDate));
            $vars['weekLabels'] = $this->calendarHelper->getWeekLabel($weekLabels, $startWeekDay);
            $vars['year'] = $this->buildYear(
                $baseDate,
                $order,
                $limit,
                $startWeekDay,
                $weekLabels
            );
            $vars['moduleFields'] = $this->buildModuleField();

            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 年間カレンダーデータを構築します。
     *
     * @param string $baseDate 年（YYYY-MM-DD形式）。
     * @param string $order エントリーの並び順（例: 'datetime-asc'）。
     * @param int $limit 取得するエントリーの最大数。
     * @param int $startWeekDay 週の開始曜日（0 = 日曜日）。
     * @param array $weekLabels 曜日のラベル配列。
     * @return array 構築された年間カレンダーデータ。
     */
    protected function buildYear(string $baseDate, string $order, int $limit, int $startWeekDay, array $weekLabels): array
    {
        $yearTime = strtotime($baseDate);
        if ($yearTime === false) {
            throw new RuntimeException('Invalid base date format.');
        }
        $year = date('Y', $yearTime);
        $months = [];
        foreach (range(1, 12) as $month) {
            $monthLabel = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $baseDate = "{$year}-{$monthLabel}-01";
            $months[] = [
                'm' => $month,
                'label' => $monthLabel,
                'weeks' => $this->buildMonth(
                    $baseDate,
                    $order,
                    $limit,
                    $startWeekDay,
                    $weekLabels
                ),
            ];
        }
        return $months;
    }

    /**
     * 月間カレンダーデータを構築します。
     *
     * @param string $baseDate 月の基準日付（YYYY-MM-DD）。
     * @param string $order エントリーの並び順（例: 'datetime-asc'）。
     * @param int $limit 取得するエントリーの最大数。
     * @param int $startWeekDay 週の開始曜日（0 = 日曜日）。
     * @param array $weekLabels 曜日のラベル配列。
     * @return array 構築された月間カレンダーデータ。
     */
    protected function buildMonth(string $baseDate, string $order, int $limit, int $startWeekDay, array $weekLabels): array
    {
        $sql = $this->buildEntryCalendarQuery($this->bid, $this->cid, $this->uid, [
            'order' => $order,
            'limit' => $limit,
            'startDate' => $this->calendarHelper->getStartDate('month', $baseDate, $startWeekDay, 0),
            'endDate' => $this->calendarHelper->getEndDate('month', $baseDate, $startWeekDay, 0),
        ]);
        $q = $sql->get(dsn());
        $entries = Database::query($q, 'all');
        $buildEntries = $this->buildEntries($entries);
        $weeks = $this->calendarHelper->buildMonth(
            new DateTime($baseDate),
            $weekLabels,
            $startWeekDay,
            true
        );
        return $this->addEntryDataToCalendar($weeks, $buildEntries);
    }
}
