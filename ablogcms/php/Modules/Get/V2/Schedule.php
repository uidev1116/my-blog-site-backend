<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\CalendarHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Config;
use SQL_Select;
use SQL;
use DateTime;
use RuntimeException;

class Schedule extends Base
{
    use \Acms\Traits\Utilities\EagerLoadingTrait;

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
            $mode = $config->get('v2_schedule_mode', 'month');
            $startWeekDay = (int) $config->get('v2_schedule_begin_week', 0); // 0 = Sunday
            $pastDays = (int) $config->get('v2_schedule_past_days', 3);
            $futureDays = (int) $config->get('v2_schedule_future_days', 3);
            $key = $config->get('v2_schedule_key');
            if (!$key) {
                throw new RuntimeException('Schedule key is not set in the module configuration.');
            }
            $labelData = $this->getLabelMap($key);
            $sql = $this->buildScheduleQuery(
                $this->bid,
                $key,
                $baseDate,
                $this->calendarHelper->getStartDate($mode, $baseDate, $startWeekDay, $pastDays),
                $this->calendarHelper->getEndDate($mode, $baseDate, $startWeekDay, $futureDays)
            );
            $q = $sql->get(dsn());
            $schedules = Database::query($q, 'all');
            $buildData = $this->buildScheduleData($schedules, $labelData);
            $weekLabels = $config->getArray('v2_schedule_week_label') ? $config->getArray('v2_schedule_week_label') : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

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
            $vars['weeks'] = $this->addEntryDataToCalendar($vars['weeks'], $buildData);
            $vars['moduleFields'] = $this->buildModuleField();

            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * ラベルマップを取得
     *
     * @param string $key
     * @return array
     */
    protected function getLabelMap(string $key): array
    {
        $config = Config::loadBlogConfig($this->bid);
        $labelData = $config->getArray("schedule_label@{$key}");
        $separator = config('schedule_label_separator', '__sep__');
        $separator = $separator ? $separator : '__sep__';

        return array_reduce($labelData, function ($carry, $row) use ($separator) {
            [$label, $id, $class] = explode($separator, $row, 3) + [null, null, null];
            $carry[$id] = [
                'label' => $label ?? '',
                'class' => $class ?? '',
            ];
            return $carry;
        }, []);
    }

    /**
     * スケジュール情報のSQLクエリを組み立てる
     *
     * @param int $bid
     * @param string $key
     * @param string $baseDate
     * @param string $startDate
     * @param string $endDate
     * @return SQL_Select
     */
    protected function buildScheduleQuery(int $bid, string $key, string $baseDate, string $startDate, string $endDate): SQL_Select
    {
        $date = new DateTime($baseDate);
        $startDateTime = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);
        $yearList = array_unique([$date->format('Y'), $startDateTime->format('Y'), $endDateTime->format('Y')]);
        $monthList = array_unique([$date->format('m'), $startDateTime->format('m'), $endDateTime->format('m')]);

        $sql = SQL::newSelect('schedule');
        $sql->addWhereOpr('schedule_id', $key);
        $sql->addWhereIn('schedule_year', $yearList);
        $sql->addWhereIn('schedule_month', $monthList);
        $sql->addWhereOpr('schedule_blog_id', $bid);

        return $sql;
    }

    /**
     * スケジュール情報を組み立てる
     *
     * @param array $data
     * @param array $labelData
     * @return array
     */
    protected function buildScheduleData(array $data, array $labelData): array
    {
        $items = [];
        if (!$data) {
            return [];
        }
        foreach ($data as $monthData) {
            $year = $monthData['schedule_year'];
            $month = $monthData['schedule_month'];
            $scheduleData = acmsDangerUnserialize($monthData['schedule_data']);
            $fieldData = acmsDangerUnserialize($monthData['schedule_field']);
            foreach ($scheduleData as $i => $day) {
                $date = "{$year}-{$month}-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                if (!isset($items[$date])) {
                    $items[$date] = [];
                }
                $labelKey = $day["label{$i}"][0] ?? '';
                $item = [
                    'text' => $day["item{$i}"][0] ?? '',
                    'label' => $labelData[$labelKey]['label'] ?? '',
                    'class' => $labelData[$labelKey]['class'] ?? '',
                ];
                $item['fields'] = isset($fieldData[$i]) ? $this->buildFieldTrait($fieldData[$i]) : null;
                $items[$date] = $item;
            }
        }
        return $items;
    }

    /**
     * カレンダーにスケジュール情報を追加
     *
     * @param array $weeks
     * @param array $data
     * @return array
     */
    protected function addEntryDataToCalendar(array $weeks, array $data): array
    {
        $newWeeks = [];
        foreach ($weeks as $week) {
            $newWeek = [];
            foreach ($week as $day) {
                $date = $day['date'];
                $day['data'] = isset($data[$date]) ? $data[$date] : null;
                $newWeek[] = $day;
            }
            $newWeeks[] = $newWeek;
        }
        return $newWeeks;
    }
}
