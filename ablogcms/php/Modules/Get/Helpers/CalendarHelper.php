<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Modules\Get\Helpers\BaseHelper;
use DateTime;

class CalendarHelper extends BaseHelper
{
    /**
     * カレンダーを組み立て
     *
     * @param array{
     *  mode?: 'month' | 'range',
     *  weekLabels?: string[], // ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
     *  baseDate?: string, // 'today' or 'YYYY-MM-DD'
     *  startWeekDay?: int, // 0 = Sunday
     *  showPadding?: bool,
     *  pastDays?: int, // for 'range' mode
     *  futureDays?: int, // for 'range' mode
     * } $options
     * @return array
     */
    public function buildCalendar(array $options = []): array
    {
        $mode = $options['mode'] ?? 'month'; // 'month' or 'range'
        $baseDate = new DateTime($options['baseDate'] ?? 'today');
        $weekLabels = $options['weekLabels'] ?? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $startWeekDay = $options['startWeekDay'] ?? 0; // 0 = Sunday
        $showPadding = $options['showPadding'] ?? true;

        if ($mode === 'range') {
            $past = $options['pastDays'] ?? 3;
            $future = $options['futureDays'] ?? 3;
            $items = $this->buildRange($baseDate, $weekLabels, $past, $future);
            $startWeekDay = isset($items[0][0]['week']) ? $items[0][0]['week'] : 0; // Ensure valid week day index (0-6)
            $weekLabels = $this->getWeekLabel($weekLabels, $startWeekDay);
            $weekLength = count($items[0] ?? []) < 7 ? count($items[0] ?? []) : 7;
            return [
                'date' => $this->getDate($mode, $baseDate, $past, $future),
                'weekLabels' => array_slice($weekLabels, 0, $weekLength),
                'weeks' => $items,
            ];
        } else {
            return [
                'date' => $this->getDate($mode, $baseDate),
                'weekLabels' => $this->getWeekLabel($weekLabels, $startWeekDay),
                'weeks' => $this->buildMonth($baseDate, $weekLabels, $startWeekDay, $showPadding),
            ];
        }
    }

    /**
     * カレンダーの開始日を取得
     *
     * @param string $mode 'month' or 'range'
     * @param string $baseDate 'YYYY-MM-DD'
     * @param integer $startWeekDay // 0 = Sunday
     * @param integer $pastDays
     * @return string
     */
    public function getStartDate(string $mode, string $baseDate, int $startWeekDay, int $pastDays): string
    {
        if ($mode === 'range') {
            return (new DateTime($baseDate))->modify("-{$pastDays} days")->format('Y-m-d 00:00:00');
        }
        $firstDayOfMonth = (new DateTime($baseDate))->modify('first day of this month');
        $firstWeekDay = (int) $firstDayOfMonth->format('w');
        $diff = ($firstWeekDay - $startWeekDay + 7) % 7;

        $paddingStart = clone $firstDayOfMonth;
        return $paddingStart->modify("-{$diff} days")->format('Y-m-d 00:00:00');
    }

    /**
     * カレンダーの終了日を取得
     *
     * @param string $mode 'month' or 'range'
     * @param string $baseDate 'YYYY-MM-DD'
     * @param integer $startWeekDay // 0 = Sunday
     * @param integer $futureDays
     * @return string
     */
    public function getEndDate(string $mode, string $baseDate, int $startWeekDay, int $futureDays): string
    {
        if ($mode === 'range') {
            return (new DateTime($baseDate))->modify("+{$futureDays} days")->format('Y-m-d 23:59:59');
        }
        $lastDayOfMonth = (new DateTime($baseDate))->modify('last day of this month');
        $w = (int) $lastDayOfMonth->format('w');
        $endWeekDay = ($startWeekDay + 6) % 7;
        $diff = ($endWeekDay - $w + 7) % 7;

        $paddingEnd = clone $lastDayOfMonth;
        return $paddingEnd->modify("+{$diff} days")->format('Y-m-d 23:59:59');
    }

    /**
     * dateブロックの変数の取得
     *
     * @param string $mode 'month' or 'range'
     * @param Datetime $date
     * @param integer|null $past
     * @param integer|null $future
     * @return array
     */
    public function getDate(string $mode, Datetime $date, ?int $past = null, ?int $future = null): array
    {
        if ($mode === 'range') {
            $pastDay = $past ?? 3;
            $futureDay = $future ?? 3;
            $prev = (clone $date)->modify("-{$pastDay} days");
            $next = (clone $date)->modify("+{$futureDay} days");
            $format = 'Y/m/d';
        } else {
            $prev = (clone $date)->modify('first day of last month');
            $next = (clone $date)->modify('first day of next month');
            $format = 'Y/m';
        }
        return [
            'date' => $date->format('Y-m-d'),
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('m'),
            'day' => (int) $date->format('d'),
            'prevDate' => $prev->format($format),
            'nextDate' => $next->format($format),
            'prevUrl' => acmsLink([
                'bid' => $this->bid,
                'cid' => $this->cid,
                'date' => $prev->format($format),
            ]),
            'nextUrl' => acmsLink([
                'bid' => $this->bid,
                'cid' => $this->cid,
                'date' => $next->format($format),
            ]),
        ];
    }

    /**
     * dateブロックの変数の取得
     *
     * @param Datetime $date
     * @return array
     */
    public function getYearDate(Datetime $date): array
    {
        $prev = (clone $date)->modify('-1 year');
        $next = (clone $date)->modify('+1 year');
        $format = 'Y';

        return [
            'date' => $date->format('Y-m-d'),
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('m'),
            'day' => (int) $date->format('d'),
            'prevDate' => $prev->format($format),
            'nextDate' => $next->format($format),
            'prevUrl' => acmsLink([
                'bid' => $this->bid,
                'cid' => $this->cid,
                'date' => $prev->format($format),
            ]),
            'nextUrl' => acmsLink([
                'bid' => $this->bid,
                'cid' => $this->cid,
                'date' => $next->format($format),
            ]),
        ];
    }

    /**
     * 曜日ラベルの取得
     *
     * @param string[] $weekLabels
     * @param int $beginWeek 0 = Sunday
     * @return array
     */
    public function getWeekLabel(array $weekLabels, int $beginWeek): array
    {
        $labels = [];
        for ($i = 0; $i < 7; $i++) {
            $w = ($beginWeek + $i) % 7;
            if (isset($weekLabels[$w]) && $weekLabels[$w]) {
                $labels[] = [
                    'w' => $w,
                    'label' => $weekLabels[$w],
                ];
            }
        }
        return $labels;
    }

    /**
     * 日付の配列を組み立て
     *
     * @param DateTime $baseDate
     * @param array $weekLabels
     * @param int $pastDays
     * @param int $futureDays
     * @return array
     */
    public function buildRange(DateTime $baseDate, array $weekLabels, int $pastDays, int $futureDays): array
    {
        $dates = [];
        for ($i = -$pastDays; $i <= $futureDays; $i++) {
            $d = (clone $baseDate)->modify("{$i} days");
            $dates[] = $this->buildDateArray($d, false, $weekLabels);
        }
        return array_chunk($dates, 7);
    }

    /**
     * 月のカレンダーを組み立て
     *
     * @param DateTime $baseDate
     * @param array $weekLabels
     * @param int $startWeekDay 0 = Sunday
     * @param bool $showPadding
     * @return array
     */
    public function buildMonth(DateTime $baseDate, array $weekLabels, int $startWeekDay, bool $showPadding): array
    {
        $dates = [];

        $year = (int)$baseDate->format('Y');
        $month = (int)$baseDate->format('m');
        $first = new DateTime("$year-$month-01");
        $last = (clone $first)->modify('last day of this month');

        $dayCount = (int)$last->format('d');
        $firstWeekDay = (int)$first->format('w');

        // 前パディング
        if ($showPadding) {
            $paddingStart = ($firstWeekDay - $startWeekDay + 7) % 7;
            for ($i = $paddingStart; $i > 0; $i--) {
                $d = (clone $first)->modify("-{$i} days");
                $dates[] = $this->buildDateArray($d, true, $weekLabels);
            }
        }

        // 今月の日
        for ($d = 1; $d <= $dayCount; $d++) {
            $date = new DateTime("$year-$month-$d");
            $dates[] = $this->buildDateArray($date, false, $weekLabels);
        }

        // 後パディング
        if ($showPadding) {
            $lastWeekDay = (int)$last->format('w');
            $paddingEnd = ($startWeekDay + 6 - $lastWeekDay + 7) % 7;
            for ($i = 1; $i <= $paddingEnd; $i++) {
                $d = (clone $last)->modify("+{$i} days");
                $dates[] = $this->buildDateArray($d, true, $weekLabels);
            }
        }

        // 週ごとに分割
        $weeks = [];
        $week = [];
        foreach ($dates as $date) {
            $week[] = $date;
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }
        if ($week) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * 日付の配列を組み立て
     *
     * @param DateTime $date
     * @param bool $isPadding
     * @param array $weekLabels
     * @return array
     */
    public function buildDateArray(DateTime $date, bool $isPadding, array $weekLabels): array
    {
        $today = new DateTime();
        $isToday = $date->format('Y-m-d') === $today->format('Y-m-d');
        $week = (int) $date->format('w'); // 0=Sunday, 6=Saturday

        return [
            'date' => $date->format('Y-m-d'),
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('m'),
            'day' => (int) $date->format('d'),
            'week' => $week,
            'weekLabel' => $weekLabels[$week] ?? '',
            'isPadding' => $isPadding,
            'isToday' => $isToday,
            'url' => acmsLink([
                'bid' => $this->bid,
                'cid' => $this->cid,
                'date' => $date->format('Y/m/d'),
            ]),
        ];
    }
}
