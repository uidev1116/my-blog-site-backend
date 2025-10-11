<?php

namespace Acms\Modules\Get\Helpers\Entry;

use Acms\Modules\Get\Helpers\BaseHelper;
use SQL;
use SQL_Select;
use ACMS_Filter;
use RuntimeException;

class CalendarHelper extends BaseHelper
{
    use \Acms\Traits\Utilities\FieldTrait;
    use \Acms\Traits\Utilities\EagerLoadingTrait;

    protected $entries;
    protected $ymd;
    protected $y;
    protected $m;
    protected $d;

    /**
     * カレンダーの開始日
     * 例）前後月のエントリー表示を有効にしている場合、2022/06 のURLコンテキストでアクセスしたとき 2022-05-30 00:00:00
     *
     * @var string
     */
    protected $startDate;

    /**
     * カレンダーの終了日
     * 例）前後月のエントリー表示を有効にしている場合、2022/06 のURLコンテキストでアクセスしたとき 2022-07-03 23:59:59
     *
     * @var string
     */
    protected $endDate;

    /**
     * エントリーの開始日
     *
     * @var string
     */
    protected $entryStartDate;

    /**
     * エントリーの終了日
     *
     * @var string
     */
    protected $entryEndDate;

    /**
     * day:loop ブロックの中で最も若い日
     *
     * @var int
     */
    protected $firstDay;

    /**
     * day:loop ブロックのループ回数
     *
     * @var int
     */
    protected $loopCount;

    /**
     * day:loop ブロックの中で最も若い日の曜日（数値）
     *
     * @var int
     */
    protected $firstW;

    /**
     * 前後月を考慮した最初の曜日（数値）
     *
     * @var int
     */
    protected $beginW;

    /**
     * week:loopブロックの区切りの曜日（数値）
     *
     * @var int
     */
    protected $separateWeek;

    /**
     * sqlの組み立て
     *
     * @param integer $bid
     * @param integer|null $cid
     * @param integer|null $uid
     * @return SQL_Select
     */
    public function buildEntryCalendarQuery(int $bid, ?int $cid = null, ?int $uid = null): SQL_Select
    {
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
        ACMS_Filter::blogTree($sql, $bid, $this->blogAxis);
        ACMS_Filter::blogStatus($sql);
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');

        if ($cid) {
            ACMS_Filter::categoryTree($sql, $cid, $this->categoryAxis);
        }
        ACMS_Filter::categoryStatus($sql);
        ACMS_Filter::entrySession($sql);
        ACMS_Filter::entrySpan($sql, $this->entryStartDate, $this->entryEndDate);
        ACMS_Filter::entryOrder($sql, $this->config['order'], $uid, $cid);

        return $sql;
    }

    /**
     * 日付のURLコンテキストに関わるの代入
     *
     * @return void
     */
    public function setDateContextVars(): void
    {
        if ($this->start === null) {
            $this->start = '1000-01-01 00:00:00';
        }
        $this->ymd = substr($this->start, 0, 10) === '1000-01-01'
                ? date('Y-m-d', requestTime())
                : substr($this->start, 0, 10);

        list($this->y, $this->m, $this->d) = explode('-', $this->ymd);
    }

    /**
     * 日付を計算
     *
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $addDays
     * @return string
     */
    protected function computeDate(int $year, int $month, int $day, int $addDays): string
    {
        $baseSec = mktime(0, 0, 0, $month, $day, $year);
        if ($baseSec === false) {
            throw new \RuntimeException("Invalid date: {$year}-{$month}-{$day}");
        }
        $addSec = $addDays * 86400;
        $targetSec = $baseSec + $addSec;

        return date('Y-m-d', $targetSec);
    }

    /**
     * カレンダーの計算に必要な変数の代入
     *
     * @return void
     */
    public function setCalendarVars(): void
    {
        switch ($this->config['mode']) {
            case "month":
                $ym = substr($this->ymd, 0, 7);
                $this->startDate = $ym . '-01 00:00:00';
                $this->endDate = $ym . '-31 23:59:59';

                $this->entryStartDate = $this->startDate;
                $this->entryEndDate = $this->endDate;

                $this->firstDay = 1;
                $this->loopCount = intval($this->formatDate('t', $ym . '-01'));
                $this->firstW = intval($this->formatDate('w', $ym . '-01'));
                $this->beginW = intval($this->config['beginWeek']);
                $prevS = ($this->firstW + (7 - $this->beginW)) % 7;

                $this->startDate = $this->computeDate(
                    (int)substr($this->startDate, 0, 4),
                    (int)substr($this->startDate, 5, 2),
                    (int)substr($this->startDate, 8, 2),
                    -$prevS
                );
                $this->startDate = $this->startDate . ' 00:00:00';
                $lastW = intval($this->formatDate('w', $this->endDate));
                $nextS = 6 - ($lastW + (7 - $this->beginW)) % 7;
                $this->endDate = $this->computeDate(
                    (int)substr($this->endDate, 0, 4),
                    (int)substr($this->endDate, 5, 2),
                    (int)substr($this->endDate, 8, 2),
                    $nextS
                );
                $this->endDate = $this->endDate . ' 23:59:59';

                if ($this->config['aroundEntry'] === 'on') {
                    $this->entryStartDate = $this->startDate;
                    $this->entryEndDate = $this->endDate;
                }

                // day:loop で曜日が1巡する前にweek:loopを追加する
                $this->separateWeek = ($this->beginW - 1 < 0) ? 6 : $this->beginW - 1;

                /**
                 * entry_calendar_date_orderがdescのときは逆から数えるため1つ前の曜日ではなく
                 * 前後月を考慮した最初の曜日を区切りの曜日とする
                 */
                if ($this->config['dateOrder'] === 'desc') {
                    $this->separateWeek = $this->beginW;
                }

                break;

            case "week":
                $week = intval($this->formatDate('w', $this->ymd));
                $prevNum = ($week >= $this->config['beginWeek']) ? $week - $this->config['beginWeek'] : 7 - ($this->config['beginWeek'] - $week);
                $minusDay = $this->computeDate(
                    (int)substr($this->ymd, 0, 4),
                    (int)substr($this->ymd, 5, 2),
                    (int)substr($this->ymd, 8, 2),
                    -$prevNum
                );
                $addDay = $this->computeDate(
                    (int)substr($this->ymd, 0, 4),
                    (int)substr($this->ymd, 5, 2),
                    (int)substr($this->ymd, 8, 2),
                    6 - $prevNum
                );

                $this->startDate = $minusDay . ' 00:00:00';
                $this->endDate = $addDay . ' 23:59:59';

                $this->firstDay = intval(substr($minusDay, 8, 2));

                $this->entryStartDate = $this->startDate;
                $this->entryEndDate = $this->endDate;

                $this->loopCount = 7;
                $this->firstW = intval($this->formatDate('w', $this->startDate));
                $this->beginW = intval($this->config['beginWeek']);

                $this->separateWeek = -1; // week:loopブロックを追加しない

                break;

            case "days":
                $addDay = $this->computeDate(
                    (int)substr($this->ymd, 0, 4),
                    (int)substr($this->ymd, 5, 2),
                    (int)substr($this->ymd, 8, 2),
                    6
                );
                $this->startDate = $this->ymd . ' 00:00:00';
                $this->endDate = $addDay . ' 23:59:59';

                $this->firstDay = intval(substr($this->ymd, 8, 2));

                $this->loopCount = 7;

                $this->entryStartDate = $this->startDate;
                $this->entryEndDate = $this->endDate;
                $this->firstW = intval($this->formatDate('w', $this->startDate));
                $this->beginW = intval($this->formatDate('w', $this->startDate));

                $this->separateWeek = -1; // week:loopブロックを追加しない

                break;

            case "until_days":
                $minusDay = $this->computeDate(
                    (int)substr($this->ymd, 0, 4),
                    (int)substr($this->ymd, 5, 2),
                    (int)substr($this->ymd, 8, 2),
                    -6
                );

                $this->startDate = $minusDay . ' 00:00:00';
                $this->endDate = $this->ymd . ' 23:59:59';

                $this->firstDay = intval(substr($minusDay, 8, 2));

                $this->loopCount = 7;

                $this->entryStartDate = $this->startDate;
                $this->entryEndDate = $this->endDate;
                $this->firstW = intval($this->formatDate('w', $this->startDate));
                $this->beginW = intval($this->formatDate('w', $this->startDate));

                $this->separateWeek = -1; // week:loopブロックを追加しない

                break;
        }
    }

    /**
     * 曜日ラベルの取得
     *
     * @return array
     */
    public function getWeekLabel(): array
    {
        $weekLabels = [];
        for ($i = 0; $i < 7; $i++) {
            $w = ($this->beginW + $i) % 7;
            if (isset($this->config['weekLabels'][$w]) && $this->config['weekLabels'][$w]) {
                $weekLabels[] = [
                    'w' => $w,
                    'label' => $this->config['weekLabels'][$w],
                ];
            }
        }
        return $weekLabels;
    }

    /**
     * 指定した日付のエントリーを取得
     *
     * @param string $date
     * @param array $entries
     * @return array
     */
    protected function getEntriesByDate(string $date, array $entries): array
    {
        $buildEntries = [];
        $currentEntries = [];
        $entryIds = [];
        foreach ($entries as $entry) {
            if ($entry['entry_date'] === $date) {
                $currentEntries[] = [
                    'eid' => (int) $entry['entry_id'],
                    'cid' => (int) $entry['entry_category_id'],
                    'bid' => (int) $entry['entry_blog_id'],
                    'title' => addPrefixEntryTitle(
                        $entry['entry_title'],
                        $entry['entry_status'],
                        $entry['entry_start_datetime'],
                        $entry['entry_end_datetime'],
                        $entry['entry_approval']
                    ),
                    'link' => $entry['entry_link'],
                    'status' => $entry['entry_status'],
                    'date' => $entry['entry_datetime'],
                ];
                $entryIds[] = (int) $entry['entry_id'];
                if (count($currentEntries) === $this->config['maxEntryCount']) {
                    break;
                }
            }
        }
        if (count($currentEntries) !== 0) {
            $eagerLoadingField = $this->eagerLoadFieldTrait($entryIds, 'eid');

            foreach ($currentEntries as $entry) {
                $eid = $entry['eid'];
                $link = $entry['link'];
                $link = $link ? $link : acmsLink([
                    'bid' => $entry['bid'],
                    'eid' => $eid,
                ]);
                $vars = [
                    'title' => $entry['title'],
                    'eid' => $entry['eid'],
                    'cid' => $entry['cid'],
                    'bid' => $entry['bid'],
                    'status' => $entry['status'],
                    'datetime' => $entry['date'],
                ];
                if ($link !== '#') {
                    $vars['url']  = $link;
                }
                $vars['fields'] = isset($eagerLoadingField[$eid]) ? $this->buildFieldTrait($eagerLoadingField[$eid]) : null;
                $buildEntries[] = $vars;
            }
        }
        return $buildEntries;
    }

    public function getDays(array $entries): array
    {
        $weeks = [];
        $days = [];
        $time = strtotime($this->startDate . strval(($this->firstW + (7 - $this->beginW)) % 7) . 'day');
        if ($time === false) {
            throw new RuntimeException('Invalid date format: ' . $this->startDate . strval(($this->firstW + (7 - $this->beginW)) % 7) . 'day');
        }
        $date = date('Y-m-d', $time);

        if ($this->config['dateOrder'] === 'desc') {
            $time = strtotime($date . strval($this->loopCount - 1) . ' day');
            if ($time === false) {
                throw new RuntimeException('Invalid date format: ' . $date . strval($this->loopCount - 1) . ' day');
            }
            $date = date('Y-m-d', $time);
        }
        for ($i = 0; $i < intval($this->loopCount); $i++) {
            $curW = intval($this->formatDate('w', $date));
            $vars = [
                'week' => $this->config['weekLabels'][$curW],
                'w' => $curW,
                'day' => intval(substr($date, 8, 2)), // 先頭の0削除
                'date' => $date,
                'padding' => false,
                'url' => acmsLink([
                    'bid' => $this->bid,
                    'cid' => $this->cid,
                    'date' => date('Y/m/d', $time),
                ]),
            ];
            if (date('Y-m-d', requestTime()) === $date) {
                $vars += [
                    'today' => $this->config['today']
                ];
            }
            $vars['entries'] = $this->getEntriesByDate($date, $entries);
            $days[] = $vars;
            $time = strtotime($date . (($this->config['dateOrder'] === 'desc') ? '-' : '') . '1 day');
            if ($time === false) {
                throw new RuntimeException('Invalid date format: ' . $date . (($this->config['dateOrder'] === 'desc') ? '-' : '') . '1 day');
            }
            $date = date('Y-m-d', $time);

            if ($this->separateWeek === $curW) {
                $weeks[] = $days;
                $days = [];
            }
        }
        if (count($days) > 0) {
            $weeks[] = $days;
        }
        return $weeks;
    }

    /**
     * 前月のパディング日を取得
     *
     * @param array $entries
     * @return array
     */
    public function getPrevMonthPaddingDays(array $entries): array
    {
        $paddingDays = [];
        if ($this->config['mode'] !== 'month') {
            return [];
        }
        if ($this->config['aroundEntry'] !== 'on') {
            return [];
        }
        if ($this->config['dateOrder'] !== 'desc') {
            $span = ($this->firstW + (7 - $this->beginW)) % 7;
            $date = substr($this->startDate, 0, 10);
        } else {
            $lastW  = intval($this->formatDate('w', 'last day of' . substr($this->ymd, 0, 7)));
            $span = 6 - ($lastW + (7 - $this->beginW)) % 7;
            $date = substr($this->endDate, 0, 10);
        }
        if ($span === 0) {
            return [];
        }
        for ($i = 0; $i < $span; $i++) {
            $pw = intval($this->formatDate('w', $date));
            if (isset($this->config['weekLabels'][$pw]) && $this->config['weekLabels'][$pw]) {
                $paddingDays[] = [
                    'day' => intval(substr($date, 8, 2)), // 先頭の0削除
                    'date' => $date,
                    'w' => $pw,
                    'week' => $this->config['weekLabels'][$pw],
                    'url' => acmsLink([
                        'bid' => $this->bid,
                        'cid' => $this->cid,
                        'date' => $this->formatDate('Y/m/d', $date),
                    ]),
                    'padding' => true,
                    'entries' => $this->getEntriesByDate($date, $entries),
                ];
                $time = strtotime($date . (($this->config['dateOrder'] === 'desc') ? '-' : '') . '1 day');
                if ($time === false) {
                    throw new RuntimeException('Invalid date format: ' . $date . (($this->config['dateOrder'] === 'desc') ? '-' : '') . '1 day');
                }
                $date = date('Y-m-d', $time);
            }
        }
        return $paddingDays;
    }

    /**
     * 次月のパディング日を取得
     *
     * @param array $entries
     * @return array
     */
    public function getNextMonthPaddingDays(array $entries): array
    {
        $paddingDays = [];

        if ($this->config['mode'] !== 'month') {
            return [];
        }
        if ($this->config['aroundEntry'] !== 'on') {
            return [];
        }
        if ($this->config['dateOrder'] !== 'desc') {
            $lastW  = intval($this->formatDate('w', 'last day of' . substr($this->ymd, 0, 7)));
            $span = 6 - ($lastW + (7 - $this->beginW)) % 7;
            $date = substr($this->endDate, 0, 7) . '-01';
        } else {
            $span = ($this->firstW + (7 - $this->beginW)) % 7;
            $date = $this->formatDate('Y-m-d', 'last day of' . substr($this->startDate, 0, 7));
        }
        if ($span === 0) {
            return [];
        }
        for ($i = 0; $i < $span; $i++) {
            $pw = intval($this->formatDate('w', $date));
            if (isset($this->config['weekLabels'][$pw]) && $this->config['weekLabels'][$pw]) {
                $paddingDays[] = [
                    'day' => intval(substr($date, 8, 2)), // 先頭の0削除
                    'date' => $date,
                    'w' => $pw,
                    'week' => $this->config['weekLabels'][$pw],
                    'padding' => true,
                    'url' => acmsLink([
                        'bid' => $this->bid,
                        'cid' => $this->cid,
                        'date' => $this->formatDate('Y/m/d', $date),
                    ]),
                    'entries' => $this->getEntriesByDate($date, $entries),
                ];
                $date = $this->formatDate('Y-m-d', $date . (($this->config['dateOrder'] === 'desc') ? '-' : '') . '1 day');
            }
        }
        return $paddingDays;
    }

    /**
     * dateブロックの変数の取得
     *
     * @return array
     */
    public function getDate(): array
    {
        $weekTitle = [];

        $py = '';
        $pm = '';
        $pd = '';
        $ny = '';
        $nm = '';
        $nd = '';
        switch ($this->config['mode']) {
            case "month":
                $prevtime  = mktime(0, 0, 0, intval($this->m) - 1, 1, intval($this->y));
                $nexttime  = mktime(0, 0, 0, intval($this->m) + 1, 1, intval($this->y));
                list($py, $pm, $pd) = [
                    date('Y', $prevtime),
                    date('m', $prevtime),
                    date('d', $prevtime)
                ];
                list($ny, $nm, $nd) = [
                    date('Y', $nexttime),
                    date('m', $nexttime),
                    date('d', $nexttime)
                ];

                break;

            case "week":
                $prev = $this->computeDate(intval($this->y), intval($this->m), intval($this->d), -7);
                $next = $this->computeDate(intval($this->y), intval($this->m), intval($this->d), 7);
                list($py, $pm, $pd) = explode('-', $prev);
                list($ny, $nm, $nd) = explode('-', $next);
                $weekTitle = [
                    'firstWeekDay' => $this->firstDay
                ];

                break;

            case "days":
            case "until_days":
                $prev = $this->computeDate(intval($this->y), intval($this->m), intval($this->d), -(int) $this->config['pagerCount']);
                $next = $this->computeDate(intval($this->y), intval($this->m), intval($this->d), $this->config['pagerCount']);
                list($py, $pm, $pd) = explode('-', $prev);
                list($ny, $nm, $nd) = explode('-', $next);
                $weekTitle = [
                    'firstWeekDay' => $this->firstDay
                ];

                break;
        }

        $vars = [
            'year'      => $this->y,
            'month'     => $this->m,
            'day'       => substr($this->d, 0, 2),
            'prevDate'  => "$py/$pm/$pd",
            'nextDate'  => "$ny/$nm/$nd",
            'date'      => $this->ymd,
            'prevMonth' => $pm,
            'nextMonth' => $nm,
        ];

        $vars += $weekTitle;

        return $vars;
    }

    protected function formatDate(string $format, string $datetime): string
    {
        $time = strtotime($datetime);
        if ($time === false) {
            throw new RuntimeException('Invalid date format: ' . $datetime);
        }
        return date($format, $time);
    }
}
