<?php

class ACMS_GET_Calendar_Month extends ACMS_GET
{
    public $_axis  = [
        'bid'   => 'self',
        'cid'   => 'self',
    ];

    public $_scope = [
        'date'  => 'global',
        'start' => 'global',
        'end'   => 'global',
    ];

    public function get()
    {
        $ym = substr($this->start, 0, 7);

        if ('1000-01' == $ym) {
            $ym = date('Y-m', requestTime());
        }
        list($y, $m)    = explode('-', $ym);

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('entry');
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);

        $SQL->addSelect(SQL::newFunction('entry_datetime', ['SUBSTR', 0, 10]), 'entry_date', null, 'DISTINCT');

        $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        ACMS_Filter::blogTree($SQL, $this->bid, $this->blogAxis());
        ACMS_Filter::blogStatus($SQL);
        $SQL->addLeftJoin('category', 'category_id', 'entry_category_id');
        if ($this->cid) {
            ACMS_Filter::categoryTree($SQL, $this->cid, $this->categoryAxis());
        }
        ACMS_Filter::categoryStatus($SQL);
        ACMS_Filter::entrySession($SQL);
        ACMS_Filter::entrySpan($SQL, $ym . '-01 00:00:00', $ym . '-31 23:59:59');
        $q  = $SQL->get(dsn());

        $exists = [];
        $all    = $DB->query($q, 'all');
        foreach ($all as $row) {
            $exists[]   = $row['entry_date'];
        }

        $beginW = intval(config('calendar_begin_week'));
        $endW   = (6 + $beginW) % 7;
        $label  = configArray('calendar_week_label');
        for ($i = 0; $i < 7; $i++) {
            $w  = ($beginW + $i) % 7;
            $Tpl->add('weekLabel:loop', [
                'w'     => $w,
                'label' => $label[$w],
            ]);
        }

        //--------
        // spacer
        $firstW     = intval(date('w', strtotime($ym . '-01')));

        if ($span = ($firstW + (7 - $beginW)) % 7) {
            for ($i = 0; $i < $span; $i++) {
                $Tpl->add('spacer');
                $Tpl->add('day:loop');
            }
        }

        //-----
        // day
        $lastDay    = intval(date('t', strtotime($ym . '-01')));
        $curW   = $firstW;
        for ($day = 1; $day <= $lastDay; $day++) {
            $date   = $ym . '-' . sprintf('%02d', $day);
            if (in_array($date, $exists, true)) {
                $Tpl->add('link', [
                    'w'     => $curW,
                    'url'   => acmsLink([
                        'bid'   => $this->bid,
                        'cid'   => is_int($this->cid) ? $this->cid : null,
                        'date'  => [
                            intval($y), intval($m), intval($day)
                        ],
                    ]),
                    'day'   => $day,
                ]);
            } else {
                $Tpl->add('none', [
                    'w'     => $curW,
                    'day'   => $day,
                ]);
            }
            $Tpl->add('day:loop');
            $curW   = ($curW + 1) % 7;
            if ($beginW == $curW) {
                $Tpl->add('week:loop');
            }
        }

        //--------
        // spacer
        $lastW  = ($curW + 6) % 7;

        if ($span = 6 - ($lastW + (7 - $beginW)) % 7) {
            for ($i = 0; $i < $span; $i++) {
                $Tpl->add('spacer');
                $Tpl->add('day:loop');
            }
            $Tpl->add('week:loop');
        }

        $prevtime   = mktime(0, 0, 0, intval($m) - 1, 1, intval($y));
        $nexttime   = mktime(0, 0, 0, intval($m) + 1, 1, intval($y));
        $vars   = [
            'monthUrl'  => acmsLink([
                'bid'   => $this->bid,
                'cid'   => is_int($this->cid) ? $this->cid : null,
                'date'  => [$y, $m],
            ]),
            'month' => intval($m),
            'yearUrl'   => acmsLink([
                'bid'   => $this->bid,
                'cid'   => is_int($this->cid) ? $this->cid : null,
                'date'  => $y,
            ]),
            'year'  => $y,
            'prevUrl'   => acmsLink([
                'bid'   => $this->bid,
                'cid'   => is_int($this->cid) ? $this->cid : null,
                'date'  => [
                    date('Y', $prevtime),
                    date('m', $prevtime),
                ],
            ]),
            'nextUrl'   => acmsLink([
                'bid'   => $this->bid,
                'cid'   => is_int($this->cid) ? $this->cid : null,
                'date'  => [
                    date('Y', $nexttime),
                    date('m', $nexttime),
                ],
            ]),
        ];
        $Tpl->add(null, $vars);

        return $Tpl->get();
    }
}
