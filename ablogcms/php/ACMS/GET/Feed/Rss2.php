<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Feed_Rss2 extends ACMS_GET_Entry
{
    public $_axis = [
        'bid'   => 'descendant-or-self',
        'cid'   => 'descendant-or-self',
    ];

    public $_scope = [
        'uid'       => 'global',
        'cid'       => 'global',
        'eid'       => 'global',
        'keyword'   => 'global',
        'tag'       => 'global',
        'field'     => 'global',
        'date'      => 'global',
        'start'     => 'global',
        'end'       => 'global',
        'page'      => 'global',
    ];

    public function get()
    {
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $this->buildModuleField($Tpl);
        $DB     = DB::singleton(dsn());

        $limit  = intval(config('feed_rss2_limit'));
        $order  = ORDER ? ORDER : config('feed_rss2_order');

        $blogField      = new Field_Search(config('feed_rss2_blog_field'));
        $categoryField  = new Field_Search(config('feed_rss2_category_field'));
        $entryField     = new Field_Search(config('feed_rss2_entry_field'));

        $SQL    = SQL::newSelect('blog');
        $SQL->addSelect('blog_id');
        ACMS_Filter::blogTree($SQL, $this->bid, $this->blogAxis());
        $blogArray  = $DB->query($SQL->get(dsn()), 'all');
        $exceptBlog = [];

        foreach ($blogArray as $bid) {
            $bid    = $bid['blog_id'];
            $bconf  = Config::loadBlogConfigSet($bid);
            if ($bconf->get('feed_output_disable') === 'on') {
                $exceptBlog[] = $bid;
            }
        }

        $SQL    = SQL::newSelect('entry');
        $SQL->addLeftJoin('category', 'category_id', 'entry_category_id');
        $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');

        ACMS_Filter::blogTree($SQL, $this->bid, $this->blogAxis());
        ACMS_Filter::blogStatus($SQL);
        if ($this->cid) {
            ACMS_Filter::categoryTree($SQL, $this->cid, $this->categoryAxis());
        }
        ACMS_Filter::categoryStatus($SQL);

        // config（feed_output_disable）で指定されたブログを除外
        $SQL->addWhereNotIn('blog_id', $exceptBlog);

        if (!empty($this->eid)) {
            $SQL->addWhereOpr('entry_id', $this->eid);
        } else {
            $SQL->addWhereOpr('entry_indexing', 'on');
        }
        ACMS_Filter::entrySession($SQL);
        ACMS_Filter::entrySpan($SQL, $this->start, $this->end);

        if (!empty($this->tags)) {
            ACMS_Filter::entryTag($SQL, $this->tags);
        }
        if (!empty($this->keyword)) {
            ACMS_Filter::entryKeyword($SQL, $this->keyword);
        }
        ACMS_Filter::blogField($SQL, $blogField);
        ACMS_Filter::categoryField($SQL, $categoryField);
        ACMS_Filter::entryField($SQL, $entryField);

        $Amount = new SQL_Select($SQL);
        $Amount->setSelect('*', 'entry_amount', null, 'COUNT');
        if (!$pageAmount = $DB->query($Amount->get(dsn()), 'one')) {
            return '';
        }

        $from   = 0;
        $SQL->setLimit(($from + $limit > $pageAmount) ? ($pageAmount - $from) : $limit, $from);
        ACMS_Filter::entryOrder($SQL, $order, $this->uid, $this->cid);

        $q = $SQL->get(dsn());
        $statement = $DB->query($q, 'exec');

        $lastBuildDate  = '1000-01-01 00:00:00';
        while ($row = $DB->next($statement)) {
            $bid        = $row['entry_blog_id'];

            $uid    = $row['entry_user_id'];
            $cid    = $row['entry_category_id'];
            $eid    = $row['entry_id'];
            $link   = $row['entry_link'];
            $title  = $row['entry_title'];
            $summaryRange   = $row['entry_summary_range'];
            $permalink  = acmsLink([
                'bid'   => $bid,
                'cid'   => $cid,
                'eid'   => $eid,
            ]);

            $vars   = [
                'title'     => $row['entry_title'],
                'link'      => !empty($link) ? $link : $permalink,
                'creator'   => ACMS_RAM::userName($uid),
                'permalink' => $permalink,
                'pubDate'   => date('r', strtotime($row['entry_datetime'])),
            ];

            if (!empty($cid)) {
                $vars['category'] = ACMS_RAM::categoryName($cid);
            }

            $vars += $this->buildField(loadEntryField($eid), $Tpl, ['item:loop']);

            //--------
            // column
            if (empty($link) or 'on' == config('feed_rss2_outsidelink_description')) {
                /** @var \Acms\Services\Unit\Repository $unitRepository */
                $unitRepository = Application::make('unit-repository');
                /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
                $unitRenderingService = Application::make('unit-rendering-front');

                $collection = $unitRepository->loadUnits($eid, null, $summaryRange);
                if (count($collection) > 0) {
                    $unitRenderingService->render($collection, $Tpl, $eid);
                    if (!empty($summaryRange)) {
                        $amount = $unitRepository->countUnitsTrait($eid);
                        if ($summaryRange < $amount) {
                            $vars['continueUrl']    = $permalink;
                            $vars['continueName']   = $title;
                        }
                    }
                }
            }

            $Tpl->add('item:loop', $vars);

            if ($lastBuildDate < $row['entry_updated_datetime']) {
                $lastBuildDate = $row['entry_updated_datetime'];
            }
        }

        $Tpl->add(null, [
            'lastBuildDate' => date('r', strtotime($lastBuildDate))
        ]);
        return $Tpl->get();
    }
}
